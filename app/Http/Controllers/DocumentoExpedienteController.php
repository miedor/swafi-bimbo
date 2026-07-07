<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DocumentoExpedienteController extends Controller
{
    public function show(int $documento): BinaryFileResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);

        $absolutePath = $this->validatedDocumentPath($documentoData, $expediente);

        $this->registrarBitacora(
            numeroActivo: $expediente->numero_activo,
            accion: 'VISUALIZA_DOCUMENTO',
            tablaAfectada: 'documentos_expediente',
            registroClave: (string) $documentoData->id,
            detalle: [
                'expediente_id' => $expediente->id,
                'folio_factura' => $expediente->folio_factura,
                'tipo_documento' => $documentoData->tipo_documento,
                'nombre_archivo' => $documentoData->nombre_archivo,
            ]
        );

        $fileName = $this->safeDownloadName(
            $documentoData->nombre_archivo,
            'documento_' . $documentoData->id
        );

        return response()->file($absolutePath, [
            'Content-Type' => $this->mimeType($documentoData, $absolutePath),
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function download(int $documento): BinaryFileResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);

        $absolutePath = $this->validatedDocumentPath($documentoData, $expediente);

        $this->registrarBitacora(
            numeroActivo: $expediente->numero_activo,
            accion: 'DESCARGA_DOCUMENTO',
            tablaAfectada: 'documentos_expediente',
            registroClave: (string) $documentoData->id,
            detalle: [
                'expediente_id' => $expediente->id,
                'folio_factura' => $expediente->folio_factura,
                'tipo_documento' => $documentoData->tipo_documento,
                'nombre_archivo' => $documentoData->nombre_archivo,
            ]
        );

        $fileName = $this->safeDownloadName(
            $documentoData->nombre_archivo,
            'documento_' . $documentoData->id
        );

        return response()->download($absolutePath, $fileName, [
            'Content-Type' => $this->mimeType($documentoData, $absolutePath),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function downloadAll(int $expediente): BinaryFileResponse|RedirectResponse
    {
        $expedienteData = DB::table('expedientes')
            ->where('id', $expediente)
            ->first();

        abort_if(!$expedienteData, 404, 'El expediente solicitado no existe.');

        $documentos = DB::table('documentos_expediente')
            ->where('expediente_id', $expedienteData->id)
            ->where('vigente', true)
            ->orderBy('tipo_documento')
            ->orderByDesc('version')
            ->get();

        if ($documentos->isEmpty()) {
            return redirect()
                ->route('expediente', $expedienteData->id)
                ->withErrors([
                    'documentos' => 'El expediente no tiene documentos vigentes para descargar.',
                ]);
        }

        $preparedFiles = [];

        foreach ($documentos as $documentoData) {
            $absolutePath = $this->validatedDocumentPath($documentoData, $expedienteData);

            $preparedFiles[] = [
                'documento' => $documentoData,
                'absolute_path' => $absolutePath,
                'zip_name' => $this->zipEntryName($documentoData),
            ];
        }

        $tempDirectory = storage_path('app/private/swafi/temp');
        File::ensureDirectoryExists($tempDirectory);

        $zipFileName = 'expediente_'
            . Str::slug($expedienteData->numero_activo ?: 'activo')
            . '_'
            . Str::slug($expedienteData->folio_factura ?: 'factura')
            . '_'
            . now()->format('Ymd_His')
            . '.zip';

        $zipPath = $tempDirectory . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()
                ->route('expediente', $expedienteData->id)
                ->withErrors([
                    'documentos' => 'No fue posible generar el archivo ZIP del expediente.',
                ]);
        }

        foreach ($preparedFiles as $preparedFile) {
            $zip->addFile($preparedFile['absolute_path'], $preparedFile['zip_name']);
        }

        $zip->close();

        $this->registrarBitacora(
            numeroActivo: $expedienteData->numero_activo,
            accion: 'DESCARGA_EXPEDIENTE_ZIP',
            tablaAfectada: 'documentos_expediente',
            registroClave: (string) $expedienteData->id,
            detalle: [
                'expediente_id' => $expedienteData->id,
                'folio_factura' => $expedienteData->folio_factura,
                'total_documentos' => count($preparedFiles),
                'archivo_zip' => $zipFileName,
            ]
        );

        return response()
            ->download($zipPath, $zipFileName, [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ])
            ->deleteFileAfterSend(true);
    }

    private function findDocumentContext(int $documento): array
    {
        $documentoData = DB::table('documentos_expediente')
            ->where('id', $documento)
            ->first();

        abort_if(!$documentoData, 404, 'El documento solicitado no existe.');

        $expediente = DB::table('expedientes')
            ->where('id', $documentoData->expediente_id)
            ->first();

        abort_if(!$expediente, 404, 'El expediente relacionado con el documento no existe.');

        return [$documentoData, $expediente];
    }

    private function validatedDocumentPath(object $documentoData, object $expediente): string
    {
        if (!$documentoData->vigente) {
            abort(404, 'El documento no se encuentra vigente.');
        }

        if (!$documentoData->ruta_archivo || !Storage::disk('local')->exists($documentoData->ruta_archivo)) {
            $this->registrarBitacora(
                numeroActivo: $expediente->numero_activo,
                accion: 'ARCHIVO_NO_LOCALIZADO',
                tablaAfectada: 'documentos_expediente',
                registroClave: (string) $documentoData->id,
                detalle: [
                    'expediente_id' => $expediente->id,
                    'folio_factura' => $expediente->folio_factura,
                    'ruta_archivo' => $documentoData->ruta_archivo,
                    'nombre_archivo' => $documentoData->nombre_archivo,
                ]
            );

            abort(404, 'El archivo físico no fue localizado en el almacenamiento privado.');
        }

        $absolutePath = Storage::disk('local')->path($documentoData->ruta_archivo);

        if ($documentoData->hash_sha256) {
            $currentHash = hash_file('sha256', $absolutePath);

            if (!hash_equals(strtolower($documentoData->hash_sha256), strtolower($currentHash))) {
                $this->registrarBitacora(
                    numeroActivo: $expediente->numero_activo,
                    accion: 'INTEGRIDAD_FALLIDA',
                    tablaAfectada: 'documentos_expediente',
                    registroClave: (string) $documentoData->id,
                    detalle: [
                        'expediente_id' => $expediente->id,
                        'folio_factura' => $expediente->folio_factura,
                        'nombre_archivo' => $documentoData->nombre_archivo,
                        'hash_registrado' => $documentoData->hash_sha256,
                        'hash_actual' => $currentHash,
                    ]
                );

                abort(409, 'La integridad del documento no coincide con el hash registrado.');
            }
        }

        return $absolutePath;
    }

    private function mimeType(object $documentoData, string $absolutePath): string
    {
        if (!empty($documentoData->mime_type)) {
            return $documentoData->mime_type;
        }

        $extension = strtolower(pathinfo($documentoData->nombre_archivo ?? '', PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            default => mime_content_type($absolutePath) ?: 'application/octet-stream',
        };
    }

    private function safeDownloadName(?string $name, string $fallback): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            $name = $fallback;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $baseName = pathinfo($name, PATHINFO_FILENAME);

        $safeBaseName = Str::slug(Str::ascii($baseName));

        if ($safeBaseName === '') {
            $safeBaseName = Str::slug($fallback) ?: 'documento';
        }

        return $extension
            ? $safeBaseName . '.' . $extension
            : $safeBaseName;
    }

    private function zipEntryName(object $documentoData): string
    {
        $tipo = Str::slug($documentoData->tipo_documento ?: 'documento');
        $version = (int) ($documentoData->version ?? 1);
        $name = $this->safeDownloadName($documentoData->nombre_archivo, 'documento_' . $documentoData->id);

        return $tipo . '_v' . $version . '_' . $name;
    }

    private function registrarBitacora(
        ?string $numeroActivo,
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        array $detalle
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => session('swafi_user_id') ?: auth()->id(),
                'modulo' => 'M03 Consultas',
                'accion' => $accion,
                'tabla_afectada' => $tablaAfectada,
                'registro_clave' => $registroClave,
                'antes' => null,
                'despues' => json_encode($detalle, JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La descarga o visualización no debe fallar por un error de bitácora.
        }
    }
}

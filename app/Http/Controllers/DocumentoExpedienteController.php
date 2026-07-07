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
    public function show(int $documento): BinaryFileResponse|RedirectResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);

        $pathResult = $this->resolveDocumentPath($documentoData, $expediente);

        if (!$pathResult['ok']) {
            return $this->redirectWithDocumentError($expediente, $pathResult['message']);
        }

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

        return response()->file($pathResult['path'], [
            'Content-Type' => $this->mimeType($documentoData, $pathResult['path']),
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function download(int $documento): BinaryFileResponse|RedirectResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);

        $pathResult = $this->resolveDocumentPath($documentoData, $expediente);

        if (!$pathResult['ok']) {
            return $this->redirectWithDocumentError($expediente, $pathResult['message']);
        }

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

        return response()->download($pathResult['path'], $fileName, [
            'Content-Type' => $this->mimeType($documentoData, $pathResult['path']),
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
            return $this->redirectWithDocumentError(
                $expedienteData,
                'El expediente no tiene documentos vigentes para descargar.'
            );
        }

        if (!class_exists(ZipArchive::class)) {
            return $this->redirectWithDocumentError(
                $expedienteData,
                'La extensión ZIP de PHP no está disponible en el servidor.'
            );
        }

        $preparedFiles = [];
        $missingFiles = [];

        foreach ($documentos as $documentoData) {
            $pathResult = $this->resolveDocumentPath($documentoData, $expedienteData);

            if (!$pathResult['ok']) {
                $missingFiles[] = $documentoData->nombre_archivo . ' - ' . $pathResult['message'];
                continue;
            }

            $preparedFiles[] = [
                'documento' => $documentoData,
                'absolute_path' => $pathResult['path'],
                'zip_name' => $this->zipEntryName($documentoData),
            ];
        }

        if (!empty($missingFiles)) {
            $this->registrarBitacora(
                numeroActivo: $expedienteData->numero_activo,
                accion: 'DESCARGA_ZIP_INCOMPLETA',
                tablaAfectada: 'documentos_expediente',
                registroClave: (string) $expedienteData->id,
                detalle: [
                    'expediente_id' => $expedienteData->id,
                    'folio_factura' => $expedienteData->folio_factura,
                    'archivos_no_localizados' => $missingFiles,
                ]
            );

            return $this->redirectWithDocumentError(
                $expedienteData,
                'No fue posible generar el ZIP porque uno o más archivos físicos no existen en el almacenamiento privado. Revisa o vuelve a cargar los documentos del expediente.'
            );
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
            return $this->redirectWithDocumentError(
                $expedienteData,
                'No fue posible generar el archivo ZIP del expediente.'
            );
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

    private function resolveDocumentPath(object $documentoData, object $expediente): array
    {
        if (!(bool) $documentoData->vigente) {
            $this->registrarBitacora(
                numeroActivo: $expediente->numero_activo,
                accion: 'DOCUMENTO_NO_VIGENTE',
                tablaAfectada: 'documentos_expediente',
                registroClave: (string) $documentoData->id,
                detalle: [
                    'expediente_id' => $expediente->id,
                    'folio_factura' => $expediente->folio_factura,
                    'nombre_archivo' => $documentoData->nombre_archivo,
                ]
            );

            return [
                'ok' => false,
                'path' => null,
                'message' => 'El documento no se encuentra vigente.',
            ];
        }

        $absolutePath = $this->locatePhysicalFile($documentoData->ruta_archivo);

        if (!$absolutePath) {
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

            return [
                'ok' => false,
                'path' => null,
                'message' => 'El registro existe en MySQL, pero el archivo físico no fue localizado en el almacenamiento privado.',
            ];
        }

        if (!empty($documentoData->hash_sha256)) {
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

                return [
                    'ok' => false,
                    'path' => null,
                    'message' => 'La integridad del documento no coincide con el hash SHA-256 registrado.',
                ];
            }
        }

        return [
            'ok' => true,
            'path' => $absolutePath,
            'message' => null,
        ];
    }

    private function locatePhysicalFile(?string $rutaArchivo): ?string
    {
        $rutaArchivo = trim((string) $rutaArchivo);

        if ($rutaArchivo === '') {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', $rutaArchivo);
        $normalizedPath = ltrim($normalizedPath, '/');

        $candidates = [
            Storage::disk('local')->path($normalizedPath),
            storage_path('app/private/' . $normalizedPath),
            storage_path('app/' . $normalizedPath),
            storage_path('app/public/' . $normalizedPath),
        ];

        if (Str::startsWith($normalizedPath, 'private/')) {
            $candidates[] = storage_path('app/' . $normalizedPath);
        }

        if (Str::startsWith($normalizedPath, 'storage/app/private/')) {
            $candidates[] = base_path($normalizedPath);
        }

        if (Str::startsWith($normalizedPath, 'storage/app/')) {
            $candidates[] = base_path($normalizedPath);
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function redirectWithDocumentError(object $expediente, string $message): RedirectResponse
    {
        return redirect()
            ->route('expediente', $expediente->id)
            ->withErrors([
                'documentos' => $message,
            ]);
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

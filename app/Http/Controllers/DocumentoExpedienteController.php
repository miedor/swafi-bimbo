<?php

namespace App\Http\Controllers;

use App\Models\DocumentoExpediente;
use App\Services\CfdiValidationService;
use App\Services\SwafiStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DocumentoExpedienteController extends Controller
{
    public function __construct(private readonly SwafiStorageService $storage)
    {
    }

    public function show(int $documento): StreamedResponse|RedirectResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);
        $storageResult = $this->resolveDocumentStorage($documentoData, $expediente);

        if (!$storageResult['ok']) {
            return $this->redirectWithDocumentError($expediente, $storageResult['message']);
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
                'storage_disk' => $storageResult['disk'],
            ]
        );

        $fileName = $this->safeDownloadName(
            $documentoData->nombre_archivo,
            'documento_' . $documentoData->id
        );

        return $this->storage->inlineResponse(
            disk: $storageResult['disk'],
            path: $storageResult['path'],
            downloadName: $fileName,
            mimeType: $this->mimeType($documentoData, $storageResult)
        );
    }

    public function download(int $documento): StreamedResponse|RedirectResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);
        $storageResult = $this->resolveDocumentStorage($documentoData, $expediente);

        if (!$storageResult['ok']) {
            return $this->redirectWithDocumentError($expediente, $storageResult['message']);
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
                'storage_disk' => $storageResult['disk'],
            ]
        );

        $fileName = $this->safeDownloadName(
            $documentoData->nombre_archivo,
            'documento_' . $documentoData->id
        );

        return $this->storage->downloadResponse(
            disk: $storageResult['disk'],
            path: $storageResult['path'],
            downloadName: $fileName,
            mimeType: $this->mimeType($documentoData, $storageResult)
        );
    }

    public function downloadAll(int $expediente): BinaryFileResponse|RedirectResponse
    {
        $expedienteData = DB::table('expedientes')
            ->where('id', $expediente)
            ->whereNull('deleted_at')
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

        $tempDirectory = storage_path('app/private/swafi/temp/zip_' . Str::uuid());
        File::ensureDirectoryExists($tempDirectory);

        $preparedFiles = [];
        $missingFiles = [];
        $keepDirectoryUntilResponse = false;

        try {
            foreach ($documentos as $documentoData) {
                $storageResult = $this->resolveDocumentStorage($documentoData, $expedienteData);

                if (!$storageResult['ok']) {
                    $missingFiles[] = $documentoData->nombre_archivo . ' - ' . $storageResult['message'];
                    continue;
                }

                $temporaryPath = $this->storage->copyToTemporaryFile(
                    disk: $storageResult['disk'],
                    path: $storageResult['path'],
                    tempDirectory: $tempDirectory,
                    fileName: $this->zipEntryName($documentoData)
                );

                $preparedFiles[] = [
                    'documento' => $documentoData,
                    'temporary_path' => $temporaryPath,
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
                    'No fue posible generar el ZIP porque uno o más archivos no existen o no superaron la validación de integridad.'
                );
            }

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
                $zip->addFile($preparedFile['temporary_path'], $preparedFile['zip_name']);
            }

            $zip->close();

            foreach ($preparedFiles as $preparedFile) {
                @unlink($preparedFile['temporary_path']);
            }

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

            $keepDirectoryUntilResponse = true;

            register_shutdown_function(static function () use ($tempDirectory): void {
                if (is_dir($tempDirectory)) {
                    File::deleteDirectory($tempDirectory);
                }
            });

            return response()
                ->download($zipPath, $zipFileName, [
                    'Content-Type' => 'application/zip',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                ])
                ->deleteFileAfterSend(true);
        } finally {
            foreach ($preparedFiles as $preparedFile) {
                if (isset($preparedFile['temporary_path'])) {
                    @unlink($preparedFile['temporary_path']);
                }
            }

            if (!$keepDirectoryUntilResponse && is_dir($tempDirectory)) {
                File::deleteDirectory($tempDirectory);
            }
        }
    }

    public function store(Request $request, int $expediente): RedirectResponse
    {
        $expedienteData = DB::table('expedientes')
            ->where('id', $expediente)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$expedienteData, 404, 'El expediente solicitado no existe.');

        $request->validate([
            'documentos' => ['required', 'array', 'min:1', 'max:20'],
            'documentos.*' => ['required', 'file', 'mimes:pdf,xml', 'max:20480'],
        ], [
            'documentos.required' => 'Debes seleccionar al menos un documento PDF o XML.',
            'documentos.array' => 'La carga de documentos no tiene un formato válido.',
            'documentos.*.file' => 'Uno de los documentos seleccionados no es válido.',
            'documentos.*.mimes' => 'Solo se permiten archivos PDF o XML.',
            'documentos.*.max' => 'Cada documento no debe superar los 20 MB.',
        ]);

        $guardados = [];
        $storedFiles = [];

        try {
            DB::transaction(function () use ($request, $expedienteData, &$guardados, &$storedFiles): void {
                foreach ($request->file('documentos', []) as $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $tipoDocumento = strtoupper($extension);
                    $originalName = $file->getClientOriginalName();

                    $stored = $this->storeUploadedDocumentFile(
                        file: $file,
                        numeroActivo: $expedienteData->numero_activo,
                        folioFactura: $expedienteData->folio_factura,
                        originalName: $originalName
                    );

                    $storedFiles[] = $stored;

                    $resultado = $this->storeOrReplaceDocumentRecord(
                        expedienteId: (int) $expedienteData->id,
                        tipoDocumento: $tipoDocumento,
                        nombreArchivo: $originalName,
                        stored: $stored
                    );

                    $guardados[] = $resultado;

                    $this->registrarBitacora(
                        numeroActivo: $expedienteData->numero_activo,
                        accion: $resultado['accion'] === 'reemplazado' ? 'DOCUMENTO_REEMPLAZADO' : 'DOCUMENTO_AGREGADO',
                        tablaAfectada: 'documentos_expediente',
                        registroClave: (string) $resultado['documento_id'],
                        detalle: [
                            'expediente_id' => $expedienteData->id,
                            'folio_factura' => $expedienteData->folio_factura,
                            'tipo_documento' => $tipoDocumento,
                            'nombre_archivo' => $originalName,
                            'version' => $resultado['version'],
                            'storage_disk' => $stored['disk'],
                        ]
                    );
                }

                $this->updateDocumentalStatus((int) $expedienteData->id, $expedienteData->numero_activo);
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                $this->storage->delete($storedFile['disk'], $storedFile['path']);
            }

            throw $exception;
        }

        return redirect()
            ->route('expediente', ['expediente' => $expedienteData->id, 'tab' => 'documentos'])
            ->with('success', 'Los documentos fueron ligados correctamente al expediente. Agregados/Reemplazados: ' . count($guardados));
    }

    public function destroy(int $documento): RedirectResponse
    {
        [$documentoData, $expediente] = $this->findDocumentContext($documento);

        if (!(bool) $documentoData->vigente) {
            return redirect()
                ->route('expediente', ['expediente' => $expediente->id, 'tab' => 'documentos'])
                ->withErrors([
                    'documentos' => 'El documento seleccionado ya se encuentra dado de baja lógicamente.',
                ]);
        }

        DB::transaction(function () use ($documentoData, $expediente): void {
            DB::table('documentos_expediente')
                ->where('id', $documentoData->id)
                ->update([
                    'vigente' => false,
                    'updated_at' => now(),
                ]);

            $this->updateDocumentalStatus((int) $expediente->id, $expediente->numero_activo);

            $this->registrarBitacora(
                numeroActivo: $expediente->numero_activo,
                accion: 'DOCUMENTO_BAJA_LOGICA',
                tablaAfectada: 'documentos_expediente',
                registroClave: (string) $documentoData->id,
                detalle: [
                    'expediente_id' => $expediente->id,
                    'folio_factura' => $expediente->folio_factura,
                    'tipo_documento' => $documentoData->tipo_documento,
                    'nombre_archivo' => $documentoData->nombre_archivo,
                    'storage_disk' => $documentoData->storage_disk ?? 'local',
                    'eliminacion' => 'Baja lógica. El archivo físico se conserva para trazabilidad.',
                ]
            );
        });

        return redirect()
            ->route('expediente', ['expediente' => $expediente->id, 'tab' => 'documentos'])
            ->with('success', 'El documento fue dado de baja lógicamente. El archivo físico y la trazabilidad se conservan.');
    }

    /**
     * @return array{disk:string,path:string,mime_type:string,tamano_bytes:int,hash_sha256:string}
     */
    private function storeUploadedDocumentFile(
        $file,
        string $numeroActivo,
        string $folioFactura,
        string $originalName
    ): array {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'dat';
        $safeName = $this->safeFileName($originalName, $extension);
        $basePath = 'swafi/expedientes/'
            . Str::slug($numeroActivo)
            . '/'
            . Str::slug($folioFactura);

        $storedName = now()->format('YmdHis')
            . '_'
            . Str::random(8)
            . '_'
            . $safeName;

        return $this->storage->storeUploadedFile(
            file: $file,
            directory: $basePath,
            storedName: $storedName
        );
    }

    private function storeOrReplaceDocumentRecord(
        int $expedienteId,
        string $tipoDocumento,
        string $nombreArchivo,
        array $stored
    ): array {
        $normalizedName = $this->normalizeDocumentIdentity($nombreArchivo);
        $hashSha256 = $stored['hash_sha256'];

        $existingDocument = DocumentoExpediente::where('expediente_id', $expedienteId)
            ->where('tipo_documento', $tipoDocumento)
            ->where(function ($query) use ($normalizedName, $hashSha256): void {
                $query->whereRaw('LOWER(nombre_archivo) = ?', [$normalizedName]);

                if (!empty($hashSha256)) {
                    $query->orWhere('hash_sha256', $hashSha256);
                }
            })
            ->orderByDesc('version')
            ->first();

        if ($existingDocument) {
            DocumentoExpediente::where('expediente_id', $expedienteId)
                ->where('tipo_documento', $tipoDocumento)
                ->where(function ($query) use ($normalizedName, $hashSha256): void {
                    $query->whereRaw('LOWER(nombre_archivo) = ?', [$normalizedName]);

                    if (!empty($hashSha256)) {
                        $query->orWhere('hash_sha256', $hashSha256);
                    }
                })
                ->update([
                    'vigente' => false,
                    'updated_at' => now(),
                ]);

            $version = ((int) $existingDocument->version) + 1;
            $accion = 'reemplazado';
        } else {
            $version = 1;
            $accion = 'agregado';
        }

        $documento = DocumentoExpediente::create([
            'expediente_id' => $expedienteId,
            'tipo_documento' => $tipoDocumento,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $stored['path'],
            'storage_disk' => $stored['disk'],
            'mime_type' => $stored['mime_type'],
            'tamano_bytes' => $stored['tamano_bytes'],
            'hash_sha256' => $stored['hash_sha256'],
            'version' => $version,
            'vigente' => true,
            'cargado_por' => auth()->id(),
        ]);

        return [
            'accion' => $accion,
            'documento_id' => $documento->id,
            'tipo_documento' => $documento->tipo_documento,
            'nombre_archivo' => $documento->nombre_archivo,
            'version' => $documento->version,
            'hash_sha256' => $documento->hash_sha256,
            'storage_disk' => $documento->storage_disk,
        ];
    }

    private function updateDocumentalStatus(int $expedienteId, string $numeroActivo): string
    {
        return app(CfdiValidationService::class)->recalculateExpedienteStatus(
            $expedienteId,
            $numeroActivo,
            auth()->id()
        );
    }

    private function findDocumentContext(int $documento): array
    {
        $documentoData = DB::table('documentos_expediente')
            ->where('id', $documento)
            ->first();

        abort_if(!$documentoData, 404, 'El documento solicitado no existe.');

        $expediente = DB::table('expedientes')
            ->where('id', $documentoData->expediente_id)
            ->whereNull('deleted_at')
            ->first();

        abort_if(!$expediente, 404, 'El expediente relacionado con el documento no existe.');

        return [$documentoData, $expediente];
    }

    private function resolveDocumentStorage(object $documentoData, object $expediente): array
    {
        if (!(bool) $documentoData->vigente) {
            return [
                'ok' => false,
                'disk' => $documentoData->storage_disk ?? 'local',
                'path' => (string) $documentoData->ruta_archivo,
                'message' => 'El documento no se encuentra vigente.',
            ];
        }

        $validation = $this->storage->validate(
            $documentoData->storage_disk ?? null,
            (string) $documentoData->ruta_archivo,
            $documentoData->hash_sha256 ?: null
        );

        if (!$validation['ok']) {
            $accion = str_contains(strtolower((string) $validation['message']), 'sha-256')
                ? 'INTEGRIDAD_FALLIDA'
                : 'ARCHIVO_NO_LOCALIZADO';

            $this->registrarBitacora(
                numeroActivo: $expediente->numero_activo,
                accion: $accion,
                tablaAfectada: 'documentos_expediente',
                registroClave: (string) $documentoData->id,
                detalle: [
                    'expediente_id' => $expediente->id,
                    'folio_factura' => $expediente->folio_factura,
                    'ruta_archivo' => $documentoData->ruta_archivo,
                    'storage_disk' => $documentoData->storage_disk ?? 'local',
                    'nombre_archivo' => $documentoData->nombre_archivo,
                    'hash_registrado' => $documentoData->hash_sha256,
                    'hash_actual' => $validation['hash_sha256'] ?? null,
                    'mensaje' => $validation['message'],
                ]
            );
        }

        return $validation;
    }

    private function redirectWithDocumentError(object $expediente, string $message): RedirectResponse
    {
        return redirect()
            ->route('expediente', ['expediente' => $expediente->id, 'tab' => 'documentos'])
            ->withErrors([
                'documentos' => $message,
            ]);
    }

    private function mimeType(object $documentoData, array $storageResult): string
    {
        if (!empty($documentoData->mime_type)) {
            return $documentoData->mime_type;
        }

        if (!empty($storageResult['mime_type'])) {
            return $storageResult['mime_type'];
        }

        $extension = strtolower(pathinfo($documentoData->nombre_archivo ?? '', PATHINFO_EXTENSION));

        return $this->mimeTypeFromExtension($extension);
    }

    private function mimeTypeFromExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
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

    private function safeFileName(string $name, string $defaultExtension): string
    {
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: $defaultExtension;
        $safeBaseName = Str::slug($baseName) ?: 'documento';

        return $safeBaseName . '.' . $extension;
    }

    private function zipEntryName(object $documentoData): string
    {
        $tipo = Str::slug($documentoData->tipo_documento ?: 'documento');
        $version = (int) ($documentoData->version ?? 1);
        $name = $this->safeDownloadName($documentoData->nombre_archivo, 'documento_' . $documentoData->id);

        return $tipo . '_v' . $version . '_' . $name;
    }

    private function normalizeDocumentIdentity(string $fileName): string
    {
        return Str::lower(trim(basename(str_replace('\\', '/', $fileName))));
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
        } catch (\Throwable) {
            // La descarga, carga o eliminación no debe fallar por un error de bitácora.
        }
    }
}

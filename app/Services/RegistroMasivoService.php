<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\DocumentoExpediente;
use App\Models\Expediente;
use App\Models\ImportacionMasiva;
use App\Models\ImportacionMasivaFila;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class RegistroMasivoService
{
    private const MAX_ROWS = 5000;
    private const MAX_ZIP_FILES = 5000;
    private const MAX_ZIP_UNCOMPRESSED_BYTES = 209715200;

    /** @var array<int, string>|null */
    private ?array $activeCurrencyCodes = null;

    private const REQUIRED_HEADERS = [
        'numero_activo',
        'descripcion',
        'folio_factura',
        'fecha_factura',
        'monto_factura',
        'moneda',
        'proveedor_rfc',
        'tipo_activo_clave',
        'centro_costo_clave',
        'planta_clave',
    ];

    private const OPTIONAL_HEADERS = [
        'uuid_cfdi',
        'ubicacion_codigo',
        'responsable_correo',
        'serie',
        'marca',
        'modelo',
        'fecha_adquisicion',
        'estatus_operativo',
        'documento_pdf',
        'documento_xml',
        'observaciones',
    ];

    public function __construct(
        private readonly SwafiStorageService $storage,
        private readonly AssetStatusCatalogService $statusCatalogs,
        private readonly SimpleXlsxReader $xlsxReader
    ) {
    }

    public function previsualizar(
        UploadedFile $layoutFile,
        UploadedFile $zipFile,
        ?int $userId
    ): ImportacionMasiva {
        $layout = $this->readLayoutRecords($layoutFile);
        $normalizedHeaders = $layout['headers'];
        $layoutFormat = $this->layoutFormat($layoutFile);

        $this->validateHeaders($normalizedHeaders);

        $zipTempDirectory = null;
        $storedCsv = null;
        $storedZip = null;

        try {
            [$zipIndex, $zipTempDirectory] = $this->buildZipIndex(
                $zipFile->getRealPath()
            );

            $uuid = (string) Str::uuid();
            $directory = 'swafi/importaciones/' . $uuid;

            $storedCsv = $this->storage->storeUploadedFile(
                $layoutFile,
                $directory,
                'layout.' . $layoutFormat
            );

            $storedZip = $this->storage->storeUploadedFile(
                $zipFile,
                $directory,
                'documentos.zip'
            );

            $headerIndexes = array_flip($normalizedHeaders);
            $headersToRead = array_merge(
                self::REQUIRED_HEADERS,
                self::OPTIONAL_HEADERS
            );

            $seenKeys = [];
            $rowPayloads = [];
            $summary = [
                'total' => 0,
                'aceptadas' => 0,
                'observadas' => 0,
                'rechazadas' => 0,
            ];

            foreach ($layout['records'] as $record) {
                $lineNumber = $record['numero_fila'];
                $columns = $record['columns'];
                $data = [];

                foreach ($headersToRead as $header) {
                    $data[$header] = isset($headerIndexes[$header])
                        ? $this->normalizeCell(
                            $columns[$headerIndexes[$header]] ?? ''
                        )
                        : '';
                }

                if ($this->isEmptyCsvRow($data)) {
                    continue;
                }

                if ($record['columnas_recibidas'] !== $record['columnas_esperadas']) {
                    $data['_estructura_errores'] = [
                        'La fila contiene '
                        . $record['columnas_recibidas']
                        . ' columnas y el encabezado define '
                        . $record['columnas_esperadas']
                        . '. Revisa el número de celdas, separadores y campos entrecomillados.',
                    ];
                }

                $summary['total']++;

                $validation = $this->validateRow(
                    data: $data,
                    zipIndex: $zipIndex,
                    lineNumber: $lineNumber,
                    seenKeys: $seenKeys
                );

                $summary[$validation['estatus'] . 's']++;

                $rowPayloads[] = [
                    'numero_fila' => $lineNumber,
                    'estatus' => $validation['estatus'],
                    'accion' => $validation['accion'],
                    'datos' => $validation['datos'],
                    'errores' => $validation['errores'],
                    'advertencias' => $validation['advertencias'],
                ];
            }

            if ($summary['total'] === 0) {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'El layout no contiene filas con información.',
                ]);
            }

            $batch = DB::transaction(function () use (
                $uuid,
                $userId,
                $layoutFile,
                $layoutFormat,
                $zipFile,
                $storedCsv,
                $storedZip,
                $summary,
                $rowPayloads
            ): ImportacionMasiva {
                $batch = ImportacionMasiva::create([
                    'uuid' => $uuid,
                    'user_id' => $userId,
                    'estado' => 'previsualizada',
                    'csv_nombre_original' => basename($layoutFile->getClientOriginalName()),
                    'csv_storage_disk' => $storedCsv['disk'],
                    'csv_ruta' => $storedCsv['path'],
                    'csv_hash_sha256' => $storedCsv['hash_sha256'],
                    'layout_formato' => $layoutFormat,
                    'zip_nombre_original' => basename($zipFile->getClientOriginalName()),
                    'zip_storage_disk' => $storedZip['disk'],
                    'zip_ruta' => $storedZip['path'],
                    'zip_hash_sha256' => $storedZip['hash_sha256'],
                    'total_filas' => $summary['total'],
                    'filas_aceptadas' => $summary['aceptadas'],
                    'filas_observadas' => $summary['observadas'],
                    'filas_rechazadas' => $summary['rechazadas'],
                    'resumen' => $summary,
                    'expira_at' => now()->addHours(24),
                ]);

                foreach (array_chunk($rowPayloads, 300) as $chunk) {
                    $now = now();
                    $insertRows = array_map(
                        static fn (array $row): array => [
                            'importacion_id' => $batch->id,
                            'numero_fila' => $row['numero_fila'],
                            'estatus' => $row['estatus'],
                            'accion' => $row['accion'],
                            'datos' => json_encode($row['datos'], JSON_UNESCAPED_UNICODE),
                            'errores' => $row['errores']
                                ? json_encode($row['errores'], JSON_UNESCAPED_UNICODE)
                                : null,
                            'advertencias' => $row['advertencias']
                                ? json_encode($row['advertencias'], JSON_UNESCAPED_UNICODE)
                                : null,
                            'aplicada' => false,
                            'resultado' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $chunk
                    );

                    ImportacionMasivaFila::insert($insertRows);
                }

                $this->registrarBitacora(
                    userId: $userId,
                    accion: 'IMPORTACION_PREVISUALIZADA',
                    registroClave: $batch->uuid,
                    antes: null,
                    despues: [
                        'archivos' => [
                            'layout' => $batch->csv_nombre_original,
                            'layout_formato' => $batch->layout_formato,
                            'zip' => $batch->zip_nombre_original,
                        ],
                        'resumen' => $summary,
                        'expira_at' => $batch->expira_at?->toIso8601String(),
                    ]
                );

                return $batch;
            });

            return $batch->fresh();
        } catch (\Throwable $exception) {
            if ($storedCsv) {
                $this->storage->delete($storedCsv['disk'], $storedCsv['path']);
            }

            if ($storedZip) {
                $this->storage->delete($storedZip['disk'], $storedZip['path']);
            }

            throw $exception;
        } finally {
            $this->deleteDirectoryIfExists($zipTempDirectory);
        }
    }

    /**
     * @return array{procesados:int,insertados:int,actualizados:int,rechazados:int}
     */
    public function aplicar(ImportacionMasiva $batch, ?int $userId): array
    {
        if (!$batch->estaVigente()) {
            throw ValidationException::withMessages([
                'lote' => 'La previsualización ya fue aplicada, cancelada o expiró. Genera una nueva.',
            ]);
        }

        if ($batch->filas_aceptadas < 1) {
            throw ValidationException::withMessages([
                'lote' => 'El lote no contiene filas aceptadas para aplicar.',
            ]);
        }

        $tempRoot = storage_path(
            'app/tmp/swafi_apply_' . (string) Str::uuid()
        );
        File::ensureDirectoryExists($tempRoot);

        $zipLocal = null;
        $zipTempDirectory = null;
        $persistentFiles = [];

        try {
            foreach ([
                [
                    'disk' => $batch->csv_storage_disk,
                    'path' => $batch->csv_ruta,
                    'hash' => $batch->csv_hash_sha256,
                    'label' => 'CSV',
                ],
                [
                    'disk' => $batch->zip_storage_disk,
                    'path' => $batch->zip_ruta,
                    'hash' => $batch->zip_hash_sha256,
                    'label' => 'ZIP',
                ],
            ] as $sourceFile) {
                if (!$this->storage->verifyHash(
                    $sourceFile['disk'],
                    $sourceFile['path'],
                    $sourceFile['hash']
                )) {
                    throw ValidationException::withMessages([
                        'lote' => "El archivo {$sourceFile['label']} del lote no superó la verificación de integridad SHA-256.",
                    ]);
                }
            }

            $zipLocal = $this->storage->copyToTemporaryFile(
                $batch->zip_storage_disk,
                $batch->zip_ruta,
                $tempRoot,
                'documentos.zip'
            );

            [$zipIndex, $zipTempDirectory] = $this->buildZipIndex($zipLocal);

            $acceptedRows = $batch->filas()
                ->where('estatus', 'aceptada')
                ->where('aplicada', false)
                ->orderBy('numero_fila')
                ->get();

            $seenKeys = [];
            $validatedRows = [];

            foreach ($acceptedRows as $row) {
                $validation = $this->validateRow(
                    data: $row->datos,
                    zipIndex: $zipIndex,
                    lineNumber: $row->numero_fila,
                    seenKeys: $seenKeys
                );

                if ($validation['estatus'] !== 'aceptada') {
                    $row->update([
                        'estatus' => $validation['estatus'],
                        'accion' => $validation['accion'],
                        'datos' => $validation['datos'],
                        'errores' => $validation['errores'],
                        'advertencias' => $validation['advertencias'],
                    ]);

                    $this->refreshBatchCounters($batch);

                    throw ValidationException::withMessages([
                        'lote' => "La fila {$row->numero_fila} dejó de cumplir las reglas vigentes. Revisa la previsualización antes de aplicar.",
                    ]);
                }

                $validatedRows[] = [
                    'model' => $row,
                    'validation' => $validation,
                ];
            }

            $summary = [
                'procesados' => count($validatedRows),
                'insertados' => 0,
                'actualizados' => 0,
                'rechazados' => $batch->filas_rechazadas + $batch->filas_observadas,
            ];

            DB::beginTransaction();

            try {
                foreach ($validatedRows as $item) {
                    /** @var ImportacionMasivaFila $row */
                    $row = $item['model'];
                    $result = $this->applyValidatedRow(
                        data: $item['validation']['datos'],
                        zipIndex: $zipIndex,
                        userId: $userId,
                        persistentFiles: $persistentFiles
                    );

                    $summary[$result['accion'] === 'insertar'
                        ? 'insertados'
                        : 'actualizados']++;

                    $row->update([
                        'aplicada' => true,
                        'resultado' => $result,
                    ]);
                }

                $batch->update([
                    'estado' => 'aplicada',
                    'filas_insertadas' => $summary['insertados'],
                    'filas_actualizadas' => $summary['actualizados'],
                    'aplicada_at' => now(),
                    'resumen' => array_merge(
                        $batch->resumen ?? [],
                        [
                            'aplicacion' => $summary,
                            'reversion' => [
                                'disponible' => false,
                                'estado' => 'consolidando',
                            ],
                        ]
                    ),
                ]);

                $this->registrarBitacora(
                    userId: $userId,
                    accion: 'IMPORTACION_LOTE_APLICADA',
                    registroClave: $batch->uuid,
                    antes: [
                        'estado' => 'previsualizada',
                    ],
                    despues: [
                        'estado' => 'aplicada',
                        'resumen' => $summary,
                    ]
                );

                DB::commit();
            } catch (\Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                foreach ($persistentFiles as $storedFile) {
                    $this->storage->delete(
                        $storedFile['disk'],
                        $storedFile['path']
                    );
                }

                throw $exception;
            }

            $freshBatch = $batch->fresh();

            if ($freshBatch) {
                $this->finalizeRollbackSnapshots(
                    batch: $freshBatch,
                    rows: collect($validatedRows)
                        ->map(fn (array $item) => $item['model']->fresh())
                        ->filter()
                );
            }

            return $summary;
        } finally {
            $this->deleteDirectoryIfExists($zipTempDirectory);
            $this->deleteDirectoryIfExists($tempRoot);
        }
    }

    public function cancelar(ImportacionMasiva $batch, ?int $userId): void
    {
        if ($batch->estado !== 'previsualizada') {
            throw ValidationException::withMessages([
                'lote' => 'Solo se puede cancelar un lote que aún no se ha aplicado.',
            ]);
        }

        DB::transaction(function () use ($batch, $userId): void {
            $batch->update([
                'estado' => 'cancelada',
                'cancelada_at' => now(),
            ]);

            $this->registrarBitacora(
                userId: $userId,
                accion: 'IMPORTACION_LOTE_CANCELADA',
                registroClave: $batch->uuid,
                antes: ['estado' => 'previsualizada'],
                despues: ['estado' => 'cancelada'],
            );
        });

        $this->storage->delete($batch->csv_storage_disk, $batch->csv_ruta);
        $this->storage->delete($batch->zip_storage_disk, $batch->zip_ruta);
    }

    public function registrarExportacionIncidencias(
        ImportacionMasiva $batch,
        ?int $userId,
        string $format,
        int $rowCount
    ): void {
        $format = Str::lower(trim($format));

        if (!in_array($format, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('El formato de exportación de incidencias no es válido.');
        }

        $this->registrarBitacora(
            userId: $userId,
            accion: $format === 'xlsx'
                ? 'IMPORTACION_INCIDENCIAS_XLSX'
                : 'IMPORTACION_INCIDENCIAS_CSV',
            registroClave: $batch->uuid,
            antes: null,
            despues: [
                'formato' => Str::upper($format),
                'filas_exportadas' => max(0, $rowCount),
                'estado_lote' => $batch->estado,
            ]
        );
    }

    /**
     * @return array{
     *     estatus:string,
     *     accion:?string,
     *     datos:array,
     *     errores:array,
     *     advertencias:array
     * }
     */
    private function validateRow(
        array $data,
        array $zipIndex,
        int $lineNumber,
        array &$seenKeys
    ): array {
        $structureErrors = is_array($data['_estructura_errores'] ?? null)
            ? array_values($data['_estructura_errores'])
            : [];
        unset($data['_estructura_errores']);

        $data = $this->normalizeRowData($data);
        $errors = $structureErrors;
        $warnings = [];
        $action = null;

        foreach (self::REQUIRED_HEADERS as $required) {
            if ($data[$required] === '') {
                $errors[] = "El campo {$required} es obligatorio.";
            }
        }

        if ($data['numero_activo'] !== '' && !preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,49}$/i', $data['numero_activo'])) {
            $errors[] = 'El número de activo contiene caracteres no permitidos o una longitud inválida.';
        }

        if ($data['descripcion'] !== '' && mb_strlen($data['descripcion']) > 255) {
            $errors[] = 'La descripción no debe superar 255 caracteres.';
        }

        if ($data['folio_factura'] !== '' && mb_strlen($data['folio_factura']) > 100) {
            $errors[] = 'El folio de factura no debe superar 100 caracteres.';
        }

        if ($data['proveedor_rfc'] !== '' && !preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $data['proveedor_rfc'])) {
            $errors[] = 'El RFC del proveedor no tiene un formato válido de 12 o 13 caracteres.';
        }

        if ($data['uuid_cfdi'] !== '' && !preg_match('/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/i', $data['uuid_cfdi'])) {
            $errors[] = 'El UUID CFDI no tiene el formato 8-4-4-4-12 esperado.';
        }

        $fechaFactura = $this->parseDateStrict($data['fecha_factura']);

        if (!$fechaFactura) {
            $errors[] = 'La fecha de factura no tiene un formato o día calendario válido.';
        } elseif (Carbon::parse($fechaFactura)->isFuture()) {
            $errors[] = 'La fecha de factura no puede ser futura.';
        }

        $fechaAdquisicion = null;

        if ($data['fecha_adquisicion'] !== '') {
            $fechaAdquisicion = $this->parseDateStrict($data['fecha_adquisicion']);

            if (!$fechaAdquisicion) {
                $errors[] = 'La fecha de adquisición no tiene un formato o día calendario válido.';
            } elseif (Carbon::parse($fechaAdquisicion)->isFuture()) {
                $errors[] = 'La fecha de adquisición no puede ser futura.';
            }
        }

        $montoFactura = $this->toDecimal($data['monto_factura']);

        if ($montoFactura === null || $montoFactura <= 0) {
            $errors[] = 'El monto de factura debe ser numérico y mayor que cero.';
        } elseif ($montoFactura > 999999999999.99) {
            $errors[] = 'El monto de factura supera el límite permitido.';
        }

        $moneda = $this->normalizeMoneda($data['moneda']);

        if (!$moneda) {
            $errors[] = 'La moneda no existe o se encuentra inactiva en el catálogo financiero.';
        }

        $estatusOperativo = $this->normalizeEstatusOperativo($data['estatus_operativo']);

        if ($data['estatus_operativo'] !== '' && $estatusOperativo === null) {
            $errors[] = 'El estatus operativo no existe, está inactivo o no coincide con la clave técnica del catálogo.';
        }

        $proveedorId = $this->activeCatalogId('proveedores', 'rfc', $data['proveedor_rfc']);
        $tipoActivoId = $this->activeCatalogId('tipos_activo', 'clave', $data['tipo_activo_clave']);
        $centroCostoId = $this->activeCatalogId('centros_costo', 'clave', $data['centro_costo_clave']);
        $plantaId = $this->activeCatalogId('plantas', 'clave', $data['planta_clave']);

        if (!$proveedorId && $data['proveedor_rfc'] !== '') {
            $errors[] = "El RFC de proveedor {$data['proveedor_rfc']} no existe o está inactivo.";
        }

        if (!$tipoActivoId && $data['tipo_activo_clave'] !== '') {
            $errors[] = "El tipo de activo {$data['tipo_activo_clave']} no existe o está inactivo.";
        }

        if (!$centroCostoId && $data['centro_costo_clave'] !== '') {
            $errors[] = "El centro de costo {$data['centro_costo_clave']} no existe o está inactivo.";
        }

        if (!$plantaId && $data['planta_clave'] !== '') {
            $errors[] = "La planta {$data['planta_clave']} no existe o está inactiva.";
        }

        $ubicacionId = null;

        if ($data['ubicacion_codigo'] !== '') {
            $ubicacion = DB::table('ubicaciones')
                ->where('codigo_interno', $data['ubicacion_codigo'])
                ->where('estatus', 'activo')
                ->first(['id', 'planta_id']);

            if (!$ubicacion) {
                $errors[] = "La ubicación {$data['ubicacion_codigo']} no existe o está inactiva.";
            } else {
                $ubicacionId = (int) $ubicacion->id;

                if ($plantaId && (int) $ubicacion->planta_id !== $plantaId) {
                    $errors[] = 'La ubicación indicada no pertenece a la planta capturada.';
                }
            }
        } else {
            $warnings[] = 'La fila no incluye ubicación física; podrá completarse posteriormente.';
        }

        $responsableId = null;

        if ($data['responsable_correo'] !== '') {
            $responsableId = DB::table('responsables')
                ->where('correo', $data['responsable_correo'])
                ->where('estatus', 'activo')
                ->value('id');

            if (!$responsableId) {
                $errors[] = "El responsable {$data['responsable_correo']} no existe o está inactivo.";
            }
        } else {
            $warnings[] = 'La fila no incluye responsable asignado.';
        }

        $key = Str::lower($data['numero_activo'] . '|' . $data['folio_factura']);

        if ($data['numero_activo'] !== '' && $data['folio_factura'] !== '') {
            if (isset($seenKeys[$key])) {
                $warnings[] = "La combinación activo/folio está repetida en la fila {$seenKeys[$key]} del mismo layout.";
            } else {
                $seenKeys[$key] = $lineNumber;
            }
        }

        if ($data['uuid_cfdi'] === '') {
            $warnings[] = 'La fila no incluye UUID CFDI.';
        }

        $existing = null;

        if ($data['numero_activo'] !== '' && $data['folio_factura'] !== '') {
            $existing = Expediente::withTrashed()
                ->where('numero_activo', $data['numero_activo'])
                ->where('folio_factura', $data['folio_factura'])
                ->first();

            if ($existing?->trashed()) {
                if ($this->isRollbackArchivedExpediente($existing)) {
                    $action = 'restaurar';
                    $warnings[] = 'El expediente fue archivado por una reversión controlada previa y será restaurado al aplicar el lote.';
                } else {
                    $errors[] = 'El expediente existe con baja lógica y requiere restauración autorizada antes de importarlo.';
                }
            } else {
                $action = $existing ? 'actualizar' : 'insertar';
            }
        }

        $documents = $this->resolveDocumentsFromZip(
            zipIndex: $zipIndex,
            pdfName: $data['documento_pdf'],
            xmlName: $data['documento_xml']
        );

        $errors = array_merge($errors, $documents['errores']);

        if ($data['documento_pdf'] === '') {
            $warnings[] = 'No se indicó PDF; el expediente podrá quedar incompleto.';
        }

        if ($data['documento_xml'] === '') {
            $warnings[] = 'No se indicó XML; el expediente podrá quedar incompleto.';
        }

        $data['_resolved'] = [
            'proveedor_id' => $proveedorId,
            'tipo_activo_id' => $tipoActivoId,
            'centro_costo_id' => $centroCostoId,
            'planta_id' => $plantaId,
            'ubicacion_id' => $ubicacionId,
            'responsable_id' => $responsableId ? (int) $responsableId : null,
            'fecha_factura' => $fechaFactura,
            'fecha_adquisicion' => $fechaAdquisicion,
            'monto_factura' => $montoFactura,
            'moneda' => $moneda,
            'estatus_operativo' => $estatusOperativo,
        ];

        if ($errors) {
            $status = 'rechazada';
            $action = null;
        } elseif (str_contains(implode(' ', $warnings), 'repetida en la fila')) {
            $status = 'observada';
            $action = null;
        } else {
            $status = 'aceptada';
        }

        return [
            'estatus' => $status,
            'accion' => $action,
            'datos' => $data,
            'errores' => array_values(array_unique($errors)),
            'advertencias' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return array{
     *     accion:string,
     *     numero_activo:string,
     *     expediente_id:int,
     *     estatus_documental:string,
     *     documentos_guardados:array,
     *     rollback:array
     * }
     */
    private function applyValidatedRow(
        array $data,
        array $zipIndex,
        ?int $userId,
        array &$persistentFiles
    ): array {
        $resolved = $data['_resolved'];
        $numeroActivo = $data['numero_activo'];
        $folioFactura = $data['folio_factura'];

        $existing = Expediente::withTrashed()
            ->where('numero_activo', $numeroActivo)
            ->where('folio_factura', $folioFactura)
            ->lockForUpdate()
            ->first();

        if ($existing?->trashed() && !$this->isRollbackArchivedExpediente($existing)) {
            throw new RuntimeException(
                "El expediente {$numeroActivo}/{$folioFactura} fue dado de baja antes de aplicar el lote."
            );
        }

        $activoAntes = $this->snapshotActivo($numeroActivo);
        $expedienteAntes = $existing
            ? $this->snapshotExpediente((int) $existing->id)
            : null;
        $valorAntes = $this->snapshotValorActivo($numeroActivo);
        $restoredFromRollback = $existing?->trashed() === true;

        if ($restoredFromRollback) {
            $existing->restore();
            $existing->forceFill([
                'deleted_by' => null,
                'delete_reason' => null,
            ])->save();
        }

        $activo = Activo::updateOrCreate(
            ['numero_activo' => $numeroActivo],
            [
                'tipo_activo_id' => $resolved['tipo_activo_id'],
                'proveedor_id' => $resolved['proveedor_id'],
                'centro_costo_id' => $resolved['centro_costo_id'],
                'planta_id' => $resolved['planta_id'],
                'ubicacion_id' => $data['ubicacion_codigo'] !== ''
                    ? $resolved['ubicacion_id']
                    : data_get($activoAntes, 'ubicacion_id'),
                'responsable_id' => $data['responsable_correo'] !== ''
                    ? $resolved['responsable_id']
                    : data_get($activoAntes, 'responsable_id'),
                'descripcion' => $data['descripcion'],
                'serie' => $data['serie'] !== ''
                    ? $data['serie']
                    : data_get($activoAntes, 'serie'),
                'marca' => $data['marca'] !== ''
                    ? $data['marca']
                    : data_get($activoAntes, 'marca'),
                'modelo' => $data['modelo'] !== ''
                    ? $data['modelo']
                    : data_get($activoAntes, 'modelo'),
                'fecha_adquisicion' => $data['fecha_adquisicion'] !== ''
                    ? $resolved['fecha_adquisicion']
                    : data_get($activoAntes, 'fecha_adquisicion'),
                'estatus_operativo' => $resolved['estatus_operativo']
                    ?? data_get($activoAntes, 'estatus_operativo')
                    ?? 'en_operacion',
                'estatus_documental' => 'incompleto',
                'activo' => true,
                'creado_por' => data_get($activoAntes, 'creado_por') ?: $userId,
                'actualizado_por' => $userId,
            ]
        );

        $expediente = Expediente::updateOrCreate(
            [
                'numero_activo' => $numeroActivo,
                'folio_factura' => $folioFactura,
            ],
            [
                'uuid_cfdi' => $data['uuid_cfdi'] !== ''
                    ? $data['uuid_cfdi']
                    : $existing?->uuid_cfdi,
                'fecha_factura' => $resolved['fecha_factura'],
                'monto_factura' => $resolved['monto_factura'],
                'moneda' => $resolved['moneda'],
                'estatus' => 'incompleto',
                'observaciones' => $data['observaciones'] !== ''
                    ? $data['observaciones']
                    : $existing?->observaciones,
                'creado_por' => $existing?->creado_por ?: $userId,
                'actualizado_por' => $userId,
                'deleted_by' => null,
                'delete_reason' => null,
            ]
        );

        $pendingDocuments = $this->resolveDocumentsFromZip(
            zipIndex: $zipIndex,
            pdfName: $data['documento_pdf'],
            xmlName: $data['documento_xml']
        );

        if ($pendingDocuments['errores']) {
            throw new RuntimeException(
                implode(' ', $pendingDocuments['errores'])
            );
        }

        $savedDocuments = $this->storeDocumentsForExpediente(
            expediente: $expediente,
            documents: $pendingDocuments['documentos'],
            numeroActivo: $numeroActivo,
            folioFactura: $folioFactura,
            userId: $userId,
            persistentFiles: $persistentFiles
        );

        $documentStatus = $this->resolveDocumentStatusFromDatabase($expediente->id);

        $expediente->update([
            'estatus' => $documentStatus,
            'actualizado_por' => $userId,
        ]);

        $activo->update([
            'estatus_documental' => $documentStatus,
            'actualizado_por' => $userId,
        ]);

        $action = $restoredFromRollback
            ? 'restaurar'
            : ($expediente->wasRecentlyCreated ? 'insertar' : 'actualizar');

        $auditAction = match ($action) {
            'insertar' => 'IMPORTACION_EXPEDIENTE_ALTA',
            'restaurar' => 'IMPORTACION_EXP_RESTAURADA',
            default => 'IMPORTACION_EXPEDIENTE_ACTUALIZACION',
        };

        $this->registrarBitacoraDetalle(
            userId: $userId,
            numeroActivo: $numeroActivo,
            accion: $auditAction,
            registroClave: (string) $expediente->id,
            antes: [
                'activo' => $activoAntes,
                'expediente' => $expedienteAntes,
                'valor' => $valorAntes,
            ],
            despues: [
                'activo' => $this->snapshotActivo($numeroActivo),
                'expediente' => $this->snapshotExpediente((int) $expediente->id),
                'documentos_guardados' => $savedDocuments,
            ]
        );

        return [
            'accion' => $action,
            'numero_activo' => $numeroActivo,
            'expediente_id' => (int) $expediente->id,
            'estatus_documental' => $documentStatus,
            'documentos_guardados' => $savedDocuments,
            'rollback' => [
                'version' => 1,
                'ready' => false,
                'before' => [
                    'activo' => $activoAntes,
                    'expediente' => $expedienteAntes,
                    'valor' => $valorAntes,
                ],
                'after' => null,
                'documents' => $savedDocuments,
            ],
        ];
    }

    private function validateHeaders(array $headers): void
    {
        $emptyHeaders = array_filter(
            $headers,
            static fn (string $header): bool => $header === ''
        );

        if ($emptyHeaders) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El layout contiene encabezados vacíos o no reconocibles.',
            ]);
        }

        $duplicates = array_keys(
            array_filter(
                array_count_values($headers),
                static fn (int $count): bool => $count > 1
            )
        );

        if ($duplicates) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El layout contiene encabezados duplicados: ' . implode(', ', $duplicates) . '.',
            ]);
        }

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missing) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo no contiene los encabezados requeridos: ' . implode(', ', $missing) . '.',
            ]);
        }
    }

    private function normalizeRowData(array $data): array
    {
        foreach (array_merge(self::REQUIRED_HEADERS, self::OPTIONAL_HEADERS) as $header) {
            $data[$header] = $this->normalizeCell($data[$header] ?? '');
        }

        $data['numero_activo'] = Str::upper($data['numero_activo']);
        $data['uuid_cfdi'] = Str::upper($data['uuid_cfdi']);
        $data['proveedor_rfc'] = Str::upper($data['proveedor_rfc']);
        $data['tipo_activo_clave'] = Str::upper($data['tipo_activo_clave']);
        $data['centro_costo_clave'] = Str::upper($data['centro_costo_clave']);
        $data['planta_clave'] = Str::upper($data['planta_clave']);
        $data['ubicacion_codigo'] = Str::upper($data['ubicacion_codigo']);
        $data['responsable_correo'] = Str::lower($data['responsable_correo']);

        return $data;
    }

    private function activeCatalogId(string $table, string $column, string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $id = DB::table($table)
            ->where($column, $value)
            ->where('estatus', 'activo')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @return array{documentos:array,errores:array}
     */
    private function resolveDocumentsFromZip(
        array $zipIndex,
        ?string $pdfName,
        ?string $xmlName
    ): array {
        $documents = [];
        $errors = [];

        foreach ([
            'PDF' => $this->splitDocumentNames($pdfName),
            'XML' => $this->splitDocumentNames($xmlName),
        ] as $type => $fileNames) {
            $expectedExtension = Str::lower($type);

            foreach ($fileNames as $fileName) {
                $key = $this->normalizeZipKey($fileName);
                $file = $zipIndex[$key] ?? null;

                if (!$file) {
                    $errors[] = "El {$type} {$fileName} no existe dentro del ZIP.";
                    continue;
                }

                if ($file['extension'] !== $expectedExtension) {
                    $errors[] = "El archivo {$fileName} no tiene extensión {$type}.";
                    continue;
                }

                $documents[] = [
                    'tipo_documento' => $type,
                    'nombre_archivo' => basename($fileName),
                    'source_path' => $file['temp_path'],
                    'mime_type' => $type === 'PDF'
                        ? 'application/pdf'
                        : 'application/xml',
                    'tamano_bytes' => $file['size'],
                    'hash_sha256' => $file['hash_sha256'],
                ];
            }
        }

        return [
            'documentos' => $documents,
            'errores' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return array{0:array,1:string}
     */
    private function buildZipIndex(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP de PHP no está disponible en el servidor.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);

        if ($openResult !== true) {
            throw new RuntimeException('El archivo ZIP no pudo abrirse o está dañado.');
        }

        if ($zip->numFiles > self::MAX_ZIP_FILES) {
            $zip->close();
            throw new RuntimeException('El ZIP contiene demasiados archivos.');
        }

        $tempDirectory = storage_path(
            'app/tmp/swafi_import_' . (string) Str::uuid()
        );
        File::ensureDirectoryExists($tempDirectory);

        $index = [];
        $totalUncompressed = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                $stat = $zip->statIndex($i);

                if (!$entryName || str_ends_with($entryName, '/')) {
                    continue;
                }

                if (str_contains($entryName, '../') || str_starts_with($entryName, '/')) {
                    throw new RuntimeException('El ZIP contiene una ruta no segura.');
                }

                $size = (int) ($stat['size'] ?? 0);
                $totalUncompressed += $size;

                if ($totalUncompressed > self::MAX_ZIP_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('El contenido descomprimido del ZIP supera 200 MB.');
                }

                $baseName = basename(str_replace('\\', '/', $entryName));
                $extension = Str::lower(pathinfo($baseName, PATHINFO_EXTENSION));

                if (!in_array($extension, ['pdf', 'xml'], true)) {
                    continue;
                }

                $key = $this->normalizeZipKey($baseName);

                if (isset($index[$key])) {
                    throw new RuntimeException("El ZIP contiene nombres de archivo duplicados: {$baseName}.");
                }

                $stream = $zip->getStream($entryName);

                if (!is_resource($stream)) {
                    throw new RuntimeException("No fue posible leer {$baseName} dentro del ZIP.");
                }

                $safeName = $this->safeFileName($baseName, $extension);
                $tempFile = $tempDirectory
                    . DIRECTORY_SEPARATOR
                    . Str::random(10)
                    . '_'
                    . $safeName;
                $destination = fopen($tempFile, 'wb');

                if ($destination === false) {
                    fclose($stream);
                    throw new RuntimeException('No fue posible crear un archivo temporal para validar el ZIP.');
                }

                try {
                    $copied = stream_copy_to_stream($stream, $destination);
                } finally {
                    fclose($stream);
                    fclose($destination);
                }

                if ($copied === false || !is_file($tempFile)) {
                    throw new RuntimeException("No fue posible extraer {$baseName} de forma segura.");
                }

                $this->validateExtractedDocumentContent(
                    path: $tempFile,
                    extension: $extension,
                    originalName: $baseName
                );

                $hash = hash_file('sha256', $tempFile);
                $fileSize = filesize($tempFile);

                if ($hash === false || $fileSize === false) {
                    throw new RuntimeException("No fue posible validar la integridad de {$baseName}.");
                }

                $index[$key] = [
                    'original_name' => $baseName,
                    'temp_path' => $tempFile,
                    'extension' => $extension,
                    'size' => (int) $fileSize,
                    'hash_sha256' => $hash,
                ];
            }
        } catch (\Throwable $exception) {
            $this->deleteDirectoryIfExists($tempDirectory);
            throw $exception;
        } finally {
            $zip->close();
        }

        return [$index, $tempDirectory];
    }

    private function validateExtractedDocumentContent(
        string $path,
        string $extension,
        string $originalName
    ): void {
        if ($extension === 'pdf') {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                throw new RuntimeException("No fue posible validar el PDF {$originalName}.");
            }

            try {
                $prefix = fread($handle, 1024);
            } finally {
                fclose($handle);
            }

            if (!is_string($prefix) || !str_contains($prefix, '%PDF-')) {
                throw new RuntimeException(
                    "El archivo {$originalName} tiene extensión PDF, pero su contenido no corresponde a un PDF válido."
                );
            }

            return;
        }

        if (!class_exists(\DOMDocument::class)) {
            throw new RuntimeException(
                'La extensión DOM de PHP no está disponible para validar documentos XML.'
            );
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || trim($contents) === '') {
            throw new RuntimeException("El XML {$originalName} está vacío o no puede leerse.");
        }

        $upperContents = Str::upper($contents);

        if (
            str_contains($upperContents, '<!DOCTYPE')
            || str_contains($upperContents, '<!ENTITY')
        ) {
            throw new RuntimeException(
                "El XML {$originalName} contiene declaraciones externas no permitidas."
            );
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML(
                $contents,
                LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT
            );
            $hasErrors = libxml_get_errors() !== [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded || $hasErrors || !$document->documentElement) {
            throw new RuntimeException(
                "El archivo {$originalName} tiene extensión XML, pero su estructura no es válida."
            );
        }
    }

    private function storeDocumentsForExpediente(
        Expediente $expediente,
        array $documents,
        string $numeroActivo,
        string $folioFactura,
        ?int $userId,
        array &$persistentFiles
    ): array {
        $saved = [];

        foreach ($documents as $document) {
            $stored = $this->storeDocumentFile(
                sourcePath: $document['source_path'],
                numeroActivo: $numeroActivo,
                folioFactura: $folioFactura,
                originalName: $document['nombre_archivo'],
                mimeType: $document['mime_type']
            );

            $persistentFiles[] = $stored;

            $saved[] = $this->storeOrReplaceDocumentRecord(
                expediente: $expediente,
                tipoDocumento: $document['tipo_documento'],
                nombreArchivo: $document['nombre_archivo'],
                stored: $stored,
                userId: $userId
            );
        }

        return $saved;
    }

    private function storeDocumentFile(
        string $sourcePath,
        string $numeroActivo,
        string $folioFactura,
        string $originalName,
        ?string $mimeType
    ): array {
        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = $this->safeFileName($originalName, $extension ?: 'dat');
        $basePath = 'swafi/expedientes/'
            . Str::slug($numeroActivo)
            . '/'
            . Str::slug($folioFactura);
        $storedName = now()->format('YmdHis')
            . '_'
            . Str::random(8)
            . '_'
            . $safeName;

        return $this->storage->storeLocalFile(
            sourcePath: $sourcePath,
            targetPath: $basePath . '/' . $storedName,
            mimeType: $mimeType
        );
    }

    private function storeOrReplaceDocumentRecord(
        Expediente $expediente,
        string $tipoDocumento,
        string $nombreArchivo,
        array $stored,
        ?int $userId
    ): array {
        $normalizedName = Str::lower(trim(basename($nombreArchivo)));
        $hash = $stored['hash_sha256'];

        $matchingQuery = DocumentoExpediente::where(
            'expediente_id',
            $expediente->id
        )
            ->where('tipo_documento', $tipoDocumento)
            ->where(function ($query) use ($normalizedName, $hash): void {
                $query->whereRaw('LOWER(nombre_archivo) = ?', [$normalizedName])
                    ->orWhere('hash_sha256', $hash);
            });

        $matchingDocuments = (clone $matchingQuery)
            ->orderByDesc('version')
            ->lockForUpdate()
            ->get();

        $previousSnapshots = $matchingDocuments
            ->sortBy('id')
            ->map(fn (DocumentoExpediente $document): array => $this->documentSnapshotFromModel(
                $document
            ))
            ->values()
            ->all();

        $existing = $matchingDocuments->first();

        if ($existing) {
            DocumentoExpediente::where('expediente_id', $expediente->id)
                ->where('tipo_documento', $tipoDocumento)
                ->where(function ($query) use ($normalizedName, $hash): void {
                    $query->whereRaw('LOWER(nombre_archivo) = ?', [$normalizedName])
                        ->orWhere('hash_sha256', $hash);
                })
                ->update([
                    'vigente' => false,
                    'updated_at' => now(),
                ]);

            $version = ((int) $existing->version) + 1;
            $action = 'reemplazado';
        } else {
            $version = 1;
            $action = 'agregado';
        }

        $document = DocumentoExpediente::create([
            'expediente_id' => $expediente->id,
            'tipo_documento' => $tipoDocumento,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $stored['path'],
            'storage_disk' => $stored['disk'],
            'mime_type' => $stored['mime_type'],
            'tamano_bytes' => $stored['tamano_bytes'],
            'hash_sha256' => $stored['hash_sha256'],
            'version' => $version,
            'vigente' => true,
            'cargado_por' => $userId,
        ]);

        return [
            'accion' => $action,
            'documento_id' => $document->id,
            'tipo_documento' => $document->tipo_documento,
            'nombre_archivo' => $document->nombre_archivo,
            'version' => $document->version,
            'hash_sha256' => $document->hash_sha256,
            'storage_disk' => $document->storage_disk,
            'ruta_archivo' => $document->ruta_archivo,
            'created' => $this->documentSnapshotFromModel($document),
            'previous' => $previousSnapshots,
        ];
    }

    private function resolveDocumentStatusFromDatabase(int $expedienteId): string
    {
        $types = DB::table('documentos_expediente')
            ->where('expediente_id', $expedienteId)
            ->where('vigente', true)
            ->pluck('tipo_documento')
            ->map(fn ($type): string => Str::upper((string) $type))
            ->unique()
            ->all();

        return in_array('PDF', $types, true) && in_array('XML', $types, true)
            ? 'completo'
            : 'incompleto';
    }

    private function refreshBatchCounters(ImportacionMasiva $batch): void
    {
        $counts = $batch->filas()
            ->select('estatus', DB::raw('COUNT(*) AS total'))
            ->groupBy('estatus')
            ->pluck('total', 'estatus');

        $batch->update([
            'filas_aceptadas' => (int) ($counts['aceptada'] ?? 0),
            'filas_observadas' => (int) ($counts['observada'] ?? 0),
            'filas_rechazadas' => (int) ($counts['rechazada'] ?? 0),
        ]);
    }

    private function splitDocumentNames(?string $value): array
    {
        $value = $this->normalizeCell($value);

        if ($value === '') {
            return [];
        }

        $files = [];
        $seen = [];

        foreach (preg_split('/\s*\|\s*/', $value) ?: [] as $part) {
            $fileName = trim((string) $part);

            if ($fileName === '') {
                continue;
            }

            if ($fileName !== basename(str_replace('\\', '/', $fileName))) {
                $fileName = basename(str_replace('\\', '/', $fileName));
            }

            $key = $this->normalizeZipKey($fileName);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $files[] = $fileName;
            }
        }

        return $files;
    }

    /**
     * Lee el layout de datos conservando una estructura común para CSV y XLSX.
     *
     * @return array{
     *     headers:array<int,string>,
     *     records:array<int,array{
     *         numero_fila:int,
     *         columns:array<int,mixed>,
     *         columnas_esperadas:int,
     *         columnas_recibidas:int
     *     }>
     * }
     */
    private function readLayoutRecords(UploadedFile $layoutFile): array
    {
        if ($this->layoutFormat($layoutFile) !== 'xlsx') {
            return $this->readCsvRecords($layoutFile);
        }

        $path = $layoutFile->getRealPath();

        if (!is_string($path) || $path === '' || !is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo XLSX no está disponible para lectura.',
            ]);
        }

        try {
            $rows = $this->xlsxReader->readFirstWorksheet($path, self::MAX_ROWS);
        } catch (\DomainException|\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'archivo_csv' => $exception->getMessage(),
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo XLSX no contiene encabezados ni registros.',
            ]);
        }

        $headers = array_shift($rows);

        if (!is_array($headers) || $headers === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo XLSX no contiene encabezados.',
            ]);
        }

        $normalizedHeaders = array_map(
            fn (mixed $header): string => $this->normalizeHeader((string) $header),
            $headers
        );
        $records = [];

        foreach (array_values($rows) as $index => $columns) {
            if (!is_array($columns) || $this->rowValuesAreEmpty($columns)) {
                continue;
            }

            if (count($records) >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'El layout supera el máximo de ' . self::MAX_ROWS . ' filas por lote.',
                ]);
            }

            $expectedColumns = count($normalizedHeaders);
            $receivedColumns = count($columns);
            $normalizedColumns = array_values($columns);

            if ($receivedColumns < $expectedColumns) {
                $normalizedColumns = array_pad(
                    $normalizedColumns,
                    $expectedColumns,
                    ''
                );
                $receivedColumns = $expectedColumns;
            }

            $records[] = [
                'numero_fila' => $index + 2,
                'columns' => $normalizedColumns,
                'columnas_esperadas' => $expectedColumns,
                'columnas_recibidas' => $receivedColumns,
            ];
        }

        if ($records === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo XLSX no contiene registros para previsualizar.',
            ]);
        }

        return [
            'headers' => $normalizedHeaders,
            'records' => $records,
        ];
    }

    private function layoutFormat(UploadedFile $layoutFile): string
    {
        return mb_strtolower((string) $layoutFile->getClientOriginalExtension()) === 'xlsx'
            ? 'xlsx'
            : 'csv';
    }

    /**
     * @param array<int, mixed> $values
     */
    private function rowValuesAreEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->normalizeCell($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Lee el CSV conforme a RFC 4180, incluyendo campos entrecomillados con
     * comas o saltos de línea, y conserva una numeración estable por registro.
     *
     * @return array{
     *     headers:array<int,string>,
     *     records:array<int,array{
     *         numero_fila:int,
     *         columns:array<int,mixed>,
     *         columnas_esperadas:int,
     *         columnas_recibidas:int
     *     }>
     * }
     */
    private function readCsvRecords(UploadedFile $csvFile): array
    {
        $path = $csvFile->getRealPath();

        if (!is_string($path) || $path === '' || !is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo CSV no está disponible para lectura.',
            ]);
        }

        $handle = fopen($path, 'rb');

        if (!is_resource($handle)) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'No fue posible abrir el archivo CSV.',
            ]);
        }

        try {
            $firstLine = fgets($handle);

            if (!is_string($firstLine) || trim($firstLine) === '') {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'El archivo CSV no contiene encabezados.',
                ]);
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter, '"', '');

            if (!is_array($headers) || $headers === []) {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'No fue posible interpretar los encabezados del CSV.',
                ]);
            }

            $normalizedHeaders = array_map(
                fn (mixed $header): string => $this->normalizeHeader((string) $header),
                $headers
            );

            $records = [];
            $recordNumber = 1;

            while (($columns = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $recordNumber++;

                if (!is_array($columns)) {
                    continue;
                }

                $hasValue = false;

                foreach ($columns as $value) {
                    if ($this->normalizeCell($value) !== '') {
                        $hasValue = true;
                        break;
                    }
                }

                if (!$hasValue) {
                    continue;
                }

                if (count($records) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'archivo_csv' => 'El layout supera el máximo de ' . self::MAX_ROWS . ' filas por lote.',
                    ]);
                }

                $records[] = [
                    'numero_fila' => $recordNumber,
                    'columns' => array_values($columns),
                    'columnas_esperadas' => count($normalizedHeaders),
                    'columnas_recibidas' => count($columns),
                ];
            }

            if ($records === []) {
                throw ValidationException::withMessages([
                    'archivo_csv' => 'El archivo no contiene registros para previsualizar.',
                ]);
            }

            return [
                'headers' => $normalizedHeaders,
                'records' => $records,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($candidates);

        return (string) (array_key_first($candidates) ?: ',');
    }

    private function normalizeHeader(?string $value): string
    {
        $value = Str::lower(Str::ascii($this->normalizeCell($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function normalizeCell(mixed $value): string
    {
        return trim(str_replace("\xEF\xBB\xBF", '', (string) $value));
    }

    private function normalizeZipKey(string $value): string
    {
        return Str::lower(
            basename(str_replace('\\', '/', $this->normalizeCell($value)))
        );
    }

    private function isEmptyCsvRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($this->normalizeCell($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function toDecimal(?string $value): ?float
    {
        $value = str_replace(['$', ' ', "\u{00A0}"], '', $this->normalizeCell($value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $last = end($parts);
            $value = strlen((string) $last) <= 2
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseDateStrict(?string $value): ?string
    {
        $value = $this->normalizeCell($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $value) === 1) {
            $serial = (float) $value;

            if ($serial >= 1 && $serial <= 2958465) {
                return (new \DateTimeImmutable('1899-12-30'))
                    ->modify('+' . (int) floor($serial) . ' days')
                    ->format('Y-m-d');
            }
        }

        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y', '!m/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $date instanceof \DateTimeImmutable
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format(ltrim($format, '!')) === $value
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeMoneda(?string $value): ?string
    {
        $value = Str::upper($this->normalizeCell($value));

        if ($value === '') {
            return null;
        }

        if ($this->activeCurrencyCodes === null) {
            $this->activeCurrencyCodes = DB::table('monedas')
                ->where('estatus', 'activo')
                ->orderBy('clave')
                ->pluck('clave')
                ->map(fn (mixed $code): string => Str::upper(trim((string) $code)))
                ->values()
                ->all();
        }

        return in_array($value, $this->activeCurrencyCodes, true)
            ? $value
            : null;
    }

    private function finalizeRollbackSnapshots(
        ImportacionMasiva $batch,
        \Illuminate\Support\Collection $rows
    ): void {
        try {
            DB::transaction(function () use ($batch, $rows): void {
                /** @var ImportacionMasiva|null $lockedBatch */
                $lockedBatch = ImportacionMasiva::query()
                    ->whereKey($batch->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedBatch) {
                    throw new RuntimeException(
                        'El lote aplicado no está disponible para consolidar su instantánea de reversión.'
                    );
                }

                $assetNumbers = $rows
                    ->filter(fn (mixed $candidate): bool => $candidate instanceof ImportacionMasivaFila)
                    ->map(function (ImportacionMasivaFila $candidate): string {
                        $result = is_array($candidate->resultado)
                            ? $candidate->resultado
                            : [];

                        return trim((string) ($result['numero_activo'] ?? ''));
                    })
                    ->filter()
                    ->values();

                if ($assetNumbers->count() !== $assetNumbers->unique()->count()) {
                    $summary = $lockedBatch->resumen ?? [];
                    $summary['reversion'] = [
                        'disponible' => false,
                        'estado' => 'no_disponible',
                        'motivo' => 'El lote contiene más de un expediente para el mismo activo. La reversión automática se bloqueó para evitar restaurar estados intermedios ambiguos.',
                    ];

                    $lockedBatch->update([
                        'reversion_disponible_hasta' => null,
                        'resumen' => $summary,
                    ]);

                    return;
                }

                foreach ($rows as $candidate) {
                    if (!$candidate instanceof ImportacionMasivaFila) {
                        continue;
                    }

                    /** @var ImportacionMasivaFila|null $row */
                    $row = ImportacionMasivaFila::query()
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$row) {
                        throw new RuntimeException(
                            "No fue posible consolidar la instantánea de la fila {$candidate->numero_fila}."
                        );
                    }

                    $result = is_array($row->resultado) ? $row->resultado : [];
                    $numeroActivo = (string) ($result['numero_activo'] ?? '');
                    $expedienteId = (int) ($result['expediente_id'] ?? 0);

                    if ($numeroActivo === '' || $expedienteId <= 0) {
                        throw new RuntimeException(
                            "No fue posible consolidar la instantánea de la fila {$row->numero_fila}."
                        );
                    }

                    $result['rollback']['after'] = [
                        'activo' => $this->snapshotActivo($numeroActivo),
                        'expediente' => $this->snapshotExpediente($expedienteId),
                        'valor' => $this->snapshotValorActivo($numeroActivo),
                    ];
                    $result['rollback']['ready'] = true;
                    $result['rollback']['captured_at'] = now()->toIso8601String();

                    $row->update(['resultado' => $result]);
                }

                $hours = max(
                    1,
                    (int) config('swafi.importaciones.reversion_horas', 24)
                );
                $deadline = ($lockedBatch->aplicada_at ?: now())
                    ->copy()
                    ->addHours($hours);
                $summary = $lockedBatch->resumen ?? [];
                $summary['reversion'] = [
                    'disponible' => true,
                    'estado' => 'lista',
                    'version' => 1,
                    'horas' => $hours,
                    'disponible_hasta' => $deadline->toIso8601String(),
                ];

                $lockedBatch->update([
                    'reversion_disponible_hasta' => $deadline,
                    'resumen' => $summary,
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'services_registromasivoservice_exception_1'
            );

            $freshBatch = $batch->fresh();

            if (!$freshBatch) {
                return;
            }

            $summary = $freshBatch->resumen ?? [];
            $summary['reversion'] = [
                'disponible' => false,
                'estado' => 'no_disponible',
                'motivo' => 'No fue posible consolidar la instantánea posterior al lote.',
            ];

            $freshBatch->update([
                'reversion_disponible_hasta' => null,
                'resumen' => $summary,
            ]);
        }
    }

    private function isRollbackArchivedExpediente(Expediente $expediente): bool
    {
        return $expediente->trashed()
            && str_starts_with(
                trim((string) $expediente->delete_reason),
                '[IMPORT_ROLLBACK]'
            );
    }

    private function snapshotActivo(string $numeroActivo): ?array
    {
        $row = DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->lockForUpdate()
            ->first([
                'numero_activo',
                'tipo_activo_id',
                'proveedor_id',
                'centro_costo_id',
                'planta_id',
                'ubicacion_id',
                'responsable_id',
                'descripcion',
                'serie',
                'marca',
                'modelo',
                'fecha_adquisicion',
                'estatus_operativo',
                'estatus_documental',
                'activo',
                'creado_por',
                'actualizado_por',
                'created_at',
                'updated_at',
            ]);

        return $row ? (array) $row : null;
    }

    private function snapshotExpediente(int $expedienteId): ?array
    {
        $row = DB::table('expedientes')
            ->where('id', $expedienteId)
            ->lockForUpdate()
            ->first([
                'id',
                'numero_activo',
                'folio_factura',
                'uuid_cfdi',
                'fecha_factura',
                'monto_factura',
                'moneda',
                'estatus',
                'observaciones',
                'creado_por',
                'actualizado_por',
                'deleted_at',
                'deleted_by',
                'delete_reason',
                'created_at',
                'updated_at',
            ]);

        return $row ? (array) $row : null;
    }

    private function snapshotValorActivo(string $numeroActivo): ?array
    {
        $row = DB::table('valores_activo')
            ->where('numero_activo', $numeroActivo)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first([
                'id',
                'numero_activo',
                'valor_fiscal',
                'valor_financiero',
                'moneda',
                'tipo_cambio',
                'fecha_tipo_cambio',
                'origen_tipo_cambio',
                'depreciacion_acumulada',
                'valor_en_libros',
                'vida_util_meses',
                'estatus_contable',
                'motivo_cambio',
                'cfdi_validacion_id',
                'conciliacion_cfdi',
                'conciliacion_detalle',
                'fecha_corte',
                'registrado_por',
                'deleted_at',
                'deleted_by',
                'delete_reason',
                'created_at',
                'updated_at',
            ]);

        return $row ? (array) $row : null;
    }

    private function documentSnapshotFromModel(
        DocumentoExpediente $document
    ): array {
        $fields = [
            'id',
            'expediente_id',
            'tipo_documento',
            'nombre_archivo',
            'ruta_archivo',
            'storage_disk',
            'mime_type',
            'tamano_bytes',
            'hash_sha256',
            'version',
            'vigente',
            'cargado_por',
            'created_at',
            'updated_at',
        ];

        return array_intersect_key(
            $document->getAttributes(),
            array_flip($fields)
        );
    }

    private function normalizeEstatusOperativo(?string $value): ?string
    {
        $value = $this->normalizeHeader($value);

        if ($value === '') {
            return null;
        }

        return $this->statusCatalogs->normalizeOperationalInput($value);
    }

    private function safeFileName(string $name, string $defaultExtension): string
    {
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION))
            ?: $defaultExtension;

        return (Str::slug($baseName) ?: 'documento') . '.' . $extension;
    }

    private function deleteDirectoryIfExists(?string $directory): void
    {
        if ($directory && File::exists($directory)) {
            File::deleteDirectory($directory);
        }
    }

    private function registrarBitacora(
        ?int $userId,
        string $accion,
        string $registroClave,
        ?array $antes,
        ?array $despues
    ): void {
        $this->registrarBitacoraDetalle(
            userId: $userId,
            numeroActivo: null,
            accion: $accion,
            registroClave: $registroClave,
            antes: $antes,
            despues: $despues,
            tablaAfectada: 'importaciones_masivas'
        );
    }

    private function registrarBitacoraDetalle(
        ?int $userId,
        ?string $numeroActivo,
        string $accion,
        string $registroClave,
        ?array $antes,
        ?array $despues,
        string $tablaAfectada = 'expedientes'
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => $userId,
            'modulo' => 'M01 Gestión de expedientes de activo fijo',
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_clave' => $registroClave,
            'antes' => $antes
                ? json_encode($antes, JSON_UNESCAPED_UNICODE)
                : null,
            'despues' => $despues
                ? json_encode($despues, JSON_UNESCAPED_UNICODE)
                : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

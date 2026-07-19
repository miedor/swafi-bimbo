<?php

namespace App\Services;

use App\Models\ImportacionCatalogo;
use App\Models\ImportacionCatalogoFila;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CatalogImportService
{
    public const VALID_STATUSES = ['aceptada', 'observada'];

    public function __construct(
        private readonly CatalogManagementService $catalogManagement,
        private readonly CatalogValidationService $catalogValidation,
        private readonly SimpleXlsxReader $xlsxReader
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function headersFor(string $catalog): array
    {
        return match ($catalog) {
            'proveedores' => ['rfc', 'nombre', 'correo', 'telefono', 'estatus'],
            'plantas' => ['clave', 'nombre', 'direccion', 'estado', 'pais', 'estatus'],
            'centros_costo' => ['planta_clave', 'clave', 'descripcion', 'estatus'],
            'categorias_activo' => ['clave', 'nombre', 'descripcion', 'estatus'],
            'tipos_activo' => ['categoria_clave', 'clave', 'descripcion', 'vida_util_meses', 'estatus'],
            'estatus_documentales', 'estatus_operativos' => ['clave', 'nombre', 'descripcion', 'orden', 'estatus'],
            'areas' => ['planta_clave', 'clave', 'nombre', 'estatus'],
            'ubicaciones' => [
                'planta_clave',
                'area_nombre',
                'codigo_interno',
                'edificio',
                'piso',
                'pasillo',
                'descripcion',
                'estatus',
            ],
            'responsables' => ['nombre', 'correo', 'telefono', 'estatus'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public function requiredHeadersFor(string $catalog): array
    {
        return match ($catalog) {
            'proveedores' => ['rfc', 'nombre'],
            'plantas' => ['clave', 'nombre', 'direccion'],
            'centros_costo' => ['planta_clave', 'clave', 'descripcion'],
            'categorias_activo' => ['clave', 'nombre'],
            'tipos_activo' => ['categoria_clave', 'clave', 'descripcion'],
            'estatus_documentales', 'estatus_operativos' => ['clave', 'nombre', 'orden'],
            'areas' => ['planta_clave', 'clave', 'nombre'],
            'ubicaciones' => ['planta_clave', 'codigo_interno'],
            'responsables' => ['nombre'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public function exampleRowFor(string $catalog): array
    {
        return match ($catalog) {
            'proveedores' => [
                'ACM010101ABC',
                'Proveedor industrial del centro',
                'contacto@proveedor.com',
                '5555555555',
                'activo',
            ],
            'plantas' => [
                'PLT-SM',
                'Planta Santa María',
                'Calle Industrial 100, Colonia Centro',
                'Ciudad de México',
                'México',
                'activo',
            ],
            'centros_costo' => ['PLT-SM', 'CC-PLA-200', 'Producción línea 2', 'activo'],
            'categorias_activo' => ['ME', 'Maquinaria y equipo', 'Bienes productivos e instalaciones técnicas', 'activo'],
            'tipos_activo' => ['ME', 'EQP', 'Equipo de producción', '120', 'activo'],
            'estatus_documentales' => [
                'pendiente_revision',
                'Pendiente de revisión',
                'Expediente enviado a revisión documental especializada',
                '100',
                'activo',
            ],
            'estatus_operativos' => [
                'en_mantenimiento',
                'En mantenimiento',
                'Activo temporalmente fuera de operación por mantenimiento',
                '100',
                'activo',
            ],
            'areas' => ['PLT-SM', 'PROD', 'Producción', 'activo'],
            'ubicaciones' => [
                'PLT-SM',
                'Producción',
                'UBI-SM-PRO-L3-PB',
                'Edificio B',
                'PB',
                'Línea 3',
                'Producción línea 3 planta baja',
                'activo',
            ],
            'responsables' => ['Jorge Méndez', 'jorge.mendez@bimbo.local', '5555555555', 'activo'],
            default => [],
        };
    }

    public function preview(
        UploadedFile $file,
        string $catalog,
        ?int $userId,
        ?string $ip
    ): ImportacionCatalogo {
        $this->assertCatalogExists($catalog);

        $rows = $this->readRows($file);

        if (count($rows) < 2) {
            throw new DomainException('El layout no contiene registros para previsualizar.');
        }

        $rawHeaders = array_shift($rows) ?? [];
        $normalizedHeaders = array_map(
            fn (mixed $header): string => $this->normalizeHeader($header),
            $rawHeaders
        );

        $this->assertHeaders($catalog, $normalizedHeaders);

        $maxRows = $this->maxRows();

        if (count($rows) > $maxRows) {
            throw new DomainException(
                "El layout contiene más de {$maxRows} registros. Divide la carga en archivos más pequeños."
            );
        }

        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        $hash = hash_file('sha256', $file->getRealPath());

        if (!is_string($hash) || strlen($hash) !== 64) {
            throw new DomainException('No fue posible calcular la huella de integridad del archivo cargado.');
        }

        return DB::transaction(function () use (
            $file,
            $catalog,
            $userId,
            $ip,
            $rows,
            $normalizedHeaders,
            $extension,
            $hash
        ): ImportacionCatalogo {
            $batch = ImportacionCatalogo::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'catalogo' => $catalog,
                'estado' => 'previsualizada',
                'archivo_nombre_original' => $this->safeOriginalName($file->getClientOriginalName()),
                'archivo_extension' => $extension,
                'archivo_hash_sha256' => $hash,
                'expira_at' => now()->addHours($this->previewHours()),
            ]);

            $headerIndexes = [];

            foreach ($normalizedHeaders as $index => $header) {
                if ($header !== '' && !array_key_exists($header, $headerIndexes)) {
                    $headerIndexes[$header] = $index;
                }
            }

            $expectedHeaders = $this->headersFor($catalog);
            $summary = [
                'procesados' => 0,
                'aceptados' => 0,
                'observados' => 0,
                'rechazados' => 0,
                'insertar' => 0,
                'actualizar' => 0,
            ];
            $identities = [];

            foreach ($rows as $index => $columns) {
                $lineNumber = $index + 2;
                $sourceData = [];

                foreach ($expectedHeaders as $header) {
                    $sourceData[$header] = array_key_exists($header, $headerIndexes)
                        ? $this->normalizeCell($columns[$headerIndexes[$header]] ?? '')
                        : '';
                }

                if ($this->isEmptyRow($sourceData)) {
                    continue;
                }

                $summary['procesados']++;
                $classification = $this->classifyRow($catalog, $sourceData, $identities);

                ImportacionCatalogoFila::query()->create([
                    'importacion_id' => $batch->id,
                    'numero_fila' => $lineNumber,
                    'estatus' => $classification['estatus'],
                    'accion' => $classification['accion'],
                    'registro_id' => $classification['registro_id'],
                    'datos' => $classification['datos'],
                    'errores' => $classification['errores'],
                    'advertencias' => $classification['advertencias'],
                    'aplicada' => false,
                ]);

                match ($classification['estatus']) {
                    'aceptada' => $summary['aceptados']++,
                    'observada' => $summary['observados']++,
                    default => $summary['rechazados']++,
                };

                if (in_array($classification['estatus'], self::VALID_STATUSES, true)) {
                    if ($classification['accion'] === 'actualizar') {
                        $summary['actualizar']++;
                    } else {
                        $summary['insertar']++;
                    }
                }
            }

            if ($summary['procesados'] === 0) {
                throw new DomainException('El layout no contiene filas con información para previsualizar.');
            }

            $batch->forceFill([
                'total_filas' => $summary['procesados'],
                'filas_aceptadas' => $summary['aceptados'],
                'filas_observadas' => $summary['observados'],
                'filas_rechazadas' => $summary['rechazados'],
                'resumen' => [
                    'insertar' => $summary['insertar'],
                    'actualizar' => $summary['actualizar'],
                    'encabezados' => $normalizedHeaders,
                ],
            ])->save();

            $this->audit(
                action: 'IMPORTACION_CATALOGO_PREVIA',
                batch: $batch,
                userId: $userId,
                ip: $ip,
                before: null,
                after: [
                    'catalogo' => $catalog,
                    'archivo' => $batch->archivo_nombre_original,
                    'hash_sha256' => $batch->archivo_hash_sha256,
                    'resumen' => $summary,
                ]
            );

            return $batch->fresh(['usuario:id,name,email']) ?? $batch;
        }, 3);
    }

    /**
     * @return array<string, int>
     */
    public function apply(
        ImportacionCatalogo $batch,
        int $userId,
        ?string $ip
    ): array {
        return DB::transaction(function () use ($batch, $userId, $ip): array {
            /** @var ImportacionCatalogo|null $locked */
            $locked = ImportacionCatalogo::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || (int) $locked->user_id !== $userId) {
                throw new DomainException('La previsualización seleccionada no está disponible para tu usuario.');
            }

            $this->markExpiredIfNeeded($locked);

            if ($locked->estado !== 'previsualizada') {
                throw new DomainException('La previsualización ya fue aplicada, cancelada o venció.');
            }

            $rows = $locked->filas()
                ->whereIn('estatus', self::VALID_STATUSES)
                ->where('aplicada', false)
                ->orderBy('numero_fila')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw new DomainException('El lote no contiene filas válidas para aplicar.');
            }

            $inserted = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $data = is_array($row->datos) ? $row->datos : [];
                $current = $this->findExistingRecord($locked->catalogo, $data, true);
                $currentId = $current !== null ? (int) $current->id : null;
                $expectedId = $row->registro_id !== null ? (int) $row->registro_id : null;
                $expectedAction = (string) $row->accion;

                if (
                    ($expectedAction === 'insertar' && $current !== null)
                    || ($expectedAction === 'actualizar' && ($current === null || $currentId !== $expectedId))
                ) {
                    throw new DomainException(
                        'El catálogo cambió después de la previsualización. Cancela el lote y vuelve a validar el archivo.'
                    );
                }

                $this->assertDataIsStillValid($locked->catalogo, $data, $current);

                $saved = $this->catalogManagement->save(
                    catalog: $locked->catalogo,
                    data: $data,
                    recordId: $currentId,
                    userId: $userId,
                    ip: $ip
                );

                $row->forceFill([
                    'aplicada' => true,
                    'resultado' => [
                        'registro_id' => (int) $saved->id,
                        'accion' => $expectedAction,
                    ],
                ])->save();

                if ($expectedAction === 'actualizar') {
                    $updated++;
                } else {
                    $inserted++;
                }
            }

            $before = [
                'estado' => $locked->estado,
                'filas_insertadas' => (int) $locked->filas_insertadas,
                'filas_actualizadas' => (int) $locked->filas_actualizadas,
            ];

            $locked->forceFill([
                'estado' => 'aplicada',
                'filas_insertadas' => $inserted,
                'filas_actualizadas' => $updated,
                'aplicada_at' => now(),
            ])->save();

            $summary = [
                'insertados' => $inserted,
                'actualizados' => $updated,
                'aplicados' => $inserted + $updated,
                'rechazados' => (int) $locked->filas_rechazadas,
            ];

            $this->audit(
                action: 'IMPORTACION_CATALOGO_APLICADA',
                batch: $locked,
                userId: $userId,
                ip: $ip,
                before: $before,
                after: array_merge(['estado' => 'aplicada'], $summary)
            );

            return $summary;
        }, 3);
    }

    public function cancel(ImportacionCatalogo $batch, int $userId, ?string $ip): void
    {
        DB::transaction(function () use ($batch, $userId, $ip): void {
            /** @var ImportacionCatalogo|null $locked */
            $locked = ImportacionCatalogo::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || (int) $locked->user_id !== $userId) {
                throw new DomainException('La previsualización seleccionada no está disponible para tu usuario.');
            }

            $this->markExpiredIfNeeded($locked);

            if ($locked->estado !== 'previsualizada') {
                throw new DomainException('Solo es posible cancelar una previsualización pendiente.');
            }

            $locked->forceFill([
                'estado' => 'cancelada',
                'cancelada_at' => now(),
            ])->save();

            $this->audit(
                action: 'IMPORTACION_CATALOGO_CANCELADA',
                batch: $locked,
                userId: $userId,
                ip: $ip,
                before: ['estado' => 'previsualizada'],
                after: ['estado' => 'cancelada']
            );
        }, 3);
    }

    public function findOwnedBatch(string $uuid, int $userId): ImportacionCatalogo
    {
        /** @var ImportacionCatalogo|null $batch */
        $batch = ImportacionCatalogo::query()
            ->with(['usuario:id,name,email'])
            ->where('uuid', $uuid)
            ->where('user_id', $userId)
            ->first();

        if ($batch === null) {
            throw new DomainException('La previsualización solicitada no existe o no pertenece a tu usuario.');
        }

        $this->markExpiredIfNeeded($batch);

        return $batch->fresh(['usuario:id,name,email']) ?? $batch;
    }

    public function incidentRows(ImportacionCatalogo $batch): Collection
    {
        return $batch->filas()
            ->whereIn('estatus', ['observada', 'rechazada'])
            ->orderBy('numero_fila')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function incidentHeaders(): array
    {
        return [
            'Fila',
            'Estatus',
            'Acción propuesta',
            'Identificador',
            'Errores',
            'Advertencias',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function incidentDataRows(Collection $rows, string $catalog): array
    {
        return $rows->map(function (ImportacionCatalogoFila $row) use ($catalog): array {
            $data = is_array($row->datos) ? $row->datos : [];

            return [
                (int) $row->numero_fila,
                ucfirst((string) $row->estatus),
                $row->accion ? ucfirst((string) $row->accion) : 'No aplicable',
                $this->identityLabel($catalog, $data),
                implode(' | ', $this->normalizeMessages($row->errores)),
                implode(' | ', $this->normalizeMessages($row->advertencias)),
            ];
        })->all();
    }

    public function registerIncidentExport(
        ImportacionCatalogo $batch,
        int $userId,
        ?string $ip,
        string $format,
        int $rowCount
    ): void {
        $this->audit(
            action: 'EXPORTA_INCIDENCIAS_CATALOGO',
            batch: $batch,
            userId: $userId,
            ip: $ip,
            before: null,
            after: [
                'formato' => $format,
                'filas' => $rowCount,
            ]
        );
    }

    /**
     * @param array<string, bool> $identities
     * @return array{
     *     estatus: string,
     *     accion: ?string,
     *     registro_id: ?int,
     *     datos: array<string, mixed>,
     *     errores: array<int, string>,
     *     advertencias: array<int, string>
     * }
     */
    private function classifyRow(string $catalog, array $sourceData, array &$identities): array
    {
        $errors = [];
        $warnings = [];
        $data = $this->prepareRow($catalog, $sourceData, $errors);

        if ($errors !== []) {
            return $this->rejectedClassification($sourceData, $errors);
        }

        $existing = $this->findExistingRecord($catalog, $data, false);
        $recordId = $existing !== null ? (int) $existing->id : null;
        $identity = $this->identityKey($catalog, $data);

        if ($identity === '') {
            return $this->rejectedClassification($data, ['No fue posible determinar el identificador único de la fila.']);
        }

        if (isset($identities[$identity])) {
            return $this->rejectedClassification(
                $data,
                ['El mismo identificador aparece más de una vez dentro del layout. Conserva una sola fila por registro.']
            );
        }

        $identities[$identity] = true;

        $validator = $this->catalogValidation->makeValidator($data, $catalog, $recordId);

        if ($validator->fails()) {
            return $this->rejectedClassification($data, $validator->errors()->all());
        }

        if ($catalog === 'ubicaciones' && !empty($data['area_id'])) {
            $belongs = DB::table('areas')
                ->where('id', (int) $data['area_id'])
                ->where('planta_id', (int) $data['planta_id'])
                ->where('estatus', 'activo')
                ->exists();

            if (!$belongs) {
                return $this->rejectedClassification(
                    $data,
                    ['El área indicada no pertenece a la planta seleccionada o está inactiva.']
                );
            }
        }

        if ($existing !== null) {
            try {
                $this->catalogManagement->assertUpdateAllowed($catalog, $existing, $data);

                if (
                    (string) ($existing->estatus ?? 'activo') === 'activo'
                    && (string) ($data['estatus'] ?? 'activo') === 'inactivo'
                ) {
                    $this->catalogManagement->assertCatalogCanBeDeactivated($catalog, (int) $existing->id);
                    $warnings[] = 'La fila solicita desactivar un registro existente. Revisa sus dependencias antes de aplicar.';
                }
            } catch (DomainException $exception) {
                return $this->rejectedClassification($data, [$exception->getMessage()]);
            }

            $warnings[] = 'El registro ya existe y será actualizado si confirmas la aplicación del lote.';
        }

        return [
            'estatus' => $warnings === [] ? 'aceptada' : 'observada',
            'accion' => $existing === null ? 'insertar' : 'actualizar',
            'registro_id' => $recordId,
            'datos' => $data,
            'errores' => [],
            'advertencias' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return array{
     *     estatus: string,
     *     accion: null,
     *     registro_id: null,
     *     datos: array<string, mixed>,
     *     errores: array<int, string>,
     *     advertencias: array<int, string>
     * }
     */
    private function rejectedClassification(array $data, array $errors): array
    {
        return [
            'estatus' => 'rechazada',
            'accion' => null,
            'registro_id' => null,
            'datos' => $data,
            'errores' => array_values(array_unique(array_filter(
                array_map(static fn (mixed $error): string => trim((string) $error), $errors),
                static fn (string $error): bool => $error !== ''
            ))),
            'advertencias' => [],
        ];
    }

    /**
     * @param array<int, string> $errors
     * @return array<string, mixed>
     */
    private function prepareRow(string $catalog, array $data, array &$errors): array
    {
        $status = $this->normalizeStatus($data['estatus'] ?? 'activo');

        if ($status === null) {
            $errors[] = 'El estatus debe ser activo o inactivo.';
            $status = 'activo';
        }

        return match ($catalog) {
            'proveedores' => $this->prepareProvider($data, $status),
            'plantas' => $this->preparePlant($data, $status),
            'centros_costo' => $this->prepareCostCenter($data, $status, $errors),
            'categorias_activo' => $this->prepareAssetCategory($data, $status),
            'tipos_activo' => $this->prepareAssetType($data, $status, $errors),
            'estatus_documentales', 'estatus_operativos' => $this->prepareAssetStatus($data, $status),
            'areas' => $this->prepareArea($data, $status, $errors),
            'ubicaciones' => $this->prepareLocation($data, $status, $errors),
            'responsables' => $this->prepareResponsible($data, $status),
            default => [],
        };
    }

    private function prepareProvider(array $data, string $status): array
    {
        return [
            'rfc' => mb_strtoupper($this->normalizeCell($data['rfc'] ?? '')),
            'nombre' => $this->nullableString($data['nombre'] ?? null),
            'correo' => $this->nullableString($data['correo'] ?? null),
            'telefono' => $this->nullableString($data['telefono'] ?? null),
            'estatus' => $status,
        ];
    }

    private function preparePlant(array $data, string $status): array
    {
        return [
            'clave' => mb_strtoupper($this->normalizeCell($data['clave'] ?? '')),
            'nombre' => $this->nullableString($data['nombre'] ?? null),
            'direccion' => $this->nullableString($data['direccion'] ?? null),
            'estado' => $this->nullableString($data['estado'] ?? null),
            'pais' => $this->nullableString($data['pais'] ?? null) ?? 'México',
            'estatus' => $status,
        ];
    }

    private function prepareCostCenter(array $data, string $status, array &$errors): array
    {
        $plantKey = mb_strtoupper($this->normalizeCell($data['planta_clave'] ?? ''));
        $plantId = $this->activeIdByKey('plantas', $plantKey);

        if ($plantId === null) {
            $errors[] = "La planta {$plantKey} no existe o está inactiva.";
        }

        return [
            'planta_id' => $plantId,
            'clave' => mb_strtoupper($this->normalizeCell($data['clave'] ?? '')),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null),
            'estatus' => $status,
        ];
    }

    private function prepareAssetCategory(array $data, string $status): array
    {
        return [
            'clave' => mb_strtoupper($this->normalizeCell($data['clave'] ?? '')),
            'nombre' => $this->nullableString($data['nombre'] ?? null),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null),
            'estatus' => $status,
        ];
    }

    private function prepareAssetType(array $data, string $status, array &$errors): array
    {
        $categoryKey = mb_strtoupper($this->normalizeCell($data['categoria_clave'] ?? ''));
        $categoryId = $this->activeIdByKey('categorias_activo', $categoryKey);

        if ($categoryId === null) {
            $errors[] = "La categoría {$categoryKey} no existe o está inactiva.";
        }

        return [
            'categoria_activo_id' => $categoryId,
            'clave' => mb_strtoupper($this->normalizeCell($data['clave'] ?? '')),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null),
            'vida_util_meses' => $this->nullablePositiveInteger($data['vida_util_meses'] ?? null),
            'estatus' => $status,
        ];
    }

    private function prepareAssetStatus(array $data, string $status): array
    {
        $key = mb_strtolower($this->normalizeCell($data['clave'] ?? ''));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';

        return [
            'clave' => trim($key, '_'),
            'nombre' => $this->nullableString($data['nombre'] ?? null),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null),
            'orden' => $this->nullablePositiveInteger($data['orden'] ?? null),
            'estatus' => $status,
        ];
    }

    private function prepareArea(array $data, string $status, array &$errors): array
    {
        $plantKey = mb_strtoupper($this->normalizeCell($data['planta_clave'] ?? ''));
        $plantId = $this->activeIdByKey('plantas', $plantKey);

        if ($plantId === null) {
            $errors[] = "La planta {$plantKey} no existe o está inactiva.";
        }

        return [
            'planta_id' => $plantId,
            'clave' => mb_strtoupper($this->normalizeCell($data['clave'] ?? '')),
            'nombre' => $this->nullableString($data['nombre'] ?? null),
            'estatus' => $status,
        ];
    }

    private function prepareLocation(array $data, string $status, array &$errors): array
    {
        $plantKey = mb_strtoupper($this->normalizeCell($data['planta_clave'] ?? ''));
        $plantId = $this->activeIdByKey('plantas', $plantKey);
        $areaName = $this->normalizeCell($data['area_nombre'] ?? '');
        $areaId = null;

        if ($plantId === null) {
            $errors[] = "La planta {$plantKey} no existe o está inactiva.";
        } elseif ($areaName !== '') {
            $areaId = DB::table('areas')
                ->where('planta_id', $plantId)
                ->where('nombre', $areaName)
                ->where('estatus', 'activo')
                ->value('id');

            if ($areaId === null) {
                $errors[] = "El área {$areaName} no existe o está inactiva para la planta {$plantKey}.";
            }
        }

        return [
            'planta_id' => $plantId,
            'area_id' => $areaId !== null ? (int) $areaId : null,
            'codigo_interno' => mb_strtoupper($this->normalizeCell($data['codigo_interno'] ?? '')),
            'edificio' => $this->nullableString($data['edificio'] ?? null),
            'piso' => $this->nullableString($data['piso'] ?? null),
            'pasillo' => $this->nullableString($data['pasillo'] ?? null),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null),
            'estatus' => $status,
        ];
    }

    private function prepareResponsible(array $data, string $status): array
    {
        return [
            'nombre' => $this->nullableString($data['nombre'] ?? null),
            'correo' => $this->nullableString($data['correo'] ?? null),
            'telefono' => $this->nullableString($data['telefono'] ?? null),
            'estatus' => $status,
        ];
    }

    private function assertDataIsStillValid(string $catalog, array $data, ?object $current): void
    {
        $recordId = $current !== null ? (int) $current->id : null;
        $validator = $this->catalogValidation->makeValidator($data, $catalog, $recordId);

        if ($validator->fails()) {
            throw new DomainException(
                'La fila dejó de ser válida después de la previsualización: '
                . implode(' ', $validator->errors()->all())
            );
        }

        if ($current !== null) {
            $this->catalogManagement->assertUpdateAllowed($catalog, $current, $data);

            if (
                (string) ($current->estatus ?? 'activo') === 'activo'
                && (string) ($data['estatus'] ?? 'activo') === 'inactivo'
            ) {
                $this->catalogManagement->assertCatalogCanBeDeactivated($catalog, (int) $current->id);
            }
        }
    }

    private function findExistingRecord(string $catalog, array $data, bool $lock): ?object
    {
        $query = match ($catalog) {
            'proveedores' => DB::table('proveedores')->where('rfc', $data['rfc'] ?? ''),
            'plantas' => DB::table('plantas')->where('clave', $data['clave'] ?? ''),
            'centros_costo' => DB::table('centros_costo')->where('clave', $data['clave'] ?? ''),
            'categorias_activo' => DB::table('categorias_activo')->where('clave', $data['clave'] ?? ''),
            'tipos_activo' => DB::table('tipos_activo')->where('clave', $data['clave'] ?? ''),
            'estatus_documentales' => DB::table('estatus_documentales')->where('clave', $data['clave'] ?? ''),
            'estatus_operativos' => DB::table('estatus_operativos')->where('clave', $data['clave'] ?? ''),
            'areas' => DB::table('areas')
                ->where('planta_id', $data['planta_id'] ?? 0)
                ->where(function (Builder $query) use ($data): void {
                    $query->where('clave', $data['clave'] ?? '')
                        ->orWhere(function (Builder $fallback) use ($data): void {
                            $fallback->whereNull('clave')
                                ->where('nombre', $data['nombre'] ?? '');
                        });
                }),
            'ubicaciones' => DB::table('ubicaciones')->where('codigo_interno', $data['codigo_interno'] ?? ''),
            'responsables' => $this->responsibleQuery($data),
            default => null,
        };

        if (!$query instanceof Builder) {
            return null;
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function responsibleQuery(array $data): Builder
    {
        $email = trim((string) ($data['correo'] ?? ''));

        if ($email !== '') {
            return DB::table('responsables')->where('correo', $email);
        }

        return DB::table('responsables')->where('nombre', $data['nombre'] ?? '');
    }

    private function identityKey(string $catalog, array $data): string
    {
        return match ($catalog) {
            'proveedores' => 'rfc:' . mb_strtoupper((string) ($data['rfc'] ?? '')),
            'plantas',
            'centros_costo',
            'categorias_activo',
            'tipos_activo',
            'estatus_documentales',
            'estatus_operativos' => 'clave:' . (string) ($data['clave'] ?? ''),
            'areas' => 'area:' . (string) ($data['planta_id'] ?? '') . ':' . (string) ($data['clave'] ?? ''),
            'ubicaciones' => 'ubicacion:' . (string) ($data['codigo_interno'] ?? ''),
            'responsables' => 'responsable:' . mb_strtolower((string) (($data['correo'] ?? '') ?: ($data['nombre'] ?? ''))),
            default => '',
        };
    }

    private function identityLabel(string $catalog, array $data): string
    {
        return match ($catalog) {
            'proveedores' => (string) ($data['rfc'] ?? '—'),
            'areas' => trim((string) ($data['clave'] ?? '') . ' / ' . (string) ($data['nombre'] ?? '')),
            'ubicaciones' => (string) ($data['codigo_interno'] ?? '—'),
            'responsables' => (string) (($data['correo'] ?? '') ?: ($data['nombre'] ?? '—')),
            default => (string) (($data['clave'] ?? '') ?: ($data['nombre'] ?? '—')),
        };
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->xlsxReader->readFirstWorksheet($file->getRealPath(), $this->maxRows() + 1);
        }

        return $this->readCsv($file->getRealPath(), $this->maxRows() + 1);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path, int $maxRows): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new DomainException('No fue posible leer el archivo CSV cargado.');
        }

        $handle = fopen($path, 'rb');

        if (!is_resource($handle)) {
            throw new DomainException('No fue posible abrir el archivo CSV cargado.');
        }

        try {
            $firstLine = fgets($handle);

            if (!is_string($firstLine)) {
                return [];
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            $rows = [];

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if (count($rows) > $maxRows) {
                    throw new DomainException(
                        "El layout supera el máximo permitido de {$maxRows} filas incluyendo encabezados."
                    );
                }

                $normalized = array_map(
                    fn (mixed $value): string => $this->normalizeCell($value),
                    $row
                );

                if ($this->rowValuesAreEmpty($normalized)) {
                    continue;
                }

                $rows[] = $normalized;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function assertHeaders(string $catalog, array $headers): void
    {
        $required = $this->requiredHeadersFor($catalog);
        $missing = array_values(array_diff($required, $headers));

        if ($missing !== []) {
            throw new DomainException(
                'El layout no contiene los encabezados requeridos: ' . implode(', ', $missing) . '.'
            );
        }

        $duplicates = array_keys(array_filter(array_count_values(array_filter($headers)), fn (int $count): bool => $count > 1));

        if ($duplicates !== []) {
            throw new DomainException(
                'El layout contiene encabezados duplicados: ' . implode(', ', $duplicates) . '.'
            );
        }
    }

    private function assertCatalogExists(string $catalog): void
    {
        if (!array_key_exists($catalog, CatalogManagementService::CATALOGS)) {
            throw new DomainException('El catálogo seleccionado no es válido.');
        }
    }

    private function markExpiredIfNeeded(ImportacionCatalogo $batch): void
    {
        if (
            $batch->estado === 'previsualizada'
            && $batch->expira_at !== null
            && $batch->expira_at->isPast()
        ) {
            $batch->forceFill(['estado' => 'expirada'])->save();
        }
    }

    private function activeIdByKey(string $table, string $key): ?int
    {
        if ($key === '') {
            return null;
        }

        $id = DB::table($table)
            ->where('clave', $key)
            ->where('estatus', 'activo')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t", '|'];
        $selected = ',';
        $maxColumns = 0;

        foreach ($candidates as $candidate) {
            $columns = count(str_getcsv($line, $candidate));

            if ($columns > $maxColumns) {
                $maxColumns = $columns;
                $selected = $candidate;
            }
        }

        return $selected;
    }

    private function normalizeHeader(mixed $value): string
    {
        $normalized = $this->normalizeCell($value);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $normalized = mb_strtolower($normalized);
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    private function normalizeCell(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $normalized = str_replace("\xC2\xA0", ' ', (string) $value);
        $normalized = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $normalized) ?? '';

        return trim($normalized);
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $normalized = $this->normalizeHeader($value === null || $value === '' ? 'activo' : $value);

        return match ($normalized) {
            'activo', 'activa', '1', 'si', 'yes' => 'activo',
            'inactivo', 'inactiva', '0', 'no' => 'inactivo',
            default => null,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = $this->normalizeCell($value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        $normalized = $this->normalizeCell($value);

        if ($normalized === '' || preg_match('/^\d+$/', $normalized) !== 1) {
            return null;
        }

        $integer = (int) $normalized;

        return $integer > 0 ? $integer : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeCell($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $row
     */
    private function rowValuesAreEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMessages(mixed $messages): array
    {
        if (is_array($messages)) {
            return array_values(array_filter(
                array_map(static fn (mixed $message): string => trim((string) $message), $messages),
                static fn (string $message): bool => $message !== ''
            ));
        }

        if (is_string($messages) && trim($messages) !== '') {
            $decoded = json_decode($messages, true);

            if (is_array($decoded)) {
                return $this->normalizeMessages($decoded);
            }

            return [trim($messages)];
        }

        return [];
    }

    private function safeOriginalName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^\pL\pN._ -]+/u', '_', $base) ?? 'layout_catalogos';
        $base = trim($base, " .\t\n\r\0\x0B");

        return mb_substr($base !== '' ? $base : 'layout_catalogos', 0, 255);
    }

    private function maxRows(): int
    {
        return min(20000, max(1, (int) config('swafi.catalogos.importacion_max_filas', 5000)));
    }

    private function previewHours(): int
    {
        return min(72, max(1, (int) config('swafi.catalogos.previsualizacion_horas', 24)));
    }

    private function audit(
        string $action,
        ImportacionCatalogo $batch,
        ?int $userId,
        ?string $ip,
        ?array $before,
        ?array $after
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $userId,
                'modulo' => 'M04 Administración y seguridad',
                'accion' => $action,
                'tabla_afectada' => 'importaciones_catalogo',
                'registro_clave' => $batch->uuid,
                'antes' => $before !== null
                    ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : null,
                'despues' => $after !== null
                    ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : null,
                'ip' => $ip,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'services_catalogimportservice_exception_1'
            );
        }
    }
}

<?php

namespace App\Services;

use App\Models\ImportacionMasiva;
use App\Models\ImportacionMasivaFila;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BulkImportRollbackService
{
    private const ROLLBACK_VERSION = 1;
    private const DELETE_REASON_PREFIX = '[IMPORT_ROLLBACK]';

    private const ACTIVO_FIELDS = [
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
    ];

    private const EXPEDIENTE_FIELDS = [
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
    ];

    private const VALOR_FIELDS = [
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
    ];

    private const DOCUMENT_FIELDS = [
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

    /**
     * Revierte un lote completo. Cualquier incompatibilidad o dependencia
     * posterior cancela toda la operación mediante rollback transaccional.
     *
     * @return array{filas_revertidas:int,expedientes_dados_baja:int,valores_dados_baja:int,activos_desactivados:int,documentos_inhabilitados:int}
     */
    public function revertir(
        ImportacionMasiva $batch,
        int $userId,
        string $reason
    ): array {
        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'lote' => 'La sesión no contiene un usuario válido para realizar la reversión.',
            ]);
        }

        $reason = trim($reason);

        return DB::transaction(function () use ($batch, $userId, $reason): array {
            /** @var ImportacionMasiva|null $lockedBatch */
            $lockedBatch = ImportacionMasiva::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedBatch) {
                throw ValidationException::withMessages([
                    'lote' => 'El lote solicitado ya no está disponible.',
                ]);
            }

            $this->assertBatchCanBeReverted($lockedBatch);

            $rows = $lockedBatch->filas()
                ->where('aplicada', true)
                ->orderByDesc('numero_fila')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'lote' => 'El lote no contiene filas aplicadas que puedan revertirse.',
                ]);
            }

            $batchExpedienteIds = $rows
                ->map(fn (ImportacionMasivaFila $row): int => (int) data_get(
                    $row->resultado,
                    'rollback.after.expediente.id',
                    0
                ))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $batchDocumentIds = $rows
                ->flatMap(fn (ImportacionMasivaFila $row): array => collect(
                    data_get($row->resultado, 'rollback.documents', [])
                )->map(fn (array $change): int => (int) data_get(
                    $change,
                    'created.id',
                    0
                ))->filter()->values()->all())
                ->unique()
                ->values()
                ->all();

            $summary = [
                'filas_revertidas' => 0,
                'expedientes_dados_baja' => 0,
                'valores_dados_baja' => 0,
                'activos_desactivados' => 0,
                'documentos_inhabilitados' => 0,
            ];

            foreach ($rows as $row) {
                $result = is_array($row->resultado) ? $row->resultado : [];
                $rollback = $result['rollback'] ?? null;

                $this->assertRollbackMetadata($row, $rollback);

                $numeroActivo = (string) data_get(
                    $rollback,
                    'after.activo.numero_activo',
                    ''
                );
                $expedienteId = (int) data_get(
                    $rollback,
                    'after.expediente.id',
                    0
                );

                if ($numeroActivo === '' || $expedienteId <= 0) {
                    throw ValidationException::withMessages([
                        'lote' => "La fila {$row->numero_fila} no conserva identificadores suficientes para una reversión segura.",
                    ]);
                }

                $this->assertCurrentStateMatches($row, $rollback);
                $this->assertNoLaterDependencies(
                    batch: $lockedBatch,
                    numeroActivo: $numeroActivo,
                    expedienteId: $expedienteId,
                    batchExpedienteIds: $batchExpedienteIds,
                    batchDocumentIds: $batchDocumentIds
                );

                $documentsDisabled = $this->restoreDocuments(
                    (array) ($rollback['documents'] ?? [])
                );
                $valueDeleted = $this->restoreValue(
                    before: data_get($rollback, 'before.valor'),
                    after: data_get($rollback, 'after.valor'),
                    userId: $userId,
                    reason: $reason
                );
                $expedienteDeleted = $this->restoreExpediente(
                    before: data_get($rollback, 'before.expediente'),
                    after: data_get($rollback, 'after.expediente'),
                    userId: $userId,
                    reason: $reason
                );
                $assetDisabled = $this->restoreAsset(
                    before: data_get($rollback, 'before.activo'),
                    after: data_get($rollback, 'after.activo'),
                    userId: $userId
                );

                $result['rollback']['reverted_at'] = now()->toIso8601String();
                $result['rollback']['reverted_by'] = $userId;
                $result['rollback']['reversion_reason'] = $reason;

                $row->update(['resultado' => $result]);

                $summary['filas_revertidas']++;
                $summary['documentos_inhabilitados'] += $documentsDisabled;
                $summary['expedientes_dados_baja'] += $expedienteDeleted ? 1 : 0;
                $summary['valores_dados_baja'] += $valueDeleted ? 1 : 0;
                $summary['activos_desactivados'] += $assetDisabled ? 1 : 0;

                $this->registerAudit(
                    userId: $userId,
                    numeroActivo: $numeroActivo,
                    action: 'IMPORTACION_FILA_REVERTIDA',
                    table: 'importacion_masiva_filas',
                    key: (string) $row->id,
                    before: [
                        'importacion_uuid' => $lockedBatch->uuid,
                        'resultado_aplicacion' => $rollback['after'] ?? null,
                    ],
                    after: [
                        'motivo' => $reason,
                        'resultado_reversion' => $rollback['before'] ?? null,
                    ]
                );
            }

            $previousSummary = $lockedBatch->resumen ?? [];
            $lockedBatch->update([
                'estado' => 'revertida',
                'revertida_at' => now(),
                'revertida_por' => $userId,
                'motivo_reversion' => $reason,
                'reversion_resumen' => $summary,
                'resumen' => array_merge($previousSummary, [
                    'reversion' => array_merge($summary, [
                        'disponible' => false,
                        'motivo' => $reason,
                        'revertida_at' => now()->toIso8601String(),
                    ]),
                ]),
            ]);

            $this->registerAudit(
                userId: $userId,
                numeroActivo: null,
                action: 'IMPORTACION_LOTE_REVERTIDA',
                table: 'importaciones_masivas',
                key: $lockedBatch->uuid,
                before: [
                    'estado' => 'aplicada',
                    'aplicada_at' => $lockedBatch->aplicada_at?->toIso8601String(),
                ],
                after: [
                    'estado' => 'revertida',
                    'motivo' => $reason,
                    'resumen' => $summary,
                ]
            );

            return $summary;
        }, 3);
    }

    private function assertBatchCanBeReverted(ImportacionMasiva $batch): void
    {
        if ($batch->estado !== 'aplicada') {
            throw ValidationException::withMessages([
                'lote' => 'Solo puede revertirse un lote que se encuentre aplicado.',
            ]);
        }

        if (!$batch->reversion_disponible_hasta) {
            throw ValidationException::withMessages([
                'lote' => 'Este lote fue aplicado antes de habilitar HU-029 y no contiene una instantánea de reversión confiable.',
            ]);
        }

        if ($batch->reversion_disponible_hasta->isPast()) {
            throw ValidationException::withMessages([
                'lote' => 'La ventana autorizada para revertir este lote ya terminó.',
            ]);
        }

        if (data_get($batch->resumen, 'reversion.disponible') !== true) {
            throw ValidationException::withMessages([
                'lote' => 'El lote no cuenta con metadatos completos para una reversión segura.',
            ]);
        }
    }

    private function assertRollbackMetadata(
        ImportacionMasivaFila $row,
        mixed $rollback
    ): void {
        if (
            !is_array($rollback)
            || (int) ($rollback['version'] ?? 0) !== self::ROLLBACK_VERSION
            || ($rollback['ready'] ?? false) !== true
            || !is_array($rollback['before'] ?? null)
            || !is_array($rollback['after'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'lote' => "La fila {$row->numero_fila} no contiene una instantánea de reversión completa.",
            ]);
        }
    }

    private function assertCurrentStateMatches(
        ImportacionMasivaFila $row,
        array $rollback
    ): void {
        $expectedActivo = data_get($rollback, 'after.activo');
        $expectedExpediente = data_get($rollback, 'after.expediente');
        $expectedValor = data_get($rollback, 'after.valor');

        $currentActivo = $this->snapshotByKey(
            'activos',
            'numero_activo',
            (string) data_get($expectedActivo, 'numero_activo', ''),
            self::ACTIVO_FIELDS
        );
        $currentExpediente = $this->snapshotByKey(
            'expedientes',
            'id',
            (int) data_get($expectedExpediente, 'id', 0),
            self::EXPEDIENTE_FIELDS
        );
        $currentValor = is_array($expectedValor)
            ? $this->snapshotByKey(
                'valores_activo',
                'id',
                (int) data_get($expectedValor, 'id', 0),
                self::VALOR_FIELDS
            )
            : $this->snapshotActiveValueForAsset(
                (string) data_get($expectedActivo, 'numero_activo', '')
            );

        foreach ([
            'activo' => [$expectedActivo, $currentActivo],
            'expediente' => [$expectedExpediente, $currentExpediente],
            'valor fiscal/financiero' => [$expectedValor, $currentValor],
        ] as $label => [$expected, $current]) {
            if (!$this->snapshotsAreEqual($expected, $current)) {
                throw ValidationException::withMessages([
                    'lote' => "La fila {$row->numero_fila} no puede revertirse porque el {$label} cambió después de aplicar el lote.",
                ]);
            }
        }

        foreach ((array) ($rollback['documents'] ?? []) as $change) {
            $expectedDocument = data_get($change, 'created');

            if (!is_array($expectedDocument)) {
                throw ValidationException::withMessages([
                    'lote' => "La fila {$row->numero_fila} contiene metadatos documentales incompletos.",
                ]);
            }

            $currentDocument = $this->snapshotByKey(
                'documentos_expediente',
                'id',
                (int) ($expectedDocument['id'] ?? 0),
                self::DOCUMENT_FIELDS
            );

            if (!$this->snapshotsAreEqual($expectedDocument, $currentDocument)) {
                throw ValidationException::withMessages([
                    'lote' => "La fila {$row->numero_fila} no puede revertirse porque uno de sus documentos fue modificado posteriormente.",
                ]);
            }
        }
    }

    private function assertNoLaterDependencies(
        ImportacionMasiva $batch,
        string $numeroActivo,
        int $expedienteId,
        array $batchExpedienteIds,
        array $batchDocumentIds
    ): void {
        $appliedAt = $batch->aplicada_at;

        if (!$appliedAt instanceof CarbonInterface) {
            throw new RuntimeException('El lote no conserva la fecha de aplicación requerida para validar dependencias.');
        }

        if (
            DB::table('documentos_expediente')
                ->where('expediente_id', $expedienteId)
                ->when(
                    $batchDocumentIds !== [],
                    fn ($query) => $query->whereNotIn('id', $batchDocumentIds)
                )
                ->where(function ($query) use ($appliedAt): void {
                    $query->where('created_at', '>=', $appliedAt)
                        ->orWhere('updated_at', '>=', $appliedAt);
                })
                ->lockForUpdate()
                ->exists()
        ) {
            $this->dependencyError($numeroActivo, 'documentos agregados después de la importación');
        }

        if (
            DB::table('expedientes')
                ->where('numero_activo', $numeroActivo)
                ->when(
                    $batchExpedienteIds !== [],
                    fn ($query) => $query->whereNotIn('id', $batchExpedienteIds)
                )
                ->where(function ($query) use ($appliedAt): void {
                    $query->where('created_at', '>=', $appliedAt)
                        ->orWhere('updated_at', '>=', $appliedAt);
                })
                ->lockForUpdate()
                ->exists()
        ) {
            $this->dependencyError($numeroActivo, 'otros expedientes creados posteriormente');
        }

        $dependencies = [
            ['expediente_observaciones', 'expediente_id', $expedienteId],
            ['movimientos_ubicacion', 'numero_activo', $numeroActivo],
            ['inventarios_activo', 'numero_activo', $numeroActivo],
            ['inventario_evidencias', 'numero_activo', $numeroActivo],
            ['solicitudes_traslado', 'numero_activo', $numeroActivo],
        ];

        foreach ($dependencies as [$table, $column, $value]) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (
                DB::table($table)
                    ->where($column, $value)
                    ->where(function ($query) use ($appliedAt): void {
                        $query->where('created_at', '>=', $appliedAt)
                            ->orWhere('updated_at', '>=', $appliedAt);
                    })
                    ->lockForUpdate()
                    ->exists()
            ) {
                $this->dependencyError(
                    $numeroActivo,
                    "actividad posterior registrada en {$table}"
                );
            }
        }
    }

    private function dependencyError(string $numeroActivo, string $detail): never
    {
        throw ValidationException::withMessages([
            'lote' => "No es posible revertir el lote porque el activo {$numeroActivo} tiene {$detail}.",
        ]);
    }

    private function restoreDocuments(array $changes): int
    {
        $disabled = 0;

        foreach ($changes as $change) {
            $created = data_get($change, 'created');

            if (is_array($created) && (int) ($created['id'] ?? 0) > 0) {
                DB::table('documentos_expediente')
                    ->where('id', (int) $created['id'])
                    ->update([
                        'vigente' => false,
                        'updated_at' => now(),
                    ]);
                $disabled++;
            }

            foreach ((array) data_get($change, 'previous', []) as $previous) {
                if (!is_array($previous) || (int) ($previous['id'] ?? 0) <= 0) {
                    continue;
                }

                DB::table('documentos_expediente')
                    ->where('id', (int) $previous['id'])
                    ->update([
                        'vigente' => (bool) ($previous['vigente'] ?? false),
                        'updated_at' => $previous['updated_at'] ?? now(),
                    ]);
            }
        }

        return $disabled;
    }

    private function restoreValue(
        mixed $before,
        mixed $after,
        int $userId,
        string $reason
    ): bool {
        if (is_array($before)) {
            $id = (int) ($before['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('La instantánea del valor fiscal/financiero no contiene un identificador válido.');
            }

            DB::table('valores_activo')
                ->where('id', $id)
                ->update($this->withoutKey($before, 'id'));

            return false;
        }

        if ($after === null) {
            return false;
        }

        if (!is_array($after) || (int) ($after['id'] ?? 0) <= 0) {
            throw new RuntimeException('La instantánea posterior del valor fiscal/financiero no es válida.');
        }

        DB::table('valores_activo')
            ->where('id', (int) $after['id'])
            ->update([
                'estatus_contable' => 'baja',
                'deleted_at' => now(),
                'deleted_by' => $userId,
                'delete_reason' => self::DELETE_REASON_PREFIX.' '.$reason,
                'updated_at' => now(),
            ]);

        return true;
    }

    private function restoreExpediente(
        mixed $before,
        mixed $after,
        int $userId,
        string $reason
    ): bool {
        if (!is_array($after) || (int) ($after['id'] ?? 0) <= 0) {
            throw new RuntimeException('La instantánea del expediente aplicado no es válida.');
        }

        $id = (int) $after['id'];

        if (is_array($before)) {
            DB::table('expedientes')
                ->where('id', $id)
                ->update($this->withoutKey($before, 'id'));

            return false;
        }

        DB::table('expedientes')
            ->where('id', $id)
            ->update([
                'estatus' => 'incompleto',
                'actualizado_por' => $userId,
                'deleted_at' => now(),
                'deleted_by' => $userId,
                'delete_reason' => self::DELETE_REASON_PREFIX.' '.$reason,
                'updated_at' => now(),
            ]);

        return true;
    }

    private function restoreAsset(
        mixed $before,
        mixed $after,
        int $userId
    ): bool {
        if (!is_array($after) || trim((string) ($after['numero_activo'] ?? '')) === '') {
            throw new RuntimeException('La instantánea del activo aplicado no es válida.');
        }

        $numeroActivo = (string) $after['numero_activo'];

        if (is_array($before)) {
            DB::table('activos')
                ->where('numero_activo', $numeroActivo)
                ->update($this->withoutKey($before, 'numero_activo'));

            return false;
        }

        $activeExpedientes = DB::table('expedientes')
            ->where('numero_activo', $numeroActivo)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->pluck('id')
            ->count();

        if ($activeExpedientes > 0) {
            throw ValidationException::withMessages([
                'lote' => "El activo {$numeroActivo} conserva expedientes activos y no puede desactivarse como parte de la reversión.",
            ]);
        }

        DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->update([
                'activo' => false,
                'estatus_documental' => 'incompleto',
                'actualizado_por' => $userId,
                'updated_at' => now(),
            ]);

        return true;
    }

    private function snapshotActiveValueForAsset(string $numeroActivo): ?array
    {
        if ($numeroActivo === '') {
            return null;
        }

        $row = DB::table('valores_activo')
            ->where('numero_activo', $numeroActivo)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first(self::VALOR_FIELDS);

        return $row ? (array) $row : null;
    }

    private function snapshotByKey(
        string $table,
        string $key,
        string|int $value,
        array $fields
    ): ?array {
        if ($value === '' || $value === 0) {
            return null;
        }

        $row = DB::table($table)
            ->where($key, $value)
            ->lockForUpdate()
            ->first($fields);

        return $row ? (array) $row : null;
    }

    private function snapshotsAreEqual(mixed $expected, mixed $current): bool
    {
        if ($expected === null || $current === null) {
            return $expected === $current;
        }

        if (!is_array($expected) || !is_array($current)) {
            return false;
        }

        return $this->canonicalize($expected) === $this->canonicalize($current);
    }

    private function canonicalize(array $values): array
    {
        ksort($values);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->canonicalize($value);
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $values[$key] = $value ? '1' : '0';
                continue;
            }

            $values[$key] = (string) $value;
        }

        return $values;
    }

    private function withoutKey(array $values, string $key): array
    {
        unset($values[$key]);

        return $values;
    }

    private function registerAudit(
        int $userId,
        ?string $numeroActivo,
        string $action,
        string $table,
        string $key,
        ?array $before,
        ?array $after
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => $userId,
            'modulo' => 'M01 Gestión de expedientes de activo fijo',
            'accion' => $action,
            'tabla_afectada' => $table,
            'registro_clave' => $key,
            'antes' => $before
                ? json_encode(
                    $before,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
                : null,
            'despues' => $after
                ? json_encode(
                    $after,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
                : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class ValorActivoHistoryService
{
    /**
     * Campos de negocio visibles en el histórico. Se excluyen identificadores
     * técnicos y metadatos internos que no aportan valor a la auditoría funcional.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'numero_activo' => 'Número de activo',
        'valor_fiscal' => 'Valor fiscal',
        'valor_financiero' => 'Valor financiero',
        'moneda' => 'Moneda',
        'tipo_cambio' => 'Tipo de cambio',
        'fecha_tipo_cambio' => 'Fecha del tipo de cambio',
        'origen_tipo_cambio' => 'Origen del tipo de cambio',
        'depreciacion_acumulada' => 'Depreciación acumulada',
        'valor_en_libros' => 'Valor en libros',
        'vida_util_meses' => 'Vida útil',
        'metodo_depreciacion' => 'Método de depreciación referencial',
        'fecha_inicio_depreciacion' => 'Fecha de inicio de depreciación',
        'valor_residual' => 'Valor residual',
        'depreciacion_estimada' => 'Depreciación estimada',
        'valor_en_libros_estimado' => 'Valor en libros estimado',
        'calculo_depreciacion_at' => 'Fecha del cálculo referencial',
        'estatus_contable' => 'Estatus contable',
        'motivo_cambio' => 'Motivo del cambio',
        'conciliacion_cfdi' => 'Estado técnico del XML',
        'conciliacion_detalle' => 'Detalle del soporte XML',
        'fecha_corte' => 'Fecha de corte',
        'deleted_at' => 'Fecha de baja lógica',
        'delete_reason' => 'Motivo de baja lógica',
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'ALTA_VALOR' => 'Registro inicial',
        'EDICION_VALOR' => 'Actualización manual',
        'RESTAURACION_VALOR' => 'Restauración de registro',
        'BAJA_LOGICA_VALOR' => 'Baja lógica',
        'IMPORTACION_VALOR_ALTA' => 'Alta por importación',
        'IMPORTACION_VALOR_EDICION' => 'Actualización por importación',
        'IMPORTACION_VALOR_RESTAURACION' => 'Restauración por importación',
        'CONCILIACION_VALOR_CFDI' => 'Actualización histórica del soporte XML',
        'ACTUALIZACION_SOPORTE_XML' => 'Actualización del soporte XML',
        'IMPORTACION_FILA_REVERTIDA' => 'Reversión de importación',
    ];

    /**
     * Obtiene el histórico paginado y transforma cada evento en una estructura
     * preparada para mostrarse sin exponer metadatos innecesarios.
     */
    public function paginate(string $numeroActivo, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $query = $this->baseQuery($numeroActivo);
        $this->applyFilters($query, $filters);

        $paginator = $query
            ->orderByDesc('b.fecha_evento')
            ->orderByDesc('b.id')
            ->paginate($perPage, ['*'], 'historial_page')
            ->withQueryString();

        $paginator->getCollection()->transform(
            fn (object $entry): object => $this->presentEntry($entry)
        );

        return $paginator;
    }

    public function summary(string $numeroActivo): array
    {
        $query = $this->baseQuery($numeroActivo);

        return [
            'total_eventos' => (clone $query)->count(),
            'primer_evento' => (clone $query)->min('b.fecha_evento'),
            'ultimo_evento' => (clone $query)->max('b.fecha_evento'),
            'usuarios' => (clone $query)
                ->whereNotNull('b.user_id')
                ->distinct()
                ->count('b.user_id'),
        ];
    }

    public function availableActions(string $numeroActivo): array
    {
        return $this->baseQuery($numeroActivo)
            ->select('b.accion')
            ->distinct()
            ->orderBy('b.accion')
            ->pluck('b.accion')
            ->map(fn ($action): array => [
                'value' => (string) $action,
                'label' => $this->actionLabel((string) $action),
            ])
            ->values()
            ->all();
    }

    public function availableUsers(string $numeroActivo): array
    {
        return $this->baseQuery($numeroActivo)
            ->whereNotNull('b.user_id')
            ->select('u.id', 'u.name', 'u.usuario')
            ->distinct()
            ->orderBy('u.name')
            ->get()
            ->map(fn (object $user): array => [
                'id' => (int) $user->id,
                'name' => trim((string) ($user->name ?: $user->usuario ?: 'Usuario SWAFI')),
            ])
            ->all();
    }

    public function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'asset_value_history_snapshot_decode',
                ['payload_length' => strlen($payload)]
            );

            return [];
        }
    }

    /**
     * Compara los snapshots y devuelve únicamente los campos de negocio que
     * cambiaron. Este método es público para poder probar la regla de auditoría
     * sin depender de una conexión de base de datos.
     */
    public function buildChanges(array $before, array $after): array
    {
        $changes = [];

        foreach (self::FIELD_LABELS as $field => $label) {
            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;

            if ($this->valuesEquivalent($field, $oldValue, $newValue)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'before' => $this->formatValue($field, $oldValue),
                'after' => $this->formatValue($field, $newValue),
            ];
        }

        return $changes;
    }

    public function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action]
            ?? Str::headline(mb_strtolower(str_replace('_', ' ', $action), 'UTF-8'));
    }

    private function baseQuery(string $numeroActivo)
    {
        return DB::table('bitacora_auditoria as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->where('b.numero_activo', $numeroActivo)
            ->where('b.tabla_afectada', 'valores_activo')
            ->select([
                'b.id',
                'b.numero_activo',
                'b.user_id',
                'b.modulo',
                'b.accion',
                'b.registro_clave',
                'b.antes',
                'b.despues',
                'b.ip',
                'b.fecha_evento',
                'u.name as usuario_nombre',
                'u.usuario as usuario_clave',
            ]);
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['accion'])) {
            $query->where('b.accion', (string) $filters['accion']);
        }

        if (!empty($filters['usuario_id'])) {
            $query->where('b.user_id', (int) $filters['usuario_id']);
        }

        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('b.fecha_evento', '>=', (string) $filters['fecha_desde']);
        }

        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('b.fecha_evento', '<=', (string) $filters['fecha_hasta']);
        }
    }

    private function presentEntry(object $entry): object
    {
        $before = $this->decodePayload($entry->antes ?? null);
        $after = $this->decodePayload($entry->despues ?? null);

        $entry->before_payload = $before;
        $entry->after_payload = $after;
        $entry->changes = $this->buildChanges($before, $after);
        $entry->accion_label = $this->actionLabel((string) $entry->accion);
        $entry->accion_class = $this->actionClass((string) $entry->accion);
        $entry->usuario_visible = trim((string) (
            $entry->usuario_nombre
            ?: $entry->usuario_clave
            ?: 'Usuario no disponible'
        ));

        return $entry;
    }

    private function actionClass(string $action): string
    {
        if (str_contains($action, 'BAJA') || str_contains($action, 'REVERT')) {
            return 'danger';
        }

        if (str_contains($action, 'RESTAUR') || str_contains($action, 'ALTA')) {
            return 'ok';
        }

        if (str_contains($action, 'CONCILIACION')) {
            return 'info';
        }

        return 'warn';
    }

    private function valuesEquivalent(string $field, mixed $before, mixed $after): bool
    {
        if (($before === null || $before === '') && ($after === null || $after === '')) {
            return true;
        }

        if (in_array($field, [
            'valor_fiscal',
            'valor_financiero',
            'depreciacion_acumulada',
            'valor_en_libros',
            'valor_residual',
            'depreciacion_estimada',
            'valor_en_libros_estimado',
            'tipo_cambio',
        ], true) && is_numeric($before) && is_numeric($after)) {
            return abs((float) $before - (float) $after) < 0.000001;
        }

        if (in_array($field, [
            'fecha_tipo_cambio',
            'fecha_inicio_depreciacion',
            'fecha_corte',
            'calculo_depreciacion_at',
            'deleted_at',
        ], true)) {
            return $this->normalizeDate($before) === $this->normalizeDate($after);
        }

        if (is_array($before) || is_object($before) || is_array($after) || is_object($after)) {
            return $this->normalizeStructuredValue($before) === $this->normalizeStructuredValue($after);
        }

        return trim((string) $before) === trim((string) $after);
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin valor';
        }

        if (in_array($field, [
            'valor_fiscal',
            'valor_financiero',
            'depreciacion_acumulada',
            'valor_en_libros',
            'valor_residual',
            'depreciacion_estimada',
            'valor_en_libros_estimado',
        ], true) && is_numeric($value)) {
            return '$ '.number_format((float) $value, 2, '.', ',');
        }

        if ($field === 'tipo_cambio' && is_numeric($value)) {
            return number_format((float) $value, 6, '.', ',');
        }

        if ($field === 'vida_util_meses' && is_numeric($value)) {
            return ((int) $value).' meses';
        }

        if (in_array($field, [
            'fecha_tipo_cambio',
            'fecha_inicio_depreciacion',
            'fecha_corte',
            'calculo_depreciacion_at',
            'deleted_at',
        ], true)) {
            $normalized = $this->normalizeDate($value);

            if ($normalized !== null) {
                try {
                    return Carbon::parse($normalized)->format(
                        in_array($field, ['deleted_at', 'calculo_depreciacion_at'], true)
                            ? 'd/m/Y H:i'
                            : 'd/m/Y'
                    );
                } catch (Throwable $exception) {
                    app(SafeExceptionReporter::class)->warning(
                        $exception,
                        'asset_value_history_date_format',
                        ['field' => $field]
                    );

                    return (string) $value;
                }
            }
        }

        if (in_array($field, ['estatus_contable', 'conciliacion_cfdi', 'metodo_depreciacion'], true)) {
            return Str::headline(mb_strtolower(str_replace('_', ' ', (string) $value), 'UTF-8'));
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return $this->normalizeStructuredValue($value);
        }

        return trim((string) $value);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'asset_value_history_datetime_normalization',
                ['value_length' => mb_strlen((string) $value)]
            );

            return trim((string) $value);
        }
    }

    private function normalizeStructuredValue(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'asset_value_history_value_encode',
                ['value_type' => get_debug_type($value)]
            );

            return '[Contenido no disponible]';
        }
    }
}

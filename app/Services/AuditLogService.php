<?php

namespace App\Services;

use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuditLogService
{
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'passwd',
        'contrasena',
        'contraseña',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'session_id',
        'mail_password',
        'private_key',
    ];

    public function query(array $filters): Builder
    {
        $query = DB::table('bitacora_auditoria as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->select([
                'b.id',
                'b.user_id',
                'b.numero_activo',
                'b.modulo',
                'b.accion',
                'b.tabla_afectada',
                'b.registro_clave',
                'b.ip',
                'b.fecha_evento',
                'u.usuario as usuario',
                'u.name as usuario_nombre',
                'u.email as usuario_email',
            ]);

        $search = $this->normalizedString($filters['buscar_bitacora'] ?? null);

        if ($search !== null) {
            $like = '%' . $this->escapeLike($search) . '%';

            $query->where(function (Builder $where) use ($like): void {
                $where->where('b.modulo', 'like', $like)
                    ->orWhere('b.accion', 'like', $like)
                    ->orWhere('b.tabla_afectada', 'like', $like)
                    ->orWhere('b.registro_clave', 'like', $like)
                    ->orWhere('b.numero_activo', 'like', $like)
                    ->orWhere('u.usuario', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhere('u.email', 'like', $like);
            });
        }

        if (!empty($filters['usuario_bitacora_id'])) {
            $query->where('b.user_id', (int) $filters['usuario_bitacora_id']);
        }

        if (($module = $this->normalizedString($filters['modulo'] ?? null)) !== null) {
            $query->where('b.modulo', $module);
        }

        if (($action = $this->normalizedString($filters['accion'] ?? null)) !== null) {
            $query->where('b.accion', $action);
        }

        if (($asset = $this->normalizedString($filters['numero_activo'] ?? null)) !== null) {
            $query->where('b.numero_activo', 'like', '%' . $this->escapeLike($asset) . '%');
        }

        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('b.fecha_evento', '>=', (string) $filters['fecha_desde']);
        }

        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('b.fecha_evento', '<=', (string) $filters['fecha_hasta']);
        }

        return $query
            ->orderByDesc('b.fecha_evento')
            ->orderByDesc('b.id');
    }

    public function filterOptions(): array
    {
        $users = DB::table('users as u')
            ->join('bitacora_auditoria as b', 'b.user_id', '=', 'u.id')
            ->select(['u.id', 'u.usuario', 'u.name', 'u.email'])
            ->distinct()
            ->orderBy('u.name')
            ->get();

        $modules = DB::table('bitacora_auditoria')
            ->whereNotNull('modulo')
            ->where('modulo', '<>', '')
            ->distinct()
            ->orderBy('modulo')
            ->pluck('modulo');

        $actions = DB::table('bitacora_auditoria')
            ->whereNotNull('accion')
            ->where('accion', '<>', '')
            ->distinct()
            ->orderBy('accion')
            ->pluck('accion');

        return [
            'users' => $users,
            'modules' => $modules,
            'actions' => $actions,
        ];
    }

    public function detail(?int $eventId): ?array
    {
        if (!$eventId) {
            return null;
        }

        $event = DB::table('bitacora_auditoria as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->where('b.id', $eventId)
            ->first([
                'b.id',
                'b.user_id',
                'b.numero_activo',
                'b.modulo',
                'b.accion',
                'b.tabla_afectada',
                'b.registro_clave',
                'b.antes',
                'b.despues',
                'b.ip',
                'b.fecha_evento',
                'b.created_at',
                'u.usuario as usuario',
                'u.name as usuario_nombre',
                'u.email as usuario_email',
            ]);

        if (!$event) {
            return null;
        }

        $before = $this->flattenSnapshot($this->decodeSnapshot($event->antes));
        $after = $this->flattenSnapshot($this->decodeSnapshot($event->despues));
        $keys = collect(array_keys($before))
            ->merge(array_keys($after))
            ->unique()
            ->sort()
            ->values();

        $changes = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if ($this->comparableValue($beforeValue) === $this->comparableValue($afterValue)) {
                continue;
            }

            $changes[] = [
                'field' => $key,
                'label' => $this->fieldLabel($key),
                'before' => $this->displayValue($beforeValue),
                'after' => $this->displayValue($afterValue),
            ];
        }

        return [
            'event' => $event,
            'changes' => $changes,
            'before' => $this->snapshotDisplay($before),
            'after' => $this->snapshotDisplay($after),
        ];
    }

    /**
     * @return array{rows: Collection<int, object>, total: int, limit: int}
     */
    public function rowsForExport(array $filters): array
    {
        $query = $this->query($filters)
            ->addSelect(['b.antes', 'b.despues']);

        $countQuery = clone $query;
        $total = (int) $countQuery->reorder()->count('b.id');
        $limit = $this->exportLimit();

        if ($total > $limit) {
            throw new DomainException(
                "La exportación contiene {$total} eventos y supera el límite de {$limit}. "
                . 'Aplica filtros adicionales por fecha, usuario, módulo o acción.'
            );
        }

        return [
            'rows' => $query->limit($limit)->get(),
            'total' => $total,
            'limit' => $limit,
        ];
    }

    public function exportLimit(): int
    {
        return min(
            50000,
            max(100, (int) config('swafi.bitacora.limite_exportacion', 10000))
        );
    }

    public function filterSummary(array $filters): string
    {
        $parts = [];

        foreach ([
            'buscar_bitacora' => 'Texto',
            'usuario_bitacora_id' => 'Usuario ID',
            'numero_activo' => 'Activo',
            'modulo' => 'Módulo',
            'accion' => 'Acción',
            'fecha_desde' => 'Desde',
            'fecha_hasta' => 'Hasta',
        ] as $key => $label) {
            $value = $filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $parts[] = $label . ': ' . (string) $value;
            }
        }

        return $parts === [] ? 'Sin filtros adicionales' : implode(' | ', $parts);
    }

    public function snapshotForExport(mixed $value): string
    {
        $snapshot = $this->flattenSnapshot($this->decodeSnapshot($value));

        if ($snapshot === []) {
            return '—';
        }

        $parts = [];

        foreach ($snapshot as $key => $item) {
            $parts[] = $this->fieldLabel($key) . ': ' . $this->displayValue($item);
        }

        return implode(' | ', $parts);
    }

    public function registerExport(
        string $format,
        int $actorId,
        ?string $ip,
        array $filters,
        int $rowCount
    ): void {
        $action = match ($format) {
            'xlsx' => 'AUDITORIA_EXPORTA_XLSX',
            'pdf' => 'AUDITORIA_EXPORTA_PDF',
            default => 'AUDITORIA_EXPORTA_CSV',
        };

        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $actorId,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => $action,
                'tabla_afectada' => 'bitacora_auditoria',
                'registro_clave' => 'exportacion_filtrada',
                'antes' => null,
                'despues' => json_encode([
                    'formato' => $format,
                    'filas_exportadas' => $rowCount,
                    'filtros' => $this->auditSafeFilters($filters),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'services_auditlogservice_exception_1'
            );
        }
    }

    private function decodeSnapshot(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : ['valor' => $decoded];
        } catch (Throwable $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'audit_snapshot_decode',
                ['value_length' => strlen($value)]
            );

            return ['estado' => 'Contenido histórico no interpretable.'];
        }
    }

    private function flattenSnapshot(array $snapshot, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($snapshot as $key => $value) {
            $key = (string) $key;
            $path = $prefix === '' ? $key : $prefix . '.' . $key;

            if ($this->isSensitiveKey($path)) {
                continue;
            }

            if (is_array($value)) {
                if ($value === []) {
                    $flattened[$path] = null;
                    continue;
                }

                $nested = $this->flattenSnapshot($value, $path);

                if ($nested === []) {
                    $flattened[$path] = null;
                } else {
                    $flattened = array_merge($flattened, $nested);
                }

                continue;
            }

            if (is_object($value)) {
                $flattened = array_merge($flattened, $this->flattenSnapshot((array) $value, $path));
                continue;
            }

            $flattened[$path] = $value;
        }

        return $flattened;
    }

    private function snapshotDisplay(array $snapshot): array
    {
        $display = [];

        foreach ($snapshot as $key => $value) {
            $display[] = [
                'field' => $key,
                'label' => $this->fieldLabel($key),
                'value' => $this->displayValue($value),
            ];
        }

        return $display;
    }

    private function auditSafeFilters(array $filters): array
    {
        $safe = [];

        foreach ([
            'buscar_bitacora',
            'usuario_bitacora_id',
            'numero_activo',
            'modulo',
            'accion',
            'fecha_desde',
            'fecha_hasta',
        ] as $key) {
            $value = $filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = $this->stringLower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function fieldLabel(string $field): string
    {
        $field = str_replace(['.', '_'], ' ', $field);
        $field = preg_replace('/\s+/u', ' ', trim($field)) ?? trim($field);

        return $this->stringTitle($field);
    }

    private function comparableValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
        }

        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', ',');
        }

        $text = is_scalar($value)
            ? (string) $value
            : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—');

        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return $this->stringLength($text) > 2000
            ? $this->stringSubstr($text, 0, 1997) . '...'
            : $text;
    }

    private function stringLower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function stringTitle(string $value): string
    {
        return function_exists('mb_convert_case')
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : ucwords(strtolower($value));
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function stringSubstr(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $start, $length)
            : substr($value, $start, $length);
    }

    private function normalizedString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

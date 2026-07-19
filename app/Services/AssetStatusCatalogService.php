<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AssetStatusCatalogService
{
    public const DOCUMENTARY_TABLE = 'estatus_documentales';

    public const OPERATIONAL_TABLE = 'estatus_operativos';

    public const BASE_DOCUMENTARY = [
        'completo' => 'Completo',
        'incompleto' => 'Incompleto',
        'observado' => 'Observado',
    ];

    public const BASE_OPERATIONAL = [
        'en_operacion' => 'En operación',
        'traslado' => 'Traslado',
        'baja' => 'Baja',
    ];

    private array $cache = [];

    public function documentaryOptions(bool $activeOnly = true): Collection
    {
        return $this->options(self::DOCUMENTARY_TABLE, self::BASE_DOCUMENTARY, $activeOnly);
    }

    public function operationalOptions(bool $activeOnly = true): Collection
    {
        return $this->options(self::OPERATIONAL_TABLE, self::BASE_OPERATIONAL, $activeOnly);
    }

    public function activeDocumentaryKeys(): array
    {
        return $this->documentaryOptions()
            ->pluck('clave')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    public function activeOperationalKeys(): array
    {
        return $this->operationalOptions()
            ->pluck('clave')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    public function isActiveDocumentary(string $key): bool
    {
        return in_array($this->normalizeKey($key), $this->activeDocumentaryKeys(), true);
    }

    public function isActiveOperational(string $key): bool
    {
        return in_array($this->normalizeKey($key), $this->activeOperationalKeys(), true);
    }

    public function normalizeOperationalInput(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeKey($value);
        $aliases = [
            'operacion' => 'en_operacion',
            'enoperacion' => 'en_operacion',
            'activo' => 'en_operacion',
        ];

        $normalized = $aliases[$normalized] ?? $normalized;

        return $this->isActiveOperational($normalized) ? $normalized : null;
    }

    public function documentaryLabel(?string $key): string
    {
        return $this->labelFor(self::DOCUMENTARY_TABLE, self::BASE_DOCUMENTARY, $key);
    }

    public function operationalLabel(?string $key): string
    {
        return $this->labelFor(self::OPERATIONAL_TABLE, self::BASE_OPERATIONAL, $key);
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    private function options(string $table, array $fallback, bool $activeOnly): Collection
    {
        $cacheKey = $table . ':' . ($activeOnly ? 'active' : 'all');

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if (!Schema::hasTable($table)) {
            return $this->cache[$cacheKey] = collect($fallback)
                ->map(fn (string $label, string $key) => (object) [
                    'id' => null,
                    'clave' => $key,
                    'nombre' => $label,
                    'descripcion' => null,
                    'orden' => 100,
                    'es_sistema' => true,
                    'estatus' => 'activo',
                ])
                ->values();
        }

        $query = DB::table($table)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->orderBy('clave');

        if ($activeOnly) {
            $query->where('estatus', 'activo');
        }

        return $this->cache[$cacheKey] = $query->get([
            'id',
            'clave',
            'nombre',
            'descripcion',
            'orden',
            'es_sistema',
            'estatus',
        ]);
    }

    private function labelFor(string $table, array $fallback, ?string $key): string
    {
        $normalized = $this->normalizeKey((string) $key);

        if ($normalized === '') {
            return 'Sin estatus';
        }

        $record = $this->options($table, $fallback, false)
            ->first(fn ($row) => (string) $row->clave === $normalized);

        if ($record !== null) {
            return (string) $record->nombre;
        }

        return $fallback[$normalized] ?? Str::headline($normalized);
    }

    private function normalizeKey(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }
}

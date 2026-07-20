<?php

namespace App\Services;

use App\Models\Activo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetRegistrationService
{
    /**
     * Obtiene los datos maestros operativos de un activo vigente para asociar
     * un nuevo expediente sin exponer valores fiscales o financieros.
     */
    public function lookupActive(string $numeroActivo): ?array
    {
        $asset = $this->activeAssetQuery()
            ->where('a.numero_activo', $this->normalizeAssetNumber($numeroActivo))
            ->first($this->assetColumns());

        return $asset ? $this->mapAsset($asset) : null;
    }

    /**
     * Busca activos vigentes mediante coincidencia inicial del número de activo
     * y filtros de proveedor o planta. La consulta permanece paginada para no
     * cargar listados extensos dentro del formulario de registro individual.
     *
     * @return array{
     *     data:array<int,array<string,mixed>>,
     *     meta:array{
     *         current_page:int,
     *         last_page:int,
     *         per_page:int,
     *         total:int,
     *         from:?int,
     *         to:?int
     *     }
     * }
     */
    public function searchActive(array $filters): array
    {
        $allowedPerPage = [5, 8, 10, 15, 20];
        $requestedPerPage = (int) ($filters['per_page'] ?? 8);
        $perPage = in_array($requestedPerPage, $allowedPerPage, true)
            ? $requestedPerPage
            : 8;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $term = $this->normalizeAssetNumber((string) ($filters['q'] ?? ''));

        $query = $this->activeAssetQuery();

        if ($term !== '') {
            $query->where('a.numero_activo', 'like', $term . '%');
        }

        if (!empty($filters['proveedor_id'])) {
            $query->where('a.proveedor_id', (int) $filters['proveedor_id']);
        }

        if (!empty($filters['planta_id'])) {
            $query->where('a.planta_id', (int) $filters['planta_id']);
        }

        $paginator = $query
            ->orderBy('a.numero_activo')
            ->paginate(
                $perPage,
                $this->assetColumns(),
                'page',
                $page
            );

        return [
            'data' => collect($paginator->items())
                ->map(fn (object $asset): array => $this->mapAssetSummary($asset))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function findActiveForUpdate(string $numeroActivo): ?Activo
    {
        return Activo::query()
            ->whereKey($this->normalizeAssetNumber($numeroActivo))
            ->where('activo', true)
            ->lockForUpdate()
            ->first();
    }

    public function createNew(
        array $data,
        string $estatusDocumental,
        ?int $userId
    ): Activo {
        return Activo::create([
            'numero_activo' => $this->normalizeAssetNumber((string) $data['numero_activo']),
            'tipo_activo_id' => (int) $data['tipo_activo_id'],
            'proveedor_id' => (int) $data['proveedor_id'],
            'centro_costo_id' => (int) $data['centro_costo_id'],
            'planta_id' => (int) $data['planta_id'],
            'ubicacion_id' => $this->nullableInteger($data['ubicacion_id'] ?? null),
            'responsable_id' => $this->nullableInteger($data['responsable_id'] ?? null),
            'descripcion' => (string) $data['descripcion'],
            'serie' => $this->nullableString($data['serie'] ?? null),
            'marca' => $this->nullableString($data['marca'] ?? null),
            'modelo' => $this->nullableString($data['modelo'] ?? null),
            'fecha_adquisicion' => $data['fecha_adquisicion'] ?? null,
            'estatus_operativo' => (string) $data['estatus_operativo'],
            'estatus_documental' => $estatusDocumental,
            'activo' => true,
            'creado_por' => $userId,
            'actualizado_por' => $userId,
        ]);
    }

    private function activeAssetQuery(): Builder
    {
        $expedientCounts = DB::table('expedientes')
            ->select('numero_activo', DB::raw('COUNT(*) AS expedientes_vigentes'))
            ->whereNull('deleted_at')
            ->groupBy('numero_activo');

        return DB::table('activos as a')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->leftJoin('estatus_operativos as eo', 'eo.clave', '=', 'a.estatus_operativo')
            ->leftJoinSub(
                $expedientCounts,
                'ec',
                fn ($join) => $join->on('ec.numero_activo', '=', 'a.numero_activo')
            )
            ->where('a.activo', true);
    }

    /**
     * @return array<int,string|\Illuminate\Contracts\Database\Query\Expression>
     */
    private function assetColumns(): array
    {
        return [
            'a.numero_activo',
            'a.tipo_activo_id',
            'a.proveedor_id',
            'a.centro_costo_id',
            'a.planta_id',
            'a.ubicacion_id',
            'a.responsable_id',
            'a.descripcion',
            'a.serie',
            'a.marca',
            'a.modelo',
            'a.fecha_adquisicion',
            'a.estatus_operativo',
            'a.estatus_documental',
            'a.updated_at',
            'ta.clave as tipo_clave',
            'ta.descripcion as tipo_descripcion',
            'p.rfc as proveedor_rfc',
            'p.nombre as proveedor_nombre',
            'cc.clave as centro_costo_clave',
            'cc.descripcion as centro_costo_descripcion',
            'pl.clave as planta_clave',
            'pl.nombre as planta_nombre',
            'u.codigo_interno as ubicacion_codigo',
            'u.descripcion as ubicacion_descripcion',
            'u.edificio as ubicacion_edificio',
            'u.piso as ubicacion_piso',
            'u.pasillo as ubicacion_pasillo',
            'r.nombre as responsable_nombre',
            'r.correo as responsable_correo',
            'eo.nombre as estatus_operativo_nombre',
            DB::raw('COALESCE(ec.expedientes_vigentes, 0) AS expedientes_vigentes'),
        ];
    }

    private function mapAsset(object $asset): array
    {
        return [
            'numero_activo' => (string) $asset->numero_activo,
            'tipo_activo_id' => $this->nullableInteger($asset->tipo_activo_id),
            'proveedor_id' => $this->nullableInteger($asset->proveedor_id),
            'centro_costo_id' => $this->nullableInteger($asset->centro_costo_id),
            'planta_id' => $this->nullableInteger($asset->planta_id),
            'ubicacion_id' => $this->nullableInteger($asset->ubicacion_id),
            'responsable_id' => $this->nullableInteger($asset->responsable_id),
            'descripcion' => (string) ($asset->descripcion ?? ''),
            'serie' => $asset->serie,
            'marca' => $asset->marca,
            'modelo' => $asset->modelo,
            'fecha_adquisicion' => $asset->fecha_adquisicion,
            'estatus_operativo' => (string) ($asset->estatus_operativo ?? ''),
            'estatus_documental' => (string) ($asset->estatus_documental ?? ''),
            'updated_at' => $asset->updated_at,
            'expedientes_vigentes' => (int) ($asset->expedientes_vigentes ?? 0),
            'labels' => $this->labels($asset),
        ];
    }

    private function mapAssetSummary(object $asset): array
    {
        return [
            'numero_activo' => (string) $asset->numero_activo,
            'descripcion' => (string) ($asset->descripcion ?? ''),
            'serie' => $asset->serie,
            'estatus_documental' => (string) ($asset->estatus_documental ?? ''),
            'expedientes_vigentes' => (int) ($asset->expedientes_vigentes ?? 0),
            'labels' => [
                'tipo_activo' => $this->joinLabel($asset->tipo_clave, $asset->tipo_descripcion),
                'proveedor' => $this->providerLabel($asset->proveedor_nombre, $asset->proveedor_rfc),
                'planta' => $this->joinLabel($asset->planta_clave, $asset->planta_nombre),
                'estatus_operativo' => (string) ($asset->estatus_operativo_nombre ?: $asset->estatus_operativo),
            ],
        ];
    }

    private function labels(object $asset): array
    {
        return [
            'tipo_activo' => $this->joinLabel($asset->tipo_clave, $asset->tipo_descripcion),
            'proveedor' => $this->providerLabel($asset->proveedor_nombre, $asset->proveedor_rfc),
            'centro_costo' => $this->joinLabel($asset->centro_costo_clave, $asset->centro_costo_descripcion),
            'planta' => $this->joinLabel($asset->planta_clave, $asset->planta_nombre),
            'ubicacion' => $this->locationLabel($asset),
            'responsable' => $this->providerLabel($asset->responsable_nombre, $asset->responsable_correo),
            'estatus_operativo' => (string) ($asset->estatus_operativo_nombre ?: $asset->estatus_operativo),
        ];
    }

    private function normalizeAssetNumber(string $numeroActivo): string
    {
        return Str::upper(trim($numeroActivo));
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function joinLabel(mixed $first, mixed $second): string
    {
        return collect([$first, $second])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->implode(' · ');
    }

    private function providerLabel(mixed $name, mixed $reference): string
    {
        $name = trim((string) $name);
        $reference = trim((string) $reference);

        if ($name !== '' && $reference !== '') {
            return $name . ' (' . $reference . ')';
        }

        return $name !== '' ? $name : $reference;
    }

    private function locationLabel(object $asset): string
    {
        $parts = collect([
            $asset->ubicacion_codigo ?? null,
            $asset->ubicacion_descripcion ?? null,
            $asset->ubicacion_edificio ?? null,
            $asset->ubicacion_piso ? 'Piso ' . $asset->ubicacion_piso : null,
            $asset->ubicacion_pasillo ? 'Pasillo ' . $asset->ubicacion_pasillo : null,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return $parts->implode(' · ');
    }
}

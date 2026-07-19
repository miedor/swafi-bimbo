<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;

class CatalogManagementService
{
    public const CATALOGS = [
        'proveedores' => [
            'label' => 'Proveedores',
            'table' => 'proveedores',
            'fields' => ['rfc', 'nombre', 'correo', 'telefono', 'estatus'],
        ],
        'plantas' => [
            'label' => 'Plantas',
            'table' => 'plantas',
            'fields' => ['clave', 'nombre', 'direccion', 'estado', 'pais', 'estatus'],
        ],
        'centros_costo' => [
            'label' => 'Centros de costo',
            'table' => 'centros_costo',
            'fields' => ['planta_id', 'clave', 'descripcion', 'estatus'],
        ],
        'categorias_activo' => [
            'label' => 'Categorías de activo',
            'table' => 'categorias_activo',
            'fields' => ['clave', 'nombre', 'descripcion', 'estatus'],
        ],
        'tipos_activo' => [
            'label' => 'Tipos de activo',
            'table' => 'tipos_activo',
            'fields' => ['categoria_activo_id', 'clave', 'descripcion', 'vida_util_meses', 'estatus'],
        ],
        'areas' => [
            'label' => 'Áreas',
            'table' => 'areas',
            'fields' => ['planta_id', 'clave', 'nombre', 'estatus'],
        ],
        'ubicaciones' => [
            'label' => 'Ubicaciones',
            'table' => 'ubicaciones',
            'fields' => [
                'planta_id',
                'area_id',
                'codigo_interno',
                'edificio',
                'piso',
                'pasillo',
                'descripcion',
                'estatus',
            ],
        ],
        'responsables' => [
            'label' => 'Responsables',
            'table' => 'responsables',
            'fields' => ['nombre', 'correo', 'telefono', 'estatus'],
        ],
    ];

    public function save(
        string $catalog,
        array $data,
        ?int $recordId,
        ?int $userId,
        ?string $ip
    ): object {
        $table = $this->tableFor($catalog);

        return DB::transaction(function () use ($catalog, $table, $data, $recordId, $userId, $ip): object {
            $before = null;

            if ($recordId !== null) {
                $before = DB::table($table)
                    ->where('id', $recordId)
                    ->lockForUpdate()
                    ->first();

                if ($before === null) {
                    throw new DomainException('El registro que intentas actualizar ya no existe.');
                }

                $this->assertUpdateAllowed($catalog, $before, $data);

                if (
                    (string) ($before->estatus ?? 'activo') === 'activo'
                    && (string) ($data['estatus'] ?? 'activo') === 'inactivo'
                ) {
                    $this->assertCatalogCanBeDeactivated($catalog, $recordId);
                }

                DB::table($table)
                    ->where('id', $recordId)
                    ->update(array_merge($data, ['updated_at' => now()]));

                $savedId = $recordId;
                $action = 'ACTUALIZACION_CATALOGO';
            } else {
                $savedId = (int) DB::table($table)->insertGetId(array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                $action = 'ALTA_CATALOGO';
            }

            $after = DB::table($table)
                ->where('id', $savedId)
                ->first();

            if ($after === null) {
                throw new RuntimeException('No fue posible recuperar el registro guardado.');
            }

            $this->audit(
                action: $action,
                table: $table,
                recordKey: (string) $savedId,
                before: $before,
                after: $after,
                userId: $userId,
                ip: $ip
            );

            return $after;
        }, 3);
    }

    public function changeStatus(
        string $catalog,
        int $recordId,
        string $status,
        ?int $userId,
        ?string $ip
    ): object {
        $table = $this->tableFor($catalog);

        return DB::transaction(function () use ($catalog, $table, $recordId, $status, $userId, $ip): object {
            $before = DB::table($table)
                ->where('id', $recordId)
                ->lockForUpdate()
                ->first();

            if ($before === null) {
                throw new DomainException('El registro seleccionado ya no existe.');
            }

            if ((string) $before->estatus === $status) {
                return $before;
            }

            if ($status === 'inactivo') {
                $this->assertCatalogCanBeDeactivated($catalog, $recordId);
            }

            DB::table($table)
                ->where('id', $recordId)
                ->update([
                    'estatus' => $status,
                    'updated_at' => now(),
                ]);

            $after = DB::table($table)
                ->where('id', $recordId)
                ->first();

            if ($after === null) {
                throw new RuntimeException('No fue posible recuperar el registro actualizado.');
            }

            $this->audit(
                action: $status === 'activo' ? 'ACTIVACION_CATALOGO' : 'DESACTIVACION_CATALOGO',
                table: $table,
                recordKey: (string) $recordId,
                before: $before,
                after: $after,
                userId: $userId,
                ip: $ip
            );

            return $after;
        }, 3);
    }

    public function assertUpdateAllowed(string $catalog, object $before, array $data): void
    {
        if ($catalog === 'centros_costo' && array_key_exists('planta_id', $data)) {
            $newPlantId = (int) ($data['planta_id'] ?? 0);
            $oldPlantId = (int) ($before->planta_id ?? 0);

            if ($newPlantId > 0 && $newPlantId !== $oldPlantId) {
                $incompatibleAssets = DB::table('activos')
                    ->where('centro_costo_id', (int) $before->id)
                    ->where('activo', true)
                    ->where('planta_id', '<>', $newPlantId)
                    ->count();

                if ($incompatibleAssets > 0) {
                    throw new DomainException(
                        'No puedes cambiar la planta del centro de costo porque '
                        . $incompatibleAssets
                        . ' activo(s) vigente(s) están registrados en una planta diferente. '
                        . 'Regulariza primero la asignación de los activos.'
                    );
                }
            }
        }

        if ($catalog === 'areas' && array_key_exists('planta_id', $data)) {
            $newPlantId = (int) ($data['planta_id'] ?? 0);
            $oldPlantId = (int) ($before->planta_id ?? 0);

            if ($newPlantId > 0 && $newPlantId !== $oldPlantId) {
                $locations = DB::table('ubicaciones')
                    ->where('area_id', (int) $before->id)
                    ->count();

                if ($locations > 0) {
                    throw new DomainException(
                        'No puedes cambiar la planta del área porque mantiene '
                        . $locations
                        . ' ubicación(es) relacionada(s). Crea el área correcta en la planta destino '
                        . 'y reasigna las ubicaciones mediante un proceso controlado.'
                    );
                }
            }
        }


        if ($catalog === 'tipos_activo' && array_key_exists('categoria_activo_id', $data)) {
            $newCategoryId = (int) ($data['categoria_activo_id'] ?? 0);
            $oldCategoryId = (int) ($before->categoria_activo_id ?? 0);

            if ($oldCategoryId > 0 && $newCategoryId > 0 && $newCategoryId !== $oldCategoryId) {
                $activeAssets = DB::table('activos')
                    ->where('tipo_activo_id', (int) $before->id)
                    ->where('activo', true)
                    ->count();

                if ($activeAssets > 0) {
                    throw new DomainException(
                        'No puedes cambiar la categoría del tipo de activo porque mantiene '
                        . $activeAssets
                        . ' activo(s) vigente(s) asociado(s). Crea un tipo nuevo en la categoría correcta '
                        . 'y reasigna los activos mediante un proceso controlado.'
                    );
                }
            }
        }
    }

    public function assertCatalogCanBeDeactivated(string $catalog, int $recordId): void
    {
        $dependencies = $this->dependenciesFor($catalog, $recordId);

        if ($dependencies === []) {
            return;
        }

        $details = collect($dependencies)
            ->map(fn (int $count, string $label) => $count . ' ' . $label)
            ->implode(', ');

        if ($catalog === 'plantas') {
            throw new DomainException(
                'No puedes desactivar la planta porque mantiene dependencias activas o históricas: '
                . $details
                . '. Reubica o regulariza esas relaciones antes de intentarlo nuevamente.'
            );
        }

        throw new DomainException(
            'No puedes desactivar este registro del catálogo porque mantiene dependencias operativas: '
            . $details
            . '. Regulariza esas relaciones antes de intentarlo nuevamente.'
        );
    }

    public function assertPlantCanBeDeactivated(int $plantId): void
    {
        $this->assertCatalogCanBeDeactivated('plantas', $plantId);
    }

    public function dependenciesFor(string $catalog, int $recordId): array
    {
        return match ($catalog) {
            'plantas' => $this->plantDependencies($recordId),
            'centros_costo' => $this->costCenterDependencies($recordId),
            'categorias_activo' => $this->assetCategoryDependencies($recordId),
            'tipos_activo' => $this->assetTypeDependencies($recordId),
            'areas' => $this->areaDependencies($recordId),
            default => [],
        };
    }

    public function plantDependencies(int $plantId): array
    {
        $dependencies = [
            'activo(s) asociado(s)' => DB::table('activos')
                ->where('planta_id', $plantId)
                ->count(),
            'área(s) activa(s)' => DB::table('areas')
                ->where('planta_id', $plantId)
                ->where('estatus', 'activo')
                ->count(),
            'ubicación(es) activa(s)' => DB::table('ubicaciones')
                ->where('planta_id', $plantId)
                ->where('estatus', 'activo')
                ->count(),
        ];

        if (Schema::hasColumn('centros_costo', 'planta_id')) {
            $dependencies['centro(s) de costo activo(s)'] = DB::table('centros_costo')
                ->where('planta_id', $plantId)
                ->where('estatus', 'activo')
                ->count();
        }

        if (Schema::hasTable('periodos_inventario')) {
            $dependencies['periodo(s) de inventario vigente(s)'] = DB::table('periodos_inventario')
                ->where('planta_id', $plantId)
                ->whereIn('estatus', ['abierto', 'bloqueado'])
                ->count();
        }

        if (Schema::hasTable('solicitudes_traslado')) {
            $dependencies['solicitud(es) de traslado pendiente(s)'] = DB::table('solicitudes_traslado as s')
                ->leftJoin('ubicaciones as origen', 'origen.id', '=', 's.ubicacion_origen_id')
                ->join('ubicaciones as destino', 'destino.id', '=', 's.ubicacion_destino_id')
                ->where('s.estatus', 'pendiente')
                ->where(function ($query) use ($plantId): void {
                    $query->where('origen.planta_id', $plantId)
                        ->orWhere('destino.planta_id', $plantId);
                })
                ->count();
        }

        return array_filter(
            $dependencies,
            fn (int $count) => $count > 0
        );
    }

    public function costCenterDependencies(int $costCenterId): array
    {
        $dependencies = [
            'activo(s) vigente(s) asociado(s)' => DB::table('activos')
                ->where('centro_costo_id', $costCenterId)
                ->where('activo', true)
                ->count(),
        ];

        return array_filter(
            $dependencies,
            fn (int $count) => $count > 0
        );
    }

    public function assetCategoryDependencies(int $categoryId): array
    {
        $dependencies = [
            'tipo(s) de activo activo(s)' => DB::table('tipos_activo')
                ->where('categoria_activo_id', $categoryId)
                ->where('estatus', 'activo')
                ->count(),
        ];

        return array_filter(
            $dependencies,
            fn (int $count) => $count > 0
        );
    }

    public function assetTypeDependencies(int $typeId): array
    {
        $dependencies = [
            'activo(s) vigente(s) asociado(s)' => DB::table('activos')
                ->where('tipo_activo_id', $typeId)
                ->where('activo', true)
                ->count(),
        ];

        return array_filter(
            $dependencies,
            fn (int $count) => $count > 0
        );
    }

    public function areaDependencies(int $areaId): array
    {
        $dependencies = [
            'ubicación(es) activa(s)' => DB::table('ubicaciones')
                ->where('area_id', $areaId)
                ->where('estatus', 'activo')
                ->count(),
            'activo(s) vigente(s) ubicado(s) en el área' => DB::table('activos as ac')
                ->join('ubicaciones as u', 'u.id', '=', 'ac.ubicacion_id')
                ->where('u.area_id', $areaId)
                ->where('ac.activo', true)
                ->count(),
        ];

        if (Schema::hasTable('solicitudes_traslado')) {
            $dependencies['solicitud(es) de traslado pendiente(s)'] = DB::table('solicitudes_traslado as s')
                ->leftJoin('ubicaciones as origen', 'origen.id', '=', 's.ubicacion_origen_id')
                ->join('ubicaciones as destino', 'destino.id', '=', 's.ubicacion_destino_id')
                ->where('s.estatus', 'pendiente')
                ->where(function ($query) use ($areaId): void {
                    $query->where('origen.area_id', $areaId)
                        ->orWhere('destino.area_id', $areaId);
                })
                ->count();
        }

        return array_filter(
            $dependencies,
            fn (int $count) => $count > 0
        );
    }

    public function tableFor(string $catalog): string
    {
        $definition = self::CATALOGS[$catalog] ?? null;

        if ($definition === null) {
            throw new DomainException('El catálogo solicitado no es válido.');
        }

        return $definition['table'];
    }

    public function labelFor(string $catalog): string
    {
        return self::CATALOGS[$catalog]['label'] ?? 'Catálogo';
    }

    /**
     * @throws JsonException
     */
    private function audit(
        string $action,
        string $table,
        string $recordKey,
        ?object $before,
        object $after,
        ?int $userId,
        ?string $ip
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => $userId,
            'modulo' => 'M04 Administración y seguridad del sistema',
            'accion' => $action,
            'tabla_afectada' => $table,
            'registro_clave' => $recordKey,
            'antes' => $before === null
                ? null
                : json_encode((array) $before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'despues' => json_encode((array) $after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => $ip,
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

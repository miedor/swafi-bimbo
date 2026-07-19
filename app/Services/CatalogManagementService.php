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
            'fields' => ['clave', 'descripcion', 'estatus'],
        ],
        'tipos_activo' => [
            'label' => 'Tipos de activo',
            'table' => 'tipos_activo',
            'fields' => ['clave', 'descripcion', 'vida_util_meses', 'estatus'],
        ],
        'areas' => [
            'label' => 'Áreas',
            'table' => 'areas',
            'fields' => ['planta_id', 'nombre', 'estatus'],
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

                if (
                    $catalog === 'plantas'
                    && (string) $before->estatus === 'activo'
                    && ($data['estatus'] ?? 'activo') === 'inactivo'
                ) {
                    $this->assertPlantCanBeDeactivated($recordId);
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

            if ($catalog === 'plantas' && $status === 'inactivo') {
                $this->assertPlantCanBeDeactivated($recordId);
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

    public function assertPlantCanBeDeactivated(int $plantId): void
    {
        $dependencies = $this->plantDependencies($plantId);

        if ($dependencies === []) {
            return;
        }

        $details = collect($dependencies)
            ->map(fn (int $count, string $label) => $count . ' ' . $label)
            ->implode(', ');

        throw new DomainException(
            'No puedes desactivar la planta porque mantiene dependencias activas o históricas: '
            . $details
            . '. Reubica o regulariza esas relaciones antes de intentarlo nuevamente.'
        );
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GENERIC_PERMISSION = 'catalogos.ver';

    private const AUDIT_ACTION = 'HABILITA_VISIBILIDAD_CATALOGOS';

    /**
     * @var array<string, string>
     */
    private const CATALOG_PERMISSIONS = [
        'catalogos.proveedores.ver' => 'Consultar el catálogo de proveedores.',
        'catalogos.plantas.ver' => 'Consultar el catálogo de plantas.',
        'catalogos.centros_costo.ver' => 'Consultar el catálogo de centros de costo.',
        'catalogos.categorias_activo.ver' => 'Consultar el catálogo de categorías de activo.',
        'catalogos.tipos_activo.ver' => 'Consultar el catálogo de tipos de activo.',
        'catalogos.estatus_documentales.ver' => 'Consultar el catálogo de estatus documentales.',
        'catalogos.estatus_operativos.ver' => 'Consultar el catálogo de estatus operativos.',
        'catalogos.areas.ver' => 'Consultar el catálogo de áreas.',
        'catalogos.ubicaciones.ver' => 'Consultar el catálogo de ubicaciones.',
        'catalogos.responsables.ver' => 'Consultar el catálogo de responsables.',
    ];

    public function up(): void
    {
        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        DB::transaction(function (): void {
            $this->createPermissions();
            $this->preserveCurrentCatalogVisibility();
            $this->registerAuditEvent();
        }, 3);
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-106')
                ->delete();
        }

        if (
            !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('clave', array_keys(self::CATALOG_PERMISSIONS))
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($permissionIds): void {
            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }, 3);
    }

    private function createPermissions(): void
    {
        $now = now();

        foreach (self::CATALOG_PERMISSIONS as $key => $description) {
            $payload = [
                'descripcion' => $description,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('permissions', 'activo')) {
                $payload['activo'] = 1;
            }

            if (Schema::hasColumn('permissions', 'es_sistema')) {
                $payload['es_sistema'] = 1;
            }

            if (!DB::table('permissions')->where('clave', $key)->exists()) {
                $payload['created_at'] = $now;
            }

            DB::table('permissions')->updateOrInsert(
                ['clave' => $key],
                $payload
            );
        }
    }

    private function preserveCurrentCatalogVisibility(): void
    {
        $genericPermissionId = DB::table('permissions')
            ->where('clave', self::GENERIC_PERMISSION)
            ->value('id');

        $roleIds = collect();

        if ($genericPermissionId) {
            $roleIds = DB::table('permission_role')
                ->where('permission_id', $genericPermissionId)
                ->pluck('role_id');
        }

        $administratorRoleId = DB::table('roles')
            ->where('nombre', 'Administrador SWAFI')
            ->value('id');

        if ($administratorRoleId) {
            $roleIds->push($administratorRoleId);
        }

        $roleIds = $roleIds
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->filter(fn (int $roleId): bool => $roleId > 0)
            ->unique()
            ->values();

        if ($roleIds->isEmpty()) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('clave', array_keys(self::CATALOG_PERMISSIONS))
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function registerAuditEvent(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => self::AUDIT_ACTION,
                'registro_clave' => 'HU-106',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'permissions,permission_role',
                'antes' => null,
                'despues' => json_encode([
                    'historia_usuario' => 'HU-106',
                    'permiso_modulo' => self::GENERIC_PERMISSION,
                    'permisos_catalogo' => array_keys(self::CATALOG_PERMISSIONS),
                    'preserva_visibilidad_existente' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};

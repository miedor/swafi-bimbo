<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AUDIT_ACTION = 'SEGURIDAD_ROLES_PERMISOS_CONTROL';

    public function up(): void
    {
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'es_sistema')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->boolean('es_sistema')
                    ->default(false)
                    ->after('activo');
                $table->index(['activo', 'es_sistema'], 'roles_activo_sistema_index');
            });
        }

        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'activo')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->boolean('activo')
                    ->default(true)
                    ->after('descripcion');
                $table->index('activo', 'permissions_activo_index');
            });
        }

        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'es_sistema')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->boolean('es_sistema')
                    ->default(false)
                    ->after('activo');
                $table->index('es_sistema', 'permissions_sistema_index');
            });
        }

        $this->markProtectedSecurityCatalogs();
        $this->synchronizeAdministratorPermissions();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-091,HU-092')
                ->delete();
        }

        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'es_sistema')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->dropIndex('permissions_sistema_index');
                $table->dropColumn('es_sistema');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'activo')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->dropIndex('permissions_activo_index');
                $table->dropColumn('activo');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'es_sistema')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropIndex('roles_activo_sistema_index');
                $table->dropColumn('es_sistema');
            });
        }
    }

    private function markProtectedSecurityCatalogs(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'es_sistema')) {
            DB::table('roles')
                ->whereIn('nombre', [
                    'Administrador SWAFI',
                    'Usuario Captura',
                    'Usuario Consulta / Auditoría',
                    'Usuario Planta / Inventarios',
                ])
                ->update([
                    'activo' => 1,
                    'es_sistema' => 1,
                    'updated_at' => now(),
                ]);
        }

        if (
            !Schema::hasTable('permissions')
            || !Schema::hasColumn('permissions', 'activo')
            || !Schema::hasColumn('permissions', 'es_sistema')
        ) {
            return;
        }

        DB::table('permissions')
            ->whereIn('clave', $this->systemPermissionKeys())
            ->update([
                'activo' => 1,
                'es_sistema' => 1,
                'updated_at' => now(),
            ]);
    }

    private function synchronizeAdministratorPermissions(): void
    {
        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
            || !Schema::hasColumn('permissions', 'activo')
        ) {
            return;
        }

        $administratorRoleId = DB::table('roles')
            ->where('nombre', 'Administrador SWAFI')
            ->where('activo', 1)
            ->value('id');

        if (!$administratorRoleId) {
            return;
        }

        $activePermissionIds = DB::table('permissions')
            ->where('activo', 1)
            ->pluck('id');

        foreach ($activePermissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $administratorRoleId,
                'permission_id' => $permissionId,
            ]);
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
                'registro_clave' => 'HU-091,HU-092',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'roles,permissions,permission_role',
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-091', 'HU-092'],
                    'roles_base_protegidos' => 4,
                    'permisos_sistema_protegidos' => count($this->systemPermissionKeys()),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Claves utilizadas por rutas, middleware y flujos productivos de SWAFI.
     *
     * @return array<int, string>
     */
    private function systemPermissionKeys(): array
    {
        return [
            'bitacora.ver',
            'catalogos.administrar',
            'cfdi.validar',
            'dashboard.ver',
            'documentos.cargar',
            'expedientes.crear',
            'expedientes.editar',
            'expedientes.eliminar',
            'expedientes.revertir_importacion',
            'expedientes.ver',
            'observaciones.atender',
            'observaciones.crear',
            'observaciones.validar',
            'reportes.bitacora',
            'reportes.documentales',
            'reportes.exportar',
            'reportes.exportar_excel',
            'reportes.exportar_pdf',
            'reportes.inventario',
            'reportes.plantillas',
            'reportes.valores',
            'seguridad.administrar',
            'ubicaciones.administrar',
            'ubicaciones.aprobar_traslados',
            'ubicaciones.cerrar_inventario',
            'ubicaciones.ver',
            'valores.administrar',
            'valores.ver',
        ];
    }
};

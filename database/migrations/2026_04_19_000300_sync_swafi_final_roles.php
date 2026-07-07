<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('roles') ||
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $now = now();

        /*
        |--------------------------------------------------------------------------
        | 1. Permisos finales de SWAFI
        |--------------------------------------------------------------------------
        */

        $permissions = [
            'dashboard.ver' => 'Visualizar dashboard principal.',
            'expedientes.ver' => 'Consultar expedientes y detalle de activo fijo.',
            'expedientes.crear' => 'Crear expedientes de activo fijo.',
            'expedientes.editar' => 'Editar expedientes de activo fijo.',
            'documentos.cargar' => 'Cargar documentos PDF/XML asociados al expediente.',
            'valores.administrar' => 'Administrar valores fiscales y financieros.',
            'ubicaciones.administrar' => 'Administrar ubicación física, movimientos e inventarios.',
            'reportes.exportar' => 'Consultar y exportar reportes.',
            'catalogos.administrar' => 'Administrar catálogos base del sistema.',
            'seguridad.administrar' => 'Administrar usuarios, roles y permisos.',
            'bitacora.ver' => 'Consultar bitácora de auditoría.',
        ];

        foreach ($permissions as $clave => $descripcion) {
            DB::table('permissions')->updateOrInsert(
                ['clave' => $clave],
                [
                    'descripcion' => $descripcion,
                    'updated_at' => $now,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Roles finales autorizados para SWAFI
        |--------------------------------------------------------------------------
        */

        $finalRoles = [
            'Administrador SWAFI' => [
                'descripcion' => 'Perfil máximo del sistema. Administra seguridad, usuarios, roles, permisos, catálogos, bitácora y todos los módulos.',
                'permissions' => array_keys($permissions),
            ],

            'Usuario Captura' => [
                'descripcion' => 'Usuario de Contabilidad encargado del registro individual, registro masivo, carga documental y valores fiscales/financieros.',
                'permissions' => [
                    'dashboard.ver',
                    'expedientes.ver',
                    'expedientes.crear',
                    'expedientes.editar',
                    'documentos.cargar',
                    'valores.administrar',
                ],
            ],

            'Usuario Consulta / Auditoría' => [
                'descripcion' => 'Usuario enfocado en consulta de expedientes, revisión documental, reportes, exportaciones y trazabilidad.',
                'permissions' => [
                    'dashboard.ver',
                    'expedientes.ver',
                    'reportes.exportar',
                    'bitacora.ver',
                ],
            ],

            'Usuario Planta / Inventarios' => [
                'descripcion' => 'Usuario operativo de Planta/Mantenimiento para consulta de activos, ubicación física, movimientos e inventarios.',
                'permissions' => [
                    'dashboard.ver',
                    'expedientes.ver',
                    'ubicaciones.administrar',
                    'reportes.exportar',
                ],
            ],
        ];

        foreach ($finalRoles as $nombre => $data) {
            DB::table('roles')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'descripcion' => $data['descripcion'],
                    'activo' => 1,
                    'updated_at' => $now,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Eliminar roles anteriores de prueba
        |--------------------------------------------------------------------------
        | Como todavía no existen asignaciones oficiales, se eliminan para evitar
        | confusión visual y operativa.
        */

        $finalRoleNames = array_keys($finalRoles);

        $rolesLegacy = DB::table('roles')
            ->whereNotIn('nombre', $finalRoleNames)
            ->pluck('id');

        if ($rolesLegacy->isNotEmpty()) {
            if (Schema::hasTable('role_user')) {
                DB::table('role_user')
                    ->whereIn('role_id', $rolesLegacy)
                    ->delete();
            }

            DB::table('permission_role')
                ->whereIn('role_id', $rolesLegacy)
                ->delete();

            DB::table('roles')
                ->whereIn('id', $rolesLegacy)
                ->delete();
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Reasignar permisos correctos a cada rol final
        |--------------------------------------------------------------------------
        */

        foreach ($finalRoles as $roleName => $data) {
            $roleId = DB::table('roles')
                ->where('nombre', $roleName)
                ->value('id');

            if (!$roleId) {
                continue;
            }

            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->delete();

            foreach ($data['permissions'] as $permissionClave) {
                $permissionId = DB::table('permissions')
                    ->where('clave', $permissionClave)
                    ->value('id');

                if (!$permissionId) {
                    continue;
                }

                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Asegurar que admin.swafi tenga el rol Administrador SWAFI
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('users') && Schema::hasTable('role_user')) {
            $adminUserId = DB::table('users')
                ->where('usuario', 'admin.swafi')
                ->orWhere('email', 'admin.swafi@bimbo.local')
                ->value('id');

            $adminRoleId = DB::table('roles')
                ->where('nombre', 'Administrador SWAFI')
                ->value('id');

            if ($adminUserId && $adminRoleId) {
                DB::table('role_user')
                    ->where('user_id', $adminUserId)
                    ->delete();

                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $adminUserId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Bitácora de ajuste
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => session('swafi_user_id'),
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'NORMALIZACION_ROLES_SWAFI',
                'tabla_afectada' => 'roles',
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode([
                    'roles_finales' => $finalRoleNames,
                    'descripcion' => 'Se conservaron únicamente los cuatro roles finales del proyecto SWAFI.',
                ], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        /*
         * Esta migración corrige datos de configuración de seguridad.
         * No se revierte automáticamente para evitar restaurar roles de prueba.
         */
    }
};

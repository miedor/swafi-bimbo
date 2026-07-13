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

        $permissions = [
            'dashboard.ver' => 'Visualizar el dashboard principal.',
            'expedientes.ver' => 'Consultar expedientes y detalle del activo fijo.',
            'expedientes.crear' => 'Registrar expedientes individuales y masivos.',
            'expedientes.editar' => 'Editar expedientes de activo fijo.',
            'expedientes.eliminar' => 'Eliminar expedientes conforme a las reglas del sistema.',
            'documentos.cargar' => 'Cargar, reemplazar o retirar documentos del expediente.',
            'valores.ver' => 'Consultar valores fiscales y financieros en modo lectura.',
            'valores.administrar' => 'Crear, editar, importar y eliminar valores fiscales y financieros.',
            'cfdi.validar' => 'Ejecutar la validación técnica y conciliación del XML CFDI.',
            'ubicaciones.administrar' => 'Administrar ubicación física, movimientos, inventarios y evidencias.',
            'observaciones.crear' => 'Registrar y asignar observaciones de seguimiento.',
            'observaciones.atender' => 'Atender observaciones asignadas.',
            'observaciones.validar' => 'Validar, cerrar, rechazar o cancelar observaciones.',
            'reportes.exportar' => 'Acceder al centro de reportes y exportaciones autorizadas.',
            'reportes.documentales' => 'Consultar reportes documentales.',
            'reportes.valores' => 'Consultar reportes fiscales y financieros.',
            'reportes.inventario' => 'Consultar reportes de ubicación, inventario y discrepancias.',
            'reportes.bitacora' => 'Consultar reportes de actividad y bitácora.',
            'reportes.exportar_excel' => 'Exportar reportes autorizados a Excel.',
            'reportes.exportar_pdf' => 'Exportar reportes autorizados a PDF.',
            'reportes.plantillas' => 'Guardar y administrar parámetros personales de reporte.',
            'catalogos.administrar' => 'Administrar catálogos base del sistema.',
            'seguridad.administrar' => 'Administrar usuarios, roles y permisos.',
            'bitacora.ver' => 'Consultar la bitácora de auditoría.',
        ];

        foreach ($permissions as $clave => $descripcion) {
            DB::table('permissions')->updateOrInsert(
                ['clave' => $clave],
                [
                    'descripcion' => $descripcion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $roles = [
            'Administrador SWAFI' => [
                'descripcion' => 'Perfil máximo del sistema con acceso integral a operación, seguridad, catálogos, reportes y bitácora.',
                'permissions' => array_keys($permissions),
            ],
            'Usuario Captura' => [
                'descripcion' => 'Registro individual y masivo de expedientes, documentos, validación CFDI y valores fiscales y financieros.',
                'permissions' => [
                    'cfdi.validar',
                    'dashboard.ver',
                    'documentos.cargar',
                    'expedientes.crear',
                    'expedientes.editar',
                    'expedientes.ver',
                    'observaciones.atender',
                    'valores.administrar',
                    'valores.ver',
                ],
            ],
            'Usuario Consulta / Auditoría' => [
                'descripcion' => 'Consulta, reportes, exportación, generación y validación de observaciones y revisión de trazabilidad.',
                'permissions' => [
                    'bitacora.ver',
                    'cfdi.validar',
                    'dashboard.ver',
                    'expedientes.ver',
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
                    'valores.ver',
                ],
            ],
            'Usuario Planta / Inventarios' => [
                'descripcion' => 'Consulta y seguimiento de ubicación física e inventarios, atención de observaciones operativas y reportes autorizados.',
                'permissions' => [
                    'dashboard.ver',
                    'expedientes.ver',
                    'observaciones.atender',
                    'reportes.exportar',
                    'reportes.exportar_excel',
                    'reportes.exportar_pdf',
                    'reportes.inventario',
                    'reportes.plantillas',
                    'ubicaciones.administrar',
                    'valores.ver',
                ],
            ],
        ];

        foreach ($roles as $nombre => $configuration) {
            DB::table('roles')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'descripcion' => $configuration['descripcion'],
                    'activo' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $roleId = DB::table('roles')
                ->where('nombre', $nombre)
                ->value('id');

            if (!$roleId) {
                continue;
            }

            /*
            |------------------------------------------------------------------
            | Sincronización exacta
            |------------------------------------------------------------------
            | Se elimina cualquier permiso residual del rol y se reconstruye
            | con la matriz aprobada para evitar privilegios faltantes o extras.
            */
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->delete();

            $permissionIds = DB::table('permissions')
                ->whereIn('clave', $configuration['permissions'])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Asegurar el rol del usuario técnico admin.swafi
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

                DB::table('role_user')->insert([
                    'user_id' => $adminUserId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'SINCRONIZACION_PERMISOS_ROLES',
                'tabla_afectada' => 'permission_role',
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode([
                    'roles' => array_map(
                        fn (array $item) => $item['permissions'],
                        $roles
                    ),
                    'total_permisos' => count($permissions),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => null,
                'fecha_evento' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        /*
         * No se revierte automáticamente una matriz de seguridad, porque hacerlo
         * podría restaurar permisos inconsistentes. La reversión debe realizarse
         * mediante otra migración explícita y auditada.
         */
    }
};

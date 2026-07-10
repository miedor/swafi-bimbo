<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('expediente_observaciones')) {
            Schema::table('expediente_observaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('expediente_observaciones', 'rol_destino')) {
                    $table->string('rol_destino', 50)->nullable()->after('prioridad');
                }

                if (!Schema::hasColumn('expediente_observaciones', 'asignado_a')) {
                    $table->foreignId('asignado_a')->nullable()->after('rol_destino')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('expediente_observaciones', 'fecha_asignacion')) {
                    $table->timestamp('fecha_asignacion')->nullable()->after('fecha_atencion');
                }

                if (!Schema::hasColumn('expediente_observaciones', 'fecha_notificacion')) {
                    $table->timestamp('fecha_notificacion')->nullable()->after('fecha_cancelacion');
                }

                if (!Schema::hasColumn('expediente_observaciones', 'notificacion_error')) {
                    $table->text('notificacion_error')->nullable()->after('fecha_notificacion');
                }
            });

            DB::table('expediente_observaciones')
                ->whereNull('rol_destino')
                ->update([
                    'rol_destino' => DB::raw("CASE WHEN tipo_observacion IN ('falta_ubicacion','ubicacion_incorrecta') THEN 'Usuario Planta / Inventarios' ELSE 'Usuario Captura' END"),
                    'updated_at' => $now,
                ]);
        }

        if (
            !Schema::hasTable('roles') ||
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $permissions = [
            'observaciones.crear' => 'Registrar observaciones de control documental, financiero u operativo para seguimiento cruzado.',
            'observaciones.atender' => 'Atender observaciones asignadas por Consulta/Auditoría.',
            'observaciones.validar' => 'Validar, cerrar, rechazar o cancelar observaciones atendidas.',
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

        $rolePermissions = [
            'Administrador SWAFI' => [
                'observaciones.crear',
                'observaciones.atender',
                'observaciones.validar',
            ],
            'Usuario Consulta / Auditoría' => [
                'observaciones.crear',
                'observaciones.validar',
            ],
            'Usuario Captura' => [
                'observaciones.atender',
            ],
            'Usuario Planta / Inventarios' => [
                'observaciones.atender',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionKeys) {
            $roleId = DB::table('roles')->where('nombre', $roleName)->value('id');

            if (!$roleId) {
                continue;
            }

            foreach ($permissionKeys as $permissionKey) {
                $permissionId = DB::table('permissions')->where('clave', $permissionKey)->value('id');

                if (!$permissionId) {
                    continue;
                }

                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => session('swafi_user_id'),
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'ACTUALIZACION_PERMISOS_OBSERVACIONES',
                'tabla_afectada' => 'permissions',
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode([
                    'permisos' => array_keys($permissions),
                    'descripcion' => 'Se separaron permisos de crear, atender y validar observaciones por rol.',
                ], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_role') && Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('clave', [
                    'observaciones.crear',
                    'observaciones.atender',
                    'observaciones.validar',
                ])
                ->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                DB::table('permission_role')
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }
        }
    }
};

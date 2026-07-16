<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('movimientos_ubicacion')
            && Schema::hasColumn('movimientos_ubicacion', 'motivo')
        ) {
            Schema::table('movimientos_ubicacion', function (Blueprint $table) {
                $table->string('motivo', 500)->nullable()->change();
            });
        }

        if (!Schema::hasTable('periodos_inventario')) {
            Schema::create('periodos_inventario', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('planta_id')->constrained('plantas')->restrictOnDelete();
                $table->string('nombre', 120);
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->string('estatus', 20)->default('abierto');
                $table->text('observaciones')->nullable();
                $table->text('motivo_bloqueo')->nullable();
                $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('bloqueado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('bloqueado_at')->nullable();
                $table->foreignId('desbloqueado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('desbloqueado_at')->nullable();
                $table->timestamps();

                $table->index(['planta_id', 'fecha_inicio', 'fecha_fin'], 'idx_periodo_inventario_planta_fechas');
                $table->index(['estatus', 'fecha_inicio', 'fecha_fin'], 'idx_periodo_inventario_estatus_fechas');
            });
        }

        if (!Schema::hasTable('solicitudes_traslado')) {
            Schema::create('solicitudes_traslado', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('numero_activo', 30);
                $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
                $table->foreignId('ubicacion_destino_id')->constrained('ubicaciones')->restrictOnDelete();
                $table->foreignId('responsable_destino_id')->nullable()->constrained('responsables')->nullOnDelete();
                $table->timestamp('fecha_movimiento');
                $table->string('motivo', 500);
                $table->text('evidencia')->nullable();
                $table->string('estatus', 20)->default('pendiente');
                $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('solicitado_at');
                $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resuelto_at')->nullable();
                $table->text('comentario_resolucion')->nullable();
                $table->foreignId('movimiento_id')->nullable()->constrained('movimientos_ubicacion')->nullOnDelete();
                $table->timestamps();

                $table->foreign('numero_activo')
                    ->references('numero_activo')
                    ->on('activos')
                    ->cascadeOnDelete();

                $table->index(['estatus', 'solicitado_at'], 'idx_solicitud_traslado_estatus_fecha');
                $table->index(['numero_activo', 'estatus'], 'idx_solicitud_traslado_activo_estatus');
            });
        }

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            $now = now();

            $permissions = [
                'ubicaciones.ver' => 'Consultar ubicación física, movimientos, inventarios y solicitudes de traslado.',
                'ubicaciones.aprobar_traslados' => 'Aprobar o rechazar solicitudes de traslado entre plantas.',
                'ubicaciones.cerrar_inventario' => 'Crear, bloquear y desbloquear periodos de cierre de inventario.',
            ];

            foreach ($permissions as $key => $description) {
                DB::table('permissions')->updateOrInsert(
                    ['clave' => $key],
                    [
                        'descripcion' => $description,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $rolePermissions = [
                'Administrador SWAFI' => array_keys($permissions),
                'Usuario Captura' => [
                    'ubicaciones.ver',
                    'ubicaciones.aprobar_traslados',
                ],
                'Usuario Consulta / Auditoría' => ['ubicaciones.ver'],
                'Usuario Planta / Inventarios' => ['ubicaciones.ver'],
            ];

            foreach ($rolePermissions as $roleName => $permissionKeys) {
                $roleId = DB::table('roles')->where('nombre', $roleName)->value('id');

                if (!$roleId) {
                    continue;
                }

                $permissionIds = DB::table('permissions')
                    ->whereIn('clave', $permissionKeys)
                    ->pluck('id');

                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => 'HABILITACION_APROBACION_TRASLADOS_Y_CIERRES_INVENTARIO',
                'tabla_afectada' => 'solicitudes_traslado,periodos_inventario',
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-053', 'HU-056'],
                    'permisos' => [
                        'ubicaciones.ver',
                        'ubicaciones.aprobar_traslados',
                        'ubicaciones.cerrar_inventario',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            $permissionIds = DB::table('permissions')
                ->whereIn('clave', [
                    'ubicaciones.ver',
                    'ubicaciones.aprobar_traslados',
                    'ubicaciones.cerrar_inventario',
                ])
                ->pluck('id');

            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }

        Schema::dropIfExists('solicitudes_traslado');
        Schema::dropIfExists('periodos_inventario');

        if (
            Schema::hasTable('movimientos_ubicacion')
            && Schema::hasColumn('movimientos_ubicacion', 'motivo')
        ) {
            Schema::table('movimientos_ubicacion', function (Blueprint $table) {
                $table->string('motivo', 120)->nullable()->change();
            });
        }
    }
};

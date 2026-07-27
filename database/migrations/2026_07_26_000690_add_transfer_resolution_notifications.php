<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitudes_traslado')) {
            return;
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'notificacion_solicitante_at')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->timestamp('notificacion_solicitante_at')
                    ->nullable()
                    ->after('notificacion_aprobador_error');
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'ultimo_intento_notificacion_solicitante_at')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->timestamp('ultimo_intento_notificacion_solicitante_at')
                    ->nullable()
                    ->after('notificacion_solicitante_at');
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'notificacion_solicitante_intentos')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->unsignedSmallInteger('notificacion_solicitante_intentos')
                    ->default(0)
                    ->after('ultimo_intento_notificacion_solicitante_at');
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'notificacion_solicitante_error')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->text('notificacion_solicitante_error')
                    ->nullable()
                    ->after('notificacion_solicitante_intentos');
            });
        }

        if (!Schema::hasIndex('solicitudes_traslado', 'idx_traslado_aprobador_estatus')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->index(
                    ['aprobador_asignado_id', 'estatus'],
                    'idx_traslado_aprobador_estatus'
                );
            });
        }

        if (!Schema::hasIndex('solicitudes_traslado', 'idx_traslado_solicitante_estatus')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->index(
                    ['solicitado_por', 'estatus'],
                    'idx_traslado_solicitante_estatus'
                );
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            $now = now();

            DB::table('bitacora_auditoria')->updateOrInsert(
                [
                    'accion' => 'HABILITA_NOTIF_RESOL_TRASLADO',
                    'tabla_afectada' => 'solicitudes_traslado',
                ],
                [
                    'numero_activo' => null,
                    'user_id' => null,
                    'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                    'registro_clave' => null,
                    'antes' => null,
                    'despues' => json_encode([
                        'funcionalidad' => 'Notificación al solicitante después de aprobar o rechazar un traslado entre plantas y bandejas de seguimiento en Dashboard.',
                        'campos' => [
                            'notificacion_solicitante_at',
                            'ultimo_intento_notificacion_solicitante_at',
                            'notificacion_solicitante_intentos',
                            'notificacion_solicitante_error',
                        ],
                        'indices' => [
                            'idx_traslado_aprobador_estatus',
                            'idx_traslado_solicitante_estatus',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'ip' => null,
                    'fecha_evento' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', 'HABILITA_NOTIF_RESOL_TRASLADO')
                ->where('tabla_afectada', 'solicitudes_traslado')
                ->delete();
        }

        if (!Schema::hasTable('solicitudes_traslado')) {
            return;
        }

        if (Schema::hasIndex('solicitudes_traslado', 'idx_traslado_aprobador_estatus')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->dropIndex('idx_traslado_aprobador_estatus');
            });
        }

        if (Schema::hasIndex('solicitudes_traslado', 'idx_traslado_solicitante_estatus')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->dropIndex('idx_traslado_solicitante_estatus');
            });
        }

        $columns = [
            'notificacion_solicitante_at',
            'ultimo_intento_notificacion_solicitante_at',
            'notificacion_solicitante_intentos',
            'notificacion_solicitante_error',
        ];

        $existingColumns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn('solicitudes_traslado', $column)
        ));

        if ($existingColumns !== []) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }
};

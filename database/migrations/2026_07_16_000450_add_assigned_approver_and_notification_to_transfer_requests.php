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

        if (!Schema::hasColumn('solicitudes_traslado', 'aprobador_asignado_id')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->foreignId('aprobador_asignado_id')
                    ->nullable()
                    ->after('responsable_destino_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'notificacion_aprobador_at')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->timestamp('notificacion_aprobador_at')
                    ->nullable()
                    ->after('solicitado_at');
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'ultimo_intento_notificacion_at')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->timestamp('ultimo_intento_notificacion_at')
                    ->nullable()
                    ->after('notificacion_aprobador_at');
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'notificacion_aprobador_intentos')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->unsignedSmallInteger('notificacion_aprobador_intentos')
                    ->default(0)
                    ->after('ultimo_intento_notificacion_at');
            });
        }

        if (!Schema::hasColumn('solicitudes_traslado', 'notificacion_aprobador_error')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->text('notificacion_aprobador_error')
                    ->nullable()
                    ->after('notificacion_aprobador_intentos');
            });
        }

        /*
         * Las solicitudes creadas antes de esta adaptación se conservan sin una
         * asignación automática. No se elige una persona arbitrariamente. El
         * Administrador SWAFI podrá resolverlas y las solicitudes nuevas exigirán
         * seleccionar un Usuario Captura activo.
         */
        if (Schema::hasTable('bitacora_auditoria')) {
            $now = now();

            DB::table('bitacora_auditoria')->updateOrInsert(
                [
                    'accion' => 'HABILITA_APROBADOR_TRASLADO',
                    'tabla_afectada' => 'solicitudes_traslado',
                ],
                [
                    'numero_activo' => null,
                    'user_id' => null,
                    'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                    'registro_clave' => null,
                    'antes' => null,
                    'despues' => json_encode([
                        'funcionalidad' => 'Asignación individual de Usuario Captura y notificación de solicitud de traslado.',
                        'campos' => [
                            'aprobador_asignado_id',
                            'notificacion_aprobador_at',
                            'ultimo_intento_notificacion_at',
                            'notificacion_aprobador_intentos',
                            'notificacion_aprobador_error',
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
                ->where('accion', 'HABILITA_APROBADOR_TRASLADO')
                ->where('tabla_afectada', 'solicitudes_traslado')
                ->delete();
        }

        if (!Schema::hasTable('solicitudes_traslado')) {
            return;
        }

        if (Schema::hasColumn('solicitudes_traslado', 'aprobador_asignado_id')) {
            Schema::table('solicitudes_traslado', function (Blueprint $table) {
                $table->dropConstrainedForeignId('aprobador_asignado_id');
            });
        }

        $columns = [
            'notificacion_aprobador_at',
            'ultimo_intento_notificacion_at',
            'notificacion_aprobador_intentos',
            'notificacion_aprobador_error',
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

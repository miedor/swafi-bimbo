<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VALIDATION_INDEX = 'idx_obs_validation_queue';

    private const AUDIT_ACTION = 'HABILITA_AVISO_REVISION_OBS';

    public function up(): void
    {
        if (!Schema::hasTable('expediente_observaciones')) {
            return;
        }

        Schema::table('expediente_observaciones', function (Blueprint $table): void {
            if (!Schema::hasColumn('expediente_observaciones', 'fecha_notificacion_revision')) {
                $table->timestamp('fecha_notificacion_revision')
                    ->nullable()
                    ->after('fecha_notificacion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'ultimo_intento_notificacion_revision_at')) {
                $table->timestamp('ultimo_intento_notificacion_revision_at')
                    ->nullable()
                    ->after('fecha_notificacion_revision');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'notificacion_revision_intentos')) {
                $table->unsignedInteger('notificacion_revision_intentos')
                    ->default(0)
                    ->after('ultimo_intento_notificacion_revision_at');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'notificacion_revision_error_referencia')) {
                $table->string('notificacion_revision_error_referencia', 80)
                    ->nullable()
                    ->after('notificacion_revision_intentos');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'fecha_notificacion_resolucion')) {
                $table->timestamp('fecha_notificacion_resolucion')
                    ->nullable()
                    ->after('notificacion_revision_error_referencia');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'ultimo_intento_notificacion_resolucion_at')) {
                $table->timestamp('ultimo_intento_notificacion_resolucion_at')
                    ->nullable()
                    ->after('fecha_notificacion_resolucion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'notificacion_resolucion_intentos')) {
                $table->unsignedInteger('notificacion_resolucion_intentos')
                    ->default(0)
                    ->after('ultimo_intento_notificacion_resolucion_at');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'notificacion_resolucion_error_referencia')) {
                $table->string('notificacion_resolucion_error_referencia', 80)
                    ->nullable()
                    ->after('notificacion_resolucion_intentos');
            }
        });

        if (!$this->indexExists(self::VALIDATION_INDEX)) {
            Schema::table('expediente_observaciones', function (Blueprint $table): void {
                $table->index(
                    ['estatus', 'creado_por', 'fecha_atencion'],
                    self::VALIDATION_INDEX
                );
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            $now = now();

            DB::table('bitacora_auditoria')->updateOrInsert(
                [
                    'accion' => self::AUDIT_ACTION,
                    'tabla_afectada' => 'expediente_observaciones',
                    'registro_clave' => 'HU-014-VALIDACION',
                ],
                [
                    'numero_activo' => null,
                    'user_id' => null,
                    'modulo' => 'M03 Consultas, reportes y seguimiento',
                    'antes' => null,
                    'despues' => json_encode([
                        'flujo' => 'atencion_validacion_resolucion',
                        'notificacion_revision' => true,
                        'notificacion_resolucion' => true,
                        'bandeja_dashboard' => true,
                    ], JSON_UNESCAPED_UNICODE),
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
        if (!Schema::hasTable('expediente_observaciones')) {
            return;
        }

        if ($this->indexExists(self::VALIDATION_INDEX)) {
            Schema::table('expediente_observaciones', function (Blueprint $table): void {
                $table->dropIndex(self::VALIDATION_INDEX);
            });
        }

        $columns = array_values(array_filter([
            'fecha_notificacion_revision',
            'ultimo_intento_notificacion_revision_at',
            'notificacion_revision_intentos',
            'notificacion_revision_error_referencia',
            'fecha_notificacion_resolucion',
            'ultimo_intento_notificacion_resolucion_at',
            'notificacion_resolucion_intentos',
            'notificacion_resolucion_error_referencia',
        ], static fn (string $column): bool => Schema::hasColumn(
            'expediente_observaciones',
            $column
        )));

        if ($columns !== []) {
            Schema::table('expediente_observaciones', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('tabla_afectada', 'expediente_observaciones')
                ->where('registro_clave', 'HU-014-VALIDACION')
                ->delete();
        }
    }

    private function indexExists(string $indexName): bool
    {
        foreach (Schema::getIndexes('expediente_observaciones') as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};

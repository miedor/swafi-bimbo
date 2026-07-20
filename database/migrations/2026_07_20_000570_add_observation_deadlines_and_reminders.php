<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'idx_obs_followup_due';

    private const AUDIT_ACTION = 'HABILITA_PLAZOS_OBSERVACIONES';

    public function up(): void
    {
        if (!Schema::hasTable('expediente_observaciones')) {
            return;
        }

        Schema::table('expediente_observaciones', function (Blueprint $table): void {
            if (!Schema::hasColumn('expediente_observaciones', 'fecha_compromiso')) {
                $table->date('fecha_compromiso')
                    ->nullable()
                    ->after('fecha_asignacion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'ultimo_intento_recordatorio_at')) {
                $table->timestamp('ultimo_intento_recordatorio_at')
                    ->nullable()
                    ->after('fecha_notificacion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'fecha_ultimo_recordatorio')) {
                $table->timestamp('fecha_ultimo_recordatorio')
                    ->nullable()
                    ->after('ultimo_intento_recordatorio_at');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'recordatorios_enviados')) {
                $table->unsignedSmallInteger('recordatorios_enviados')
                    ->default(0)
                    ->after('fecha_ultimo_recordatorio');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'recordatorio_error_referencia')) {
                $table->string('recordatorio_error_referencia', 16)
                    ->nullable()
                    ->after('recordatorios_enviados');
            }
        });

        if (!$this->indexExists(self::INDEX_NAME)) {
            Schema::table('expediente_observaciones', function (Blueprint $table): void {
                $table->index(
                    ['estatus', 'fecha_compromiso', 'ultimo_intento_recordatorio_at'],
                    self::INDEX_NAME
                );
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            $now = now();

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M01 Gestión de expedientes de activo fijo',
                'accion' => self::AUDIT_ACTION,
                'tabla_afectada' => 'expediente_observaciones',
                'registro_clave' => 'HU-014',
                'antes' => null,
                'despues' => json_encode([
                    'fecha_compromiso' => true,
                    'recordatorios_automaticos' => true,
                    'descripcion' => 'Se habilitaron fechas compromiso y recordatorios automáticos para observaciones pendientes.',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('tabla_afectada', 'expediente_observaciones')
                ->where('registro_clave', 'HU-014')
                ->delete();
        }

        if (!Schema::hasTable('expediente_observaciones')) {
            return;
        }

        if ($this->indexExists(self::INDEX_NAME)) {
            Schema::table('expediente_observaciones', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_NAME);
            });
        }

        $columns = array_values(array_filter([
            'recordatorio_error_referencia',
            'recordatorios_enviados',
            'fecha_ultimo_recordatorio',
            'ultimo_intento_recordatorio_at',
            'fecha_compromiso',
        ], static fn (string $column): bool => Schema::hasColumn(
            'expediente_observaciones',
            $column
        )));

        if ($columns !== []) {
            Schema::table('expediente_observaciones', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
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

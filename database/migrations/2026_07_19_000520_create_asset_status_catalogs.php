<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AUDIT_ACTION = 'HABILITA_CATALOGOS_ESTATUS';

    private const FK_ASSET_DOCUMENTARY = 'fk_activos_estatus_documental';

    private const FK_ASSET_OPERATIONAL = 'fk_activos_estatus_operativo';

    private const FK_FILE_DOCUMENTARY = 'fk_expedientes_estatus_documental';

    private const DOCUMENTARY_DEFAULTS = [
        'completo' => ['Completo', 'El expediente cuenta con la documentación base requerida.', 10],
        'incompleto' => ['Incompleto', 'El expediente aún no cuenta con todos los documentos requeridos.', 20],
        'observado' => ['Observado', 'El expediente presenta una inconsistencia o requiere seguimiento.', 30],
    ];

    private const OPERATIONAL_DEFAULTS = [
        'en_operacion' => ['En operación', 'El activo se encuentra disponible en su ubicación operativa.', 10],
        'traslado' => ['Traslado', 'El activo se encuentra en proceso de traslado o reubicación controlada.', 20],
        'baja' => ['Baja', 'El activo ya no se encuentra disponible para la operación ordinaria.', 30],
    ];

    public function up(): void
    {
        $this->createStatusTable('estatus_documentales', 'idx_est_doc_estado_orden');
        $this->createStatusTable('estatus_operativos', 'idx_est_op_estado_orden');

        $this->syncDocumentaryStatuses();
        $this->syncOperationalStatuses();
        $this->addReferentialIntegrity();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-101,HU-102,HU-105')
                ->delete();
        }

        $this->dropForeignIfExists('expedientes', self::FK_FILE_DOCUMENTARY);
        $this->dropForeignIfExists('activos', self::FK_ASSET_DOCUMENTARY);
        $this->dropForeignIfExists('activos', self::FK_ASSET_OPERATIONAL);

        Schema::dropIfExists('estatus_operativos');
        Schema::dropIfExists('estatus_documentales');
    }

    private function createStatusTable(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($indexName): void {
                $table->id();
                $table->string('clave', 20)->unique();
                $table->string('nombre', 80)->unique();
                $table->string('descripcion', 255)->nullable();
                $table->unsignedSmallInteger('orden')->default(100);
                $table->boolean('es_sistema')->default(false);
                $table->string('estatus', 20)->default('activo');
                $table->timestamps();

                $table->index(['estatus', 'orden'], $indexName);
            });

            return;
        }

        if (!$this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->index(['estatus', 'orden'], $indexName);
            });
        }
    }

    private function syncDocumentaryStatuses(): void
    {
        $this->syncBaseStatuses('estatus_documentales', self::DOCUMENTARY_DEFAULTS);

        $keys = collect();

        if (Schema::hasTable('activos') && Schema::hasColumn('activos', 'estatus_documental')) {
            $keys = $keys->merge(DB::table('activos')->distinct()->pluck('estatus_documental'));
        }

        if (Schema::hasTable('expedientes') && Schema::hasColumn('expedientes', 'estatus')) {
            $keys = $keys->merge(DB::table('expedientes')->distinct()->pluck('estatus'));
        }

        $this->syncHistoricalKeys('estatus_documentales', $keys);
    }

    private function syncOperationalStatuses(): void
    {
        $this->syncBaseStatuses('estatus_operativos', self::OPERATIONAL_DEFAULTS);

        $keys = collect();

        if (Schema::hasTable('activos') && Schema::hasColumn('activos', 'estatus_operativo')) {
            $keys = $keys->merge(DB::table('activos')->distinct()->pluck('estatus_operativo'));
        }

        $this->syncHistoricalKeys('estatus_operativos', $keys);
    }

    private function syncBaseStatuses(string $table, array $defaults): void
    {
        foreach ($defaults as $key => [$name, $description, $order]) {
            DB::table($table)->updateOrInsert(
                ['clave' => $key],
                [
                    'nombre' => $name,
                    'descripcion' => $description,
                    'orden' => $order,
                    'es_sistema' => true,
                    'estatus' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function syncHistoricalKeys(string $table, iterable $keys): void
    {
        foreach (collect($keys)->filter()->map(fn ($key) => trim((string) $key))->unique() as $key) {
            if ($key === '' || mb_strlen($key) > 20 || DB::table($table)->where('clave', $key)->exists()) {
                continue;
            }

            DB::table($table)->insert([
                'clave' => $key,
                'nombre' => mb_substr('Estatus histórico: ' . $key, 0, 80),
                'descripcion' => 'Valor conservado automáticamente durante la migración para evitar pérdida de datos históricos.',
                'orden' => 900,
                'es_sistema' => false,
                'estatus' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function addReferentialIntegrity(): void
    {
        if (
            Schema::hasTable('activos')
            && Schema::hasTable('estatus_documentales')
            && Schema::hasColumn('activos', 'estatus_documental')
            && !$this->foreignKeyExists('activos', self::FK_ASSET_DOCUMENTARY)
        ) {
            Schema::table('activos', function (Blueprint $table): void {
                $table->foreign('estatus_documental', self::FK_ASSET_DOCUMENTARY)
                    ->references('clave')
                    ->on('estatus_documentales')
                    ->restrictOnDelete()
                    ->onUpdate('restrict');
            });
        }

        if (
            Schema::hasTable('activos')
            && Schema::hasTable('estatus_operativos')
            && Schema::hasColumn('activos', 'estatus_operativo')
            && !$this->foreignKeyExists('activos', self::FK_ASSET_OPERATIONAL)
        ) {
            Schema::table('activos', function (Blueprint $table): void {
                $table->foreign('estatus_operativo', self::FK_ASSET_OPERATIONAL)
                    ->references('clave')
                    ->on('estatus_operativos')
                    ->restrictOnDelete()
                    ->onUpdate('restrict');
            });
        }

        if (
            Schema::hasTable('expedientes')
            && Schema::hasTable('estatus_documentales')
            && Schema::hasColumn('expedientes', 'estatus')
            && !$this->foreignKeyExists('expedientes', self::FK_FILE_DOCUMENTARY)
        ) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->foreign('estatus', self::FK_FILE_DOCUMENTARY)
                    ->references('clave')
                    ->on('estatus_documentales')
                    ->restrictOnDelete()
                    ->onUpdate('restrict');
            });
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
                'registro_clave' => 'HU-101,HU-102,HU-105',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'estatus_documentales,estatus_operativos',
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-101', 'HU-102', 'HU-105'],
                    'estatus_base_protegidos' => true,
                    'valores_historicos_conservados' => true,
                    'integridad_referencial' => true,
                    'catalogos_activos_en_formularios' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => ($foreignKey['name'] ?? null) === $foreignKeyName);
    }

    private function dropForeignIfExists(string $table, string $foreignKeyName): void
    {
        if (!Schema::hasTable($table) || !$this->foreignKeyExists($table, $foreignKeyName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($foreignKeyName): void {
            $table->dropForeign($foreignKeyName);
        });
    }
};

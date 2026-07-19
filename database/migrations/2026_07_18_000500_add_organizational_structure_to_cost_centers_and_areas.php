<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AUDIT_ACTION = 'HABILITA_ESTRUCTURA_CC_AREAS';

    private const COST_CENTER_PLANT_FK = 'fk_centros_costo_planta';

    public function up(): void
    {
        $this->addCostCenterPlant();
        $this->addAreaKey();
        $this->addIndexes();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-098,HU-099,HU-105')
                ->delete();
        }

        $this->dropIndexIfExists('areas', 'uq_areas_planta_clave');
        $this->dropIndexIfExists('areas', 'idx_areas_planta_clave');
        $this->dropIndexIfExists('centros_costo', 'idx_centros_costo_planta_estatus');

        if (
            Schema::hasTable('centros_costo')
            && $this->foreignKeyExists('centros_costo', self::COST_CENTER_PLANT_FK)
        ) {
            Schema::table('centros_costo', function (Blueprint $table): void {
                $table->dropForeign(self::COST_CENTER_PLANT_FK);
            });
        }

        if (Schema::hasTable('areas') && Schema::hasColumn('areas', 'clave')) {
            Schema::table('areas', function (Blueprint $table): void {
                $table->dropColumn('clave');
            });
        }

        if (Schema::hasTable('centros_costo') && Schema::hasColumn('centros_costo', 'planta_id')) {
            Schema::table('centros_costo', function (Blueprint $table): void {
                $table->dropColumn('planta_id');
            });
        }
    }

    private function addCostCenterPlant(): void
    {
        if (!Schema::hasTable('centros_costo')) {
            return;
        }

        if (!Schema::hasColumn('centros_costo', 'planta_id')) {
            Schema::table('centros_costo', function (Blueprint $table): void {
                $table->unsignedBigInteger('planta_id')->nullable()->after('id');
            });
        }

        if (
            Schema::hasTable('plantas')
            && !$this->foreignKeyExists('centros_costo', self::COST_CENTER_PLANT_FK)
        ) {
            Schema::table('centros_costo', function (Blueprint $table): void {
                $table->foreign('planta_id', self::COST_CENTER_PLANT_FK)
                    ->references('id')
                    ->on('plantas')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }
    }

    private function addAreaKey(): void
    {
        if (!Schema::hasTable('areas') || Schema::hasColumn('areas', 'clave')) {
            return;
        }

        Schema::table('areas', function (Blueprint $table): void {
            $table->string('clave', 30)->nullable()->after('planta_id');
        });
    }

    private function addIndexes(): void
    {
        if (
            Schema::hasTable('centros_costo')
            && Schema::hasColumn('centros_costo', 'planta_id')
            && !$this->indexExists('centros_costo', 'idx_centros_costo_planta_estatus')
        ) {
            Schema::table('centros_costo', function (Blueprint $table): void {
                $table->index(
                    ['planta_id', 'estatus'],
                    'idx_centros_costo_planta_estatus'
                );
            });
        }

        if (
            Schema::hasTable('areas')
            && Schema::hasColumn('areas', 'clave')
            && !$this->indexExists('areas', 'idx_areas_planta_clave')
        ) {
            Schema::table('areas', function (Blueprint $table): void {
                $table->index(['planta_id', 'clave'], 'idx_areas_planta_clave');
            });
        }

        if (
            Schema::hasTable('areas')
            && Schema::hasColumn('areas', 'clave')
            && !$this->indexExists('areas', 'uq_areas_planta_clave')
        ) {
            Schema::table('areas', function (Blueprint $table): void {
                $table->unique(['planta_id', 'clave'], 'uq_areas_planta_clave');
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
                'registro_clave' => 'HU-098,HU-099,HU-105',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'centros_costo,areas',
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-098', 'HU-099', 'HU-105'],
                    'centro_costo_por_planta' => true,
                    'clave_area_por_planta' => true,
                    'columnas_historicas_nullable' => true,
                    'validacion_dependencias' => true,
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

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};

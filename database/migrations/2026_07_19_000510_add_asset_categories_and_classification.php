<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AUDIT_ACTION = 'HABILITA_CLASIFICACION_ACTIVOS';

    private const TYPE_CATEGORY_FK = 'fk_tipos_activo_categoria';

    public function up(): void
    {
        $this->createAssetCategoriesTable();
        $this->addCategoryToAssetTypes();
        $this->addIndexes();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-100,HU-105')
                ->delete();
        }

        $this->dropIndexIfExists('tipos_activo', 'idx_tipos_activo_categoria_estatus');
        $this->dropIndexIfExists('tipos_activo', 'idx_tipos_activo_descripcion');

        if (
            Schema::hasTable('tipos_activo')
            && $this->foreignKeyExists('tipos_activo', self::TYPE_CATEGORY_FK)
        ) {
            Schema::table('tipos_activo', function (Blueprint $table): void {
                $table->dropForeign(self::TYPE_CATEGORY_FK);
            });
        }

        if (Schema::hasTable('tipos_activo') && Schema::hasColumn('tipos_activo', 'categoria_activo_id')) {
            Schema::table('tipos_activo', function (Blueprint $table): void {
                $table->dropColumn('categoria_activo_id');
            });
        }

        Schema::dropIfExists('categorias_activo');
    }

    private function createAssetCategoriesTable(): void
    {
        if (Schema::hasTable('categorias_activo')) {
            return;
        }

        Schema::create('categorias_activo', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 30)->unique();
            $table->string('nombre', 120)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();

            $table->index(['estatus', 'nombre'], 'idx_categorias_activo_estatus_nombre');
        });
    }

    private function addCategoryToAssetTypes(): void
    {
        if (!Schema::hasTable('tipos_activo')) {
            return;
        }

        if (!Schema::hasColumn('tipos_activo', 'categoria_activo_id')) {
            Schema::table('tipos_activo', function (Blueprint $table): void {
                $table->unsignedBigInteger('categoria_activo_id')->nullable()->after('id');
            });
        }

        if (
            Schema::hasTable('categorias_activo')
            && !$this->foreignKeyExists('tipos_activo', self::TYPE_CATEGORY_FK)
        ) {
            Schema::table('tipos_activo', function (Blueprint $table): void {
                $table->foreign('categoria_activo_id', self::TYPE_CATEGORY_FK)
                    ->references('id')
                    ->on('categorias_activo')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }
    }

    private function addIndexes(): void
    {
        if (
            Schema::hasTable('tipos_activo')
            && Schema::hasColumn('tipos_activo', 'categoria_activo_id')
            && !$this->indexExists('tipos_activo', 'idx_tipos_activo_categoria_estatus')
        ) {
            Schema::table('tipos_activo', function (Blueprint $table): void {
                $table->index(
                    ['categoria_activo_id', 'estatus'],
                    'idx_tipos_activo_categoria_estatus'
                );
            });
        }

        if (
            Schema::hasTable('tipos_activo')
            && !$this->indexExists('tipos_activo', 'idx_tipos_activo_descripcion')
        ) {
            Schema::table('tipos_activo', function (Blueprint $table): void {
                $table->index('descripcion', 'idx_tipos_activo_descripcion');
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
                'registro_clave' => 'HU-100,HU-105',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'categorias_activo,tipos_activo',
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-100', 'HU-105'],
                    'categorias_controladas' => true,
                    'tipos_clasificados' => true,
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

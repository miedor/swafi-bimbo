<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'expedientes.revertir_importacion';
    private const INDEX = 'importaciones_masivas_reversion_idx';

    public function up(): void
    {
        if (!Schema::hasTable('importaciones_masivas')) {
            return;
        }

        if (!Schema::hasColumn('importaciones_masivas', 'reversion_disponible_hasta')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->timestamp('reversion_disponible_hasta')
                    ->nullable()
                    ->after('aplicada_at');
            });
        }

        if (!Schema::hasColumn('importaciones_masivas', 'revertida_at')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->timestamp('revertida_at')
                    ->nullable()
                    ->after('reversion_disponible_hasta');
            });
        }

        if (!Schema::hasColumn('importaciones_masivas', 'revertida_por')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->foreignId('revertida_por')
                    ->nullable()
                    ->after('revertida_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('importaciones_masivas', 'motivo_reversion')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->string('motivo_reversion', 500)
                    ->nullable()
                    ->after('revertida_por');
            });
        }

        if (!Schema::hasColumn('importaciones_masivas', 'reversion_resumen')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->json('reversion_resumen')
                    ->nullable()
                    ->after('motivo_reversion');
            });
        }

        if (!$this->hasIndex('importaciones_masivas', self::INDEX)) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->index(
                    ['estado', 'reversion_disponible_hasta'],
                    self::INDEX
                );
            });
        }

        $this->createPermission();
        $this->registerAudit();
    }

    public function down(): void
    {
        if (
            Schema::hasTable('permission_role')
            && Schema::hasTable('permissions')
        ) {
            $permissionId = DB::table('permissions')
                ->where('clave', self::PERMISSION)
                ->value('id');

            if ($permissionId) {
                DB::table('permission_role')
                    ->where('permission_id', $permissionId)
                    ->delete();

                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->delete();
            }
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', 'HABILITA_REVERSION_IMPORTACION')
                ->where('tabla_afectada', 'importaciones_masivas')
                ->delete();
        }

        if (!Schema::hasTable('importaciones_masivas')) {
            return;
        }

        if ($this->hasIndex('importaciones_masivas', self::INDEX)) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        if (Schema::hasColumn('importaciones_masivas', 'revertida_por')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('revertida_por');
            });
        }

        $columns = [
            'reversion_disponible_hasta',
            'revertida_at',
            'motivo_reversion',
            'reversion_resumen',
        ];

        $existingColumns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn(
                'importaciones_masivas',
                $column
            )
        ));

        if ($existingColumns !== []) {
            Schema::table('importaciones_masivas', function (Blueprint $table) use ($existingColumns): void {
                $table->dropColumn($existingColumns);
            });
        }
    }

    private function createPermission(): void
    {
        if (
            !Schema::hasTable('permissions')
            || !Schema::hasTable('roles')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['clave' => self::PERMISSION],
            [
                'descripcion' => 'Revertir de forma controlada una importación masiva reciente.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $roleId = DB::table('roles')
            ->where('nombre', 'Administrador SWAFI')
            ->where('activo', 1)
            ->value('id');
        $permissionId = DB::table('permissions')
            ->where('clave', self::PERMISSION)
            ->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function registerAudit(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        $now = now();

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => 'HABILITA_REVERSION_IMPORTACION',
                'tabla_afectada' => 'importaciones_masivas',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M01 Gestión de expedientes de activo fijo',
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode([
                    'historia_usuario' => 'HU-029',
                    'permiso' => self::PERMISSION,
                    'regla' => 'Reversión administrativa dentro de una ventana configurable y sin dependencias posteriores.',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $definition) {
                if (($definition['name'] ?? null) === $index) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
};

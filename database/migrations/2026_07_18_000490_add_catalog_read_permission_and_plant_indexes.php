<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'catalogos.ver';

    private const AUDIT_ACTION = 'HABILITA_CONSULTA_CATALOGOS';

    public function up(): void
    {
        $this->addPlantAddress();
        $this->addQueryIndexes();
        $this->createReadPermission();
        $this->assignReadPermissionToBaseRoles();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-095,HU-096,HU-097')
                ->delete();
        }

        if (
            Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
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

        $this->dropIndexIfExists('ubicaciones', 'idx_ubicaciones_planta_estatus');
        $this->dropIndexIfExists('areas', 'idx_areas_planta_estatus');
        $this->dropIndexIfExists('plantas', 'idx_plantas_estatus_nombre');

        if (Schema::hasTable('plantas') && Schema::hasColumn('plantas', 'direccion')) {
            Schema::table('plantas', function (Blueprint $table): void {
                $table->dropColumn('direccion');
            });
        }
    }

    private function addPlantAddress(): void
    {
        if (!Schema::hasTable('plantas') || Schema::hasColumn('plantas', 'direccion')) {
            return;
        }

        Schema::table('plantas', function (Blueprint $table): void {
            $table->string('direccion', 255)->nullable()->after('nombre');
        });
    }

    private function addQueryIndexes(): void
    {
        if (Schema::hasTable('plantas') && !$this->indexExists('plantas', 'idx_plantas_estatus_nombre')) {
            Schema::table('plantas', function (Blueprint $table): void {
                $table->index(['estatus', 'nombre'], 'idx_plantas_estatus_nombre');
            });
        }

        if (Schema::hasTable('areas') && !$this->indexExists('areas', 'idx_areas_planta_estatus')) {
            Schema::table('areas', function (Blueprint $table): void {
                $table->index(['planta_id', 'estatus'], 'idx_areas_planta_estatus');
            });
        }

        if (Schema::hasTable('ubicaciones') && !$this->indexExists('ubicaciones', 'idx_ubicaciones_planta_estatus')) {
            Schema::table('ubicaciones', function (Blueprint $table): void {
                $table->index(['planta_id', 'estatus'], 'idx_ubicaciones_planta_estatus');
            });
        }
    }

    private function createReadPermission(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $payload = [
            'descripcion' => 'Consultar catálogos base activos e inactivos sin administrar registros.',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('permissions', 'activo')) {
            $payload['activo'] = 1;
        }

        if (Schema::hasColumn('permissions', 'es_sistema')) {
            $payload['es_sistema'] = 1;
        }

        $permissionExists = DB::table('permissions')
            ->where('clave', self::PERMISSION)
            ->exists();

        if (!$permissionExists) {
            $payload['created_at'] = now();
        }

        DB::table('permissions')->updateOrInsert(
            ['clave' => self::PERMISSION],
            $payload
        );
    }

    private function assignReadPermissionToBaseRoles(): void
    {
        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('clave', self::PERMISSION)
            ->value('id');

        if (!$permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('nombre', [
                'Administrador SWAFI',
                'Usuario Captura',
                'Usuario Consulta / Auditoría',
                'Usuario Planta / Inventarios',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
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
                'registro_clave' => 'HU-095,HU-096,HU-097',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'permissions,permission_role,plantas',
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-095', 'HU-096', 'HU-097'],
                    'permiso_consulta' => self::PERMISSION,
                    'roles_con_consulta' => 4,
                    'direccion_planta' => true,
                    'validacion_dependencias_planta' => true,
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

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};

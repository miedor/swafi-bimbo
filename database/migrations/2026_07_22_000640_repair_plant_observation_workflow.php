<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_NAME = 'Usuario Planta / Inventarios';

    private const PERMISSION_KEY = 'observaciones.atender';

    private const INDEX_NAME = 'idx_obs_assignee_queue';

    private const AUDIT_ACTION = 'REPARA_FLUJO_OBS_PLANTA';

    public function up(): void
    {
        $now = now();

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            $permissionValues = [
                'descripcion' => 'Atender observaciones asignadas por Consulta/Auditoría.',
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('permissions', 'activo')) {
                $permissionValues['activo'] = 1;
            }

            if (Schema::hasColumn('permissions', 'es_sistema')) {
                $permissionValues['es_sistema'] = 1;
            }

            if (Schema::hasColumn('permissions', 'created_at')) {
                $permissionValues['created_at'] = $now;
            }

            DB::table('permissions')->updateOrInsert(
                ['clave' => self::PERMISSION_KEY],
                $permissionValues
            );

            $roleValues = [
                'descripcion' => 'Consulta y seguimiento de ubicación física e inventarios, atención de observaciones operativas y reportes autorizados.',
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('roles', 'activo')) {
                $roleValues['activo'] = 1;
            }

            if (Schema::hasColumn('roles', 'es_sistema')) {
                $roleValues['es_sistema'] = 1;
            }

            if (Schema::hasColumn('roles', 'created_at')) {
                $roleValues['created_at'] = $now;
            }

            DB::table('roles')->updateOrInsert(
                ['nombre' => self::ROLE_NAME],
                $roleValues
            );

            $roleId = DB::table('roles')
                ->where('nombre', self::ROLE_NAME)
                ->value('id');
            $permissionId = DB::table('permissions')
                ->where('clave', self::PERMISSION_KEY)
                ->value('id');

            if ($roleId && $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        if (
            Schema::hasTable('expediente_observaciones')
            && Schema::hasColumn('expediente_observaciones', 'asignado_a')
            && Schema::hasColumn('expediente_observaciones', 'estatus')
            && !$this->indexExists(self::INDEX_NAME)
        ) {
            Schema::table('expediente_observaciones', function (Blueprint $table): void {
                $columns = ['asignado_a', 'estatus'];

                if (Schema::hasColumn('expediente_observaciones', 'fecha_compromiso')) {
                    $columns[] = 'fecha_compromiso';
                }

                $table->index($columns, self::INDEX_NAME);
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')->updateOrInsert(
                [
                    'accion' => self::AUDIT_ACTION,
                    'tabla_afectada' => 'permission_role',
                    'registro_clave' => 'HU-014-PLANTA',
                ],
                [
                    'numero_activo' => null,
                    'user_id' => null,
                    'modulo' => 'M03 Consultas, reportes y seguimiento',
                    'antes' => null,
                    'despues' => json_encode([
                        'rol' => self::ROLE_NAME,
                        'permiso' => self::PERMISSION_KEY,
                        'bandeja_asignaciones' => true,
                        'descripcion' => 'Se garantiza la atención y notificación de observaciones asignadas a Planta / Inventarios.',
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
                ->where('accion', self::AUDIT_ACTION)
                ->where('tabla_afectada', 'permission_role')
                ->where('registro_clave', 'HU-014-PLANTA')
                ->delete();
        }

        if (
            Schema::hasTable('expediente_observaciones')
            && $this->indexExists(self::INDEX_NAME)
        ) {
            Schema::table('expediente_observaciones', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_NAME);
            });
        }

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            $roleId = DB::table('roles')
                ->where('nombre', self::ROLE_NAME)
                ->value('id');
            $permissionId = DB::table('permissions')
                ->where('clave', self::PERMISSION_KEY)
                ->value('id');

            if ($roleId && $permissionId) {
                DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->delete();
            }
        }
    }

    private function indexExists(string $indexName): bool
    {
        if (!Schema::hasTable('expediente_observaciones')) {
            return false;
        }

        foreach (Schema::getIndexes('expediente_observaciones') as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};

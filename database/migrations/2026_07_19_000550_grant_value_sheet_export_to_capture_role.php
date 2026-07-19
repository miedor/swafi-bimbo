<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_NAME = 'Usuario Captura';

    /**
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        'reportes.exportar_excel',
        'reportes.exportar_pdf',
    ];

    public function up(): void
    {
        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('nombre', self::ROLE_NAME)
            ->value('id');

        if (!$roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('clave', self::PERMISSIONS)
            ->pluck('id', 'clave');

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = $permissionIds->get($permission);

            if (!$permissionId) {
                continue;
            }

            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

        $this->registerAudit();
    }

    public function down(): void
    {
        if (
            Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            $roleId = DB::table('roles')
                ->where('nombre', self::ROLE_NAME)
                ->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('clave', self::PERMISSIONS)
                ->pluck('id');

            if ($roleId && $permissionIds->isNotEmpty()) {
                DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', 'HABILITA_FICHA_VALORES_CAPTURA')
                ->where('registro_clave', 'HU-039')
                ->delete();
        }
    }

    private function registerAudit(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => 'HABILITA_FICHA_VALORES_CAPTURA',
                'registro_clave' => 'HU-039',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M02 Control fiscal y financiero',
                'tabla_afectada' => 'permission_role',
                'antes' => null,
                'despues' => json_encode([
                    'historia_usuario' => 'HU-039',
                    'rol' => self::ROLE_NAME,
                    'permisos' => self::PERMISSIONS,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};

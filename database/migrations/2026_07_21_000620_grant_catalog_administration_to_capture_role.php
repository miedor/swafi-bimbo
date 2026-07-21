<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_NAME = 'Usuario Captura';

    private const PERMISSION = 'catalogos.administrar';

    private const AUDIT_ACTION = 'HABILITA_CATALOGOS_CAPTURA';

    private const AUDIT_KEY = 'ROL-CAPTURA-CATALOGOS';

    private const ROLE_DESCRIPTION = 'Registro individual y masivo de expedientes, documentos, valores oficiales de Oracle ERP y administración de catálogos base.';

    public function up(): void
    {
        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $role = DB::table('roles')
                ->where('nombre', self::ROLE_NAME)
                ->lockForUpdate()
                ->first();

            if (!$role) {
                return;
            }

            $permissionPayload = [
                'descripcion' => 'Administrar catálogos base.',
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('permissions', 'activo')) {
                $permissionPayload['activo'] = 1;
            }

            if (Schema::hasColumn('permissions', 'es_sistema')) {
                $permissionPayload['es_sistema'] = 1;
            }

            if (!DB::table('permissions')->where('clave', self::PERMISSION)->exists()) {
                $permissionPayload['created_at'] = $now;
            }

            DB::table('permissions')->updateOrInsert(
                ['clave' => self::PERMISSION],
                $permissionPayload
            );

            $permissionId = DB::table('permissions')
                ->where('clave', self::PERMISSION)
                ->value('id');

            if (!$permissionId) {
                return;
            }

            $wasAssigned = DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('permission_id', $permissionId)
                ->exists();

            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'descripcion' => self::ROLE_DESCRIPTION,
                    'updated_at' => $now,
                ]);

            $this->registerAuditEvent(
                roleId: (int) $role->id,
                previousDescription: (string) ($role->descripcion ?? ''),
                wasAssigned: $wasAssigned
            );
        }, 3);
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')
        ) {
            return;
        }

        DB::transaction(function (): void {
            $audit = Schema::hasTable('bitacora_auditoria')
                ? DB::table('bitacora_auditoria')
                    ->where('accion', self::AUDIT_ACTION)
                    ->where('registro_clave', self::AUDIT_KEY)
                    ->first()
                : null;

            $before = [];

            if ($audit && is_string($audit->antes ?? null)) {
                $decoded = json_decode((string) $audit->antes, true);
                $before = is_array($decoded) ? $decoded : [];
            }

            $roleId = DB::table('roles')
                ->where('nombre', self::ROLE_NAME)
                ->value('id');
            $permissionId = DB::table('permissions')
                ->where('clave', self::PERMISSION)
                ->value('id');

            if (
                $roleId
                && $permissionId
                && !((bool) ($before['permiso_asignado_previamente'] ?? false))
            ) {
                DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->delete();
            }

            $previousDescription = trim((string) ($before['descripcion_rol'] ?? ''));

            if ($roleId && $previousDescription !== '') {
                DB::table('roles')
                    ->where('id', $roleId)
                    ->update([
                        'descripcion' => $previousDescription,
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasTable('bitacora_auditoria')) {
                DB::table('bitacora_auditoria')
                    ->where('accion', self::AUDIT_ACTION)
                    ->where('registro_clave', self::AUDIT_KEY)
                    ->delete();
            }
        }, 3);
    }

    private function registerAuditEvent(
        int $roleId,
        string $previousDescription,
        bool $wasAssigned
    ): void {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => self::AUDIT_ACTION,
                'registro_clave' => self::AUDIT_KEY,
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'tabla_afectada' => 'roles,permission_role',
                'antes' => json_encode([
                    'rol_id' => $roleId,
                    'rol' => self::ROLE_NAME,
                    'descripcion_rol' => $previousDescription,
                    'permiso' => self::PERMISSION,
                    'permiso_asignado_previamente' => $wasAssigned,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'despues' => json_encode([
                    'rol_id' => $roleId,
                    'rol' => self::ROLE_NAME,
                    'descripcion_rol' => self::ROLE_DESCRIPTION,
                    'permiso' => self::PERMISSION,
                    'puede_crear_actualizar_importar_activar_desactivar' => true,
                    'seguridad_administrar' => false,
                    'documentos_eliminar' => false,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};

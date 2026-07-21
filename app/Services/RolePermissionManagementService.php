<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

class RolePermissionManagementService
{
    private const ADMIN_ROLE = 'Administrador SWAFI';

    private const CAPTURE_ROLE = 'Usuario Captura';

    /**
     * Permisos mínimos que forman parte de la definición de los roles base.
     * Aunque la matriz se edite desde Seguridad, estos permisos no deben
     * retirarse porque representan decisiones operativas aprobadas.
     *
     * @var array<string, array<int, string>>
     */
    private const REQUIRED_PERMISSION_KEYS_BY_SYSTEM_ROLE = [
        self::CAPTURE_ROLE => [
            'catalogos.administrar',
        ],
    ];

    private const ADMIN_ONLY_PERMISSION_KEYS = [
        'documentos.eliminar',
    ];

    /**
     * Crea o actualiza un rol y sincroniza su matriz de permisos activos.
     *
     * @return array{role_id:int, created:bool, permissions_changed:bool, affected_users:int}
     */
    public function saveRole(array $validated, int $actorId, ?string $ip): array
    {
        return DB::transaction(function () use ($validated, $actorId, $ip): array {
            $roleId = isset($validated['id']) && $validated['id'] !== null
                ? (int) $validated['id']
                : null;
            $requestedPermissionIds = $this->normalizeIds($validated['permission_ids'] ?? []);
            $requestedActive = (int) $validated['activo'] === 1;
            $beforeRole = null;
            $beforePermissionIds = [];

            if ($roleId !== null) {
                $beforeRole = DB::table('roles')
                    ->where('id', $roleId)
                    ->lockForUpdate()
                    ->first();

                if (!$beforeRole) {
                    throw new DomainException('El rol seleccionado ya no existe. Actualiza el listado e inténtalo nuevamente.');
                }

                $beforePermissionIds = $this->permissionIdsForRole($roleId, true);
                $this->assertRoleIdentityIsSafe($beforeRole, $validated);

                if ((bool) $beforeRole->activo !== $requestedActive) {
                    throw new DomainException('Utiliza la acción Activar o Desactivar para cambiar el estatus del rol y conservar el motivo en la bitácora.');
                }
            }

            $isAdministrator = ($beforeRole?->nombre ?? $validated['nombre']) === self::ADMIN_ROLE;
            $permissionIds = $isAdministrator
                ? $this->allActivePermissionIds(true)
                : $this->assertAndReturnActivePermissionIds($requestedPermissionIds);

            if (!$isAdministrator) {
                $this->assertNoAdministratorOnlyPermissions($permissionIds);
                $permissionIds = $this->appendRequiredSystemRolePermissions(
                    $beforeRole,
                    $permissionIds
                );
            }

            if ($requestedActive && !$isAdministrator && $permissionIds === []) {
                throw new DomainException('Un rol activo debe conservar al menos un permiso activo.');
            }

            $now = now();
            $payload = [
                'nombre' => (string) $validated['nombre'],
                'descripcion' => (string) $validated['descripcion'],
                'activo' => $requestedActive ? 1 : 0,
                'updated_at' => $now,
            ];
            $created = $roleId === null;

            if ($created) {
                $payload['es_sistema'] = 0;
                $payload['created_at'] = $now;
                $roleId = (int) DB::table('roles')->insertGetId($payload);
            } else {
                DB::table('roles')->where('id', $roleId)->update($payload);
            }

            $this->syncRolePermissions($roleId, $permissionIds);

            $afterRole = DB::table('roles')->where('id', $roleId)->first();
            $affectedUsers = DB::table('role_user')
                ->where('role_id', $roleId)
                ->distinct()
                ->count('user_id');
            $permissionsChanged = $beforePermissionIds !== $permissionIds;

            $this->audit(
                action: $created ? 'SEGURIDAD_ROL_ALTA' : 'SEGURIDAD_ROL_ACTUALIZACION',
                actorId: $actorId,
                table: 'roles,permission_role',
                recordKey: (string) $roleId,
                before: $beforeRole ? [
                    'rol' => $this->roleSnapshot($beforeRole),
                    'permisos_asignados' => $beforePermissionIds,
                ] : null,
                after: [
                    'rol' => $afterRole ? $this->roleSnapshot($afterRole) : null,
                    'permisos_asignados' => $permissionIds,
                    'usuarios_afectados' => $affectedUsers,
                    'permisos_actualizados' => $permissionsChanged,
                ],
                ip: $ip
            );

            return [
                'role_id' => $roleId,
                'created' => $created,
                'permissions_changed' => $permissionsChanged,
                'affected_users' => $affectedUsers,
            ];
        }, 3);
    }

    public function changeRoleStatus(
        int $roleId,
        string $nextStatus,
        string $reason,
        int $actorId,
        ?string $ip
    ): void {
        DB::transaction(function () use ($roleId, $nextStatus, $reason, $actorId, $ip): void {
            if (!in_array($nextStatus, ['activo', 'inactivo'], true)) {
                throw new DomainException('La operación solicitada para el rol no es válida.');
            }

            $role = DB::table('roles')
                ->where('id', $roleId)
                ->lockForUpdate()
                ->first();

            if (!$role) {
                throw new DomainException('El rol seleccionado ya no existe. Actualiza el listado e inténtalo nuevamente.');
            }

            if ((bool) ($role->es_sistema ?? false)) {
                throw new DomainException('Los roles base del sistema no pueden activarse, desactivarse ni renombrarse desde la interfaz.');
            }

            $currentStatus = (bool) $role->activo ? 'activo' : 'inactivo';

            if ($currentStatus === $nextStatus) {
                throw new DomainException(
                    $nextStatus === 'activo'
                        ? 'El rol seleccionado ya se encuentra activo.'
                        : 'El rol seleccionado ya se encuentra inactivo.'
                );
            }

            $assignedUserIds = DB::table('role_user')
                ->where('role_id', $roleId)
                ->lockForUpdate()
                ->pluck('user_id')
                ->map(fn ($userId) => (int) $userId)
                ->unique()
                ->values()
                ->all();

            if ($nextStatus === 'inactivo' && $assignedUserIds !== []) {
                throw new DomainException('No puedes desactivar un rol asignado a usuarios. Reasigna primero sus cuentas y vuelve a intentarlo.');
            }

            if ($nextStatus === 'activo') {
                $permissionIds = $this->permissionIdsForRole($roleId, true);
                $activePermissionIds = $this->assertAndReturnActivePermissionIds($permissionIds);
                $this->assertNoAdministratorOnlyPermissions($activePermissionIds);

                if ($activePermissionIds === []) {
                    throw new DomainException('No puedes activar un rol sin permisos activos. Edita el rol y asigna al menos uno.');
                }
            }

            DB::table('roles')->where('id', $roleId)->update([
                'activo' => $nextStatus === 'activo' ? 1 : 0,
                'updated_at' => now(),
            ]);

            $after = DB::table('roles')->where('id', $roleId)->first();

            $this->audit(
                action: $nextStatus === 'activo'
                    ? 'SEGURIDAD_ROL_ACTIVACION'
                    : 'SEGURIDAD_ROL_DESACTIVACION',
                actorId: $actorId,
                table: 'roles',
                recordKey: (string) $roleId,
                before: $this->roleSnapshot($role),
                after: [
                    'rol' => $after ? $this->roleSnapshot($after) : null,
                    'motivo' => trim($reason),
                    'usuarios_asignados' => count($assignedUserIds),
                ],
                ip: $ip
            );
        }, 3);
    }

    /**
     * Crea un permiso técnico o actualiza únicamente su descripción.
     * La clave queda inmutable después de crearse para evitar romper rutas y reglas RBAC.
     *
     * @return array{permission_id:int, created:bool}
     */
    public function savePermission(array $validated, int $actorId, ?string $ip): array
    {
        return DB::transaction(function () use ($validated, $actorId, $ip): array {
            $permissionId = isset($validated['id']) && $validated['id'] !== null
                ? (int) $validated['id']
                : null;
            $before = null;
            $created = $permissionId === null;
            $now = now();

            if (!$created) {
                $before = DB::table('permissions')
                    ->where('id', $permissionId)
                    ->lockForUpdate()
                    ->first();

                if (!$before) {
                    throw new DomainException('El permiso seleccionado ya no existe. Actualiza el listado e inténtalo nuevamente.');
                }

                if ((string) $before->clave !== (string) $validated['clave']) {
                    throw new DomainException('La clave técnica de un permiso no puede modificarse después de su creación.');
                }

                DB::table('permissions')->where('id', $permissionId)->update([
                    'descripcion' => (string) $validated['descripcion'],
                    'updated_at' => $now,
                ]);
            } else {
                $permissionId = (int) DB::table('permissions')->insertGetId([
                    'clave' => (string) $validated['clave'],
                    'descripcion' => (string) $validated['descripcion'],
                    'activo' => 1,
                    'es_sistema' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->attachPermissionToAdministrator($permissionId);
            }

            $after = DB::table('permissions')->where('id', $permissionId)->first();

            $this->audit(
                action: $created ? 'SEGURIDAD_PERMISO_ALTA' : 'SEGURIDAD_PERMISO_ACTUALIZACION',
                actorId: $actorId,
                table: 'permissions,permission_role',
                recordKey: (string) $permissionId,
                before: $before ? $this->permissionSnapshot($before) : null,
                after: $after ? $this->permissionSnapshot($after) : null,
                ip: $ip
            );

            return [
                'permission_id' => $permissionId,
                'created' => $created,
            ];
        }, 3);
    }

    public function changePermissionStatus(
        int $permissionId,
        string $nextStatus,
        string $reason,
        int $actorId,
        ?string $ip
    ): void {
        DB::transaction(function () use ($permissionId, $nextStatus, $reason, $actorId, $ip): void {
            if (!in_array($nextStatus, ['activo', 'inactivo'], true)) {
                throw new DomainException('La operación solicitada para el permiso no es válida.');
            }

            $permission = DB::table('permissions')
                ->where('id', $permissionId)
                ->lockForUpdate()
                ->first();

            if (!$permission) {
                throw new DomainException('El permiso seleccionado ya no existe. Actualiza el listado e inténtalo nuevamente.');
            }

            if ((bool) ($permission->es_sistema ?? false)) {
                throw new DomainException('Los permisos base del sistema no pueden activarse, desactivarse ni cambiar su clave técnica.');
            }

            $currentStatus = (bool) $permission->activo ? 'activo' : 'inactivo';

            if ($currentStatus === $nextStatus) {
                throw new DomainException(
                    $nextStatus === 'activo'
                        ? 'El permiso seleccionado ya se encuentra activo.'
                        : 'El permiso seleccionado ya se encuentra inactivo.'
                );
            }

            $administratorRoleId = $this->administratorRoleId(true);
            $assignedRoleIds = DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', '<>', $administratorRoleId)
                ->lockForUpdate()
                ->pluck('role_id')
                ->map(fn ($roleId) => (int) $roleId)
                ->unique()
                ->values()
                ->all();

            if ($nextStatus === 'inactivo' && $assignedRoleIds !== []) {
                throw new DomainException('No puedes desactivar un permiso asignado a roles. Retíralo primero de todos los roles y vuelve a intentarlo.');
            }

            DB::table('permissions')->where('id', $permissionId)->update([
                'activo' => $nextStatus === 'activo' ? 1 : 0,
                'updated_at' => now(),
            ]);

            if ($nextStatus === 'activo') {
                $this->attachPermissionToAdministrator($permissionId, $administratorRoleId);
            } else {
                DB::table('permission_role')
                    ->where('role_id', $administratorRoleId)
                    ->where('permission_id', $permissionId)
                    ->delete();
            }

            $after = DB::table('permissions')->where('id', $permissionId)->first();

            $this->audit(
                action: $nextStatus === 'activo'
                    ? 'SEGURIDAD_PERMISO_ACTIVACION'
                    : 'SEGURIDAD_PERMISO_DESACTIVACION',
                actorId: $actorId,
                table: 'permissions,permission_role',
                recordKey: (string) $permissionId,
                before: $this->permissionSnapshot($permission),
                after: [
                    'permiso' => $after ? $this->permissionSnapshot($after) : null,
                    'motivo' => trim($reason),
                    'roles_asignados' => $assignedRoleIds,
                ],
                ip: $ip
            );
        }, 3);
    }

    private function assertRoleIdentityIsSafe(object $role, array $validated): void
    {
        if (!(bool) ($role->es_sistema ?? false)) {
            return;
        }

        if ((string) $role->nombre !== (string) $validated['nombre']) {
            throw new DomainException('El nombre de un rol base del sistema no puede modificarse porque existen reglas que dependen de esa identidad.');
        }

        if ((int) $validated['activo'] !== 1) {
            throw new DomainException('Los roles base del sistema deben permanecer activos.');
        }
    }

    /**
     * @return array<int, int>
     */
    private function assertAndReturnActivePermissionIds(array $permissionIds): array
    {
        if ($permissionIds === []) {
            return [];
        }

        $activeIds = DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->where('activo', 1)
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($permissionId) => (int) $permissionId)
            ->sort()
            ->values()
            ->all();

        if ($activeIds !== $permissionIds) {
            throw new DomainException('Uno de los permisos seleccionados ya no existe o está inactivo. Actualiza la pantalla y vuelve a intentarlo.');
        }

        return $activeIds;
    }

    /**
     * Conserva los permisos mínimos aprobados para un rol base del sistema.
     *
     * @param array<int, int> $permissionIds
     * @return array<int, int>
     */
    private function appendRequiredSystemRolePermissions(
        ?object $role,
        array $permissionIds
    ): array {
        if (
            $role === null
            || !(bool) ($role->es_sistema ?? false)
        ) {
            return $permissionIds;
        }

        $roleName = (string) ($role->nombre ?? '');
        $requiredKeys = self::REQUIRED_PERMISSION_KEYS_BY_SYSTEM_ROLE[$roleName] ?? [];

        if ($requiredKeys === []) {
            return $permissionIds;
        }

        $requiredPermissionIds = DB::table('permissions')
            ->whereIn('clave', $requiredKeys)
            ->where('activo', 1)
            ->lockForUpdate()
            ->pluck('id', 'clave');

        $missingKeys = array_values(array_filter(
            $requiredKeys,
            fn (string $key): bool => !$requiredPermissionIds->has($key)
        ));

        if ($missingKeys !== []) {
            throw new DomainException(
                'No fue posible conservar la matriz base del rol Usuario Captura porque falta el permiso activo catalogos.administrar.'
            );
        }

        return collect($permissionIds)
            ->merge($requiredPermissionIds->values())
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function assertNoAdministratorOnlyPermissions(array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $restrictedPermission = DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->whereIn('clave', self::ADMIN_ONLY_PERMISSION_KEYS)
            ->lockForUpdate()
            ->value('clave');

        if ($restrictedPermission !== null) {
            throw new DomainException(
                'El permiso para dar de baja documentos es exclusivo del Administrador SWAFI y no puede asignarse a otro rol.'
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function allActivePermissionIds(bool $lock = false): array
    {
        $query = DB::table('permissions')
            ->where('activo', 1)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->pluck('id')
            ->map(fn ($permissionId) => (int) $permissionId)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function permissionIdsForRole(int $roleId, bool $lock = false): array
    {
        $query = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->orderBy('permission_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->pluck('permission_id')
            ->map(fn ($permissionId) => (int) $permissionId)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        DB::table('permission_role')->where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function administratorRoleId(bool $lock = false): int
    {
        $query = DB::table('roles')
            ->where('nombre', self::ADMIN_ROLE)
            ->where('activo', 1);

        if ($lock) {
            $query->lockForUpdate();
        }

        $roleId = $query->value('id');

        if ($roleId === null) {
            throw new DomainException('No fue posible localizar el rol Administrador SWAFI activo. Verifica la configuración de seguridad.');
        }

        return (int) $roleId;
    }

    private function attachPermissionToAdministrator(int $permissionId, ?int $administratorRoleId = null): void
    {
        $roleId = $administratorRoleId ?? $this->administratorRoleId(true);

        DB::table('permission_role')->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function roleSnapshot(object $role): array
    {
        return [
            'id' => (int) $role->id,
            'nombre' => (string) $role->nombre,
            'descripcion' => (string) ($role->descripcion ?? ''),
            'activo' => (bool) $role->activo,
            'es_sistema' => (bool) ($role->es_sistema ?? false),
        ];
    }

    private function permissionSnapshot(object $permission): array
    {
        return [
            'id' => (int) $permission->id,
            'clave' => (string) $permission->clave,
            'descripcion' => (string) ($permission->descripcion ?? ''),
            'activo' => (bool) ($permission->activo ?? true),
            'es_sistema' => (bool) ($permission->es_sistema ?? false),
        ];
    }

    private function audit(
        string $action,
        int $actorId,
        string $table,
        string $recordKey,
        ?array $before,
        ?array $after,
        ?string $ip
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => $actorId > 0 ? $actorId : null,
            'modulo' => 'M04 Administración y seguridad del sistema',
            'accion' => $action,
            'tabla_afectada' => $table,
            'registro_clave' => $recordKey,
            'antes' => $before !== null
                ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'despues' => $after !== null
                ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'ip' => $ip,
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

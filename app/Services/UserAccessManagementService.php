<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAccessManagementService
{
    private const ADMIN_ROLE = 'Administrador SWAFI';

    /**
     * Crea o actualiza un usuario y sincroniza sus roles activos.
     *
     * @return array{user_id:int, created:bool, authorization_changed:bool, password_changed:bool}
     */
    public function saveUser(array $validated, int $actorId, ?string $ip): array
    {
        return DB::transaction(function () use ($validated, $actorId, $ip): array {
            $userId = isset($validated['id']) && $validated['id'] !== null
                ? (int) $validated['id']
                : null;

            $roleIds = collect($validated['role_ids'] ?? [])
                ->map(fn ($roleId) => (int) $roleId)
                ->filter(fn (int $roleId) => $roleId > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $this->assertActiveRoles($roleIds);

            $beforeUser = null;
            $beforeRoleIds = [];

            if ($userId !== null) {
                $beforeUser = DB::table('users')
                    ->where('id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$beforeUser) {
                    throw new DomainException('El usuario seleccionado ya no existe. Actualiza el listado e inténtalo nuevamente.');
                }

                $beforeRoleIds = $this->roleIdsForUser($userId, true);
                $this->assertSelfProtection($actorId, $userId, $beforeRoleIds, $roleIds, $validated);
                $this->assertBlockedUserActivation($beforeUser, $validated);
                $this->assertAdministratorContinuity(
                    targetUserId: $userId,
                    currentStatus: (string) ($beforeUser->estatus ?? 'activo'),
                    nextStatus: (string) $validated['estatus'],
                    currentRoleIds: $beforeRoleIds,
                    nextRoleIds: $roleIds
                );
            }

            $now = now();
            $passwordChanged = !empty($validated['password']);
            $payload = [
                'usuario' => (string) $validated['usuario'],
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'estatus' => (string) $validated['estatus'],
                'updated_at' => $now,
            ];

            if ($passwordChanged) {
                $payload['password'] = Hash::make((string) $validated['password']);
                $payload['password_changed_at'] = $now;
                $payload['intentos_fallidos'] = 0;
                $payload['ultimo_intento_fallido'] = null;
                $payload['bloqueado_en'] = null;
                $payload['bloqueado_motivo'] = null;
                $payload['remember_token'] = null;
            }

            if ((string) $validated['estatus'] === 'activo' && ($beforeUser->estatus ?? null) !== 'bloqueado') {
                $payload['intentos_fallidos'] = 0;
                $payload['ultimo_intento_fallido'] = null;
                $payload['bloqueado_en'] = null;
                $payload['bloqueado_motivo'] = null;
            }

            $created = $userId === null;

            if ($created) {
                $payload['created_at'] = $now;
                $userId = (int) DB::table('users')->insertGetId($payload);
            } else {
                DB::table('users')->where('id', $userId)->update($payload);
            }

            $this->syncRoles($userId, $roleIds);

            $statusChanged = !$created
                && (string) ($beforeUser->estatus ?? '') !== (string) $validated['estatus'];
            $rolesChanged = !$created && $beforeRoleIds !== $roleIds;
            $authorizationChanged = $statusChanged || $rolesChanged;

            if (!$created && ($authorizationChanged || $passwordChanged)) {
                $this->revokeSessions($userId);
            }

            $afterUser = DB::table('users')->where('id', $userId)->first();

            $this->audit(
                action: $created ? 'SEGURIDAD_USUARIO_ALTA' : 'SEGURIDAD_USUARIO_ACTUALIZACION',
                actorId: $actorId,
                targetUserId: $userId,
                before: $beforeUser ? [
                    'usuario' => $this->safeUserPayload($beforeUser),
                    'roles_asignados' => $beforeRoleIds,
                ] : null,
                after: [
                    'usuario' => $afterUser ? $this->safeUserPayload($afterUser) : null,
                    'roles_asignados' => $roleIds,
                    'sesiones_revocadas' => !$created && ($authorizationChanged || $passwordChanged),
                ],
                ip: $ip
            );

            return [
                'user_id' => $userId,
                'created' => $created,
                'authorization_changed' => $authorizationChanged,
                'password_changed' => $passwordChanged,
            ];
        }, 3);
    }

    /**
     * Activa o desactiva una cuenta sin destruir su historial.
     */
    public function changeStatus(
        int $targetUserId,
        string $nextStatus,
        int $actorId,
        ?string $reason,
        ?string $ip
    ): void {
        DB::transaction(function () use ($targetUserId, $nextStatus, $actorId, $reason, $ip): void {
            if (!in_array($nextStatus, ['activo', 'inactivo'], true)) {
                throw new DomainException('La operación solicitada para el usuario no es válida.');
            }

            $user = DB::table('users')
                ->where('id', $targetUserId)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                throw new DomainException('El usuario seleccionado ya no existe. Actualiza el listado e inténtalo nuevamente.');
            }

            $currentStatus = (string) ($user->estatus ?? 'activo');

            if ($actorId === $targetUserId && $nextStatus !== 'activo') {
                throw new DomainException('No puedes desactivar el usuario con el que tienes la sesión actual.');
            }

            if ($currentStatus === 'bloqueado' && $nextStatus === 'activo') {
                throw new DomainException('Para desbloquear esta cuenta debes editarla y asignar una contraseña nueva.');
            }

            if ($currentStatus === $nextStatus) {
                throw new DomainException(
                    $nextStatus === 'activo'
                        ? 'El usuario seleccionado ya se encuentra activo.'
                        : 'El usuario seleccionado ya se encuentra inactivo.'
                );
            }

            $roleIds = $this->roleIdsForUser($targetUserId, true);

            $this->assertAdministratorContinuity(
                targetUserId: $targetUserId,
                currentStatus: $currentStatus,
                nextStatus: $nextStatus,
                currentRoleIds: $roleIds,
                nextRoleIds: $roleIds
            );

            $before = $this->safeUserPayload($user);
            $payload = [
                'estatus' => $nextStatus,
                'updated_at' => now(),
            ];

            if ($nextStatus === 'inactivo') {
                $payload['remember_token'] = null;
            } else {
                $payload['intentos_fallidos'] = 0;
                $payload['ultimo_intento_fallido'] = null;
                $payload['bloqueado_en'] = null;
                $payload['bloqueado_motivo'] = null;
            }

            DB::table('users')->where('id', $targetUserId)->update($payload);

            if ($nextStatus === 'inactivo') {
                $this->revokeSessions($targetUserId);
            }

            $after = DB::table('users')->where('id', $targetUserId)->first();

            $this->audit(
                action: $nextStatus === 'activo'
                    ? 'SEGURIDAD_USUARIO_ACTIVACION'
                    : 'SEGURIDAD_USUARIO_DESACTIVACION',
                actorId: $actorId,
                targetUserId: $targetUserId,
                before: $before,
                after: [
                    'usuario' => $after ? $this->safeUserPayload($after) : null,
                    'roles_asignados' => $roleIds,
                    'motivo' => $reason !== null && trim($reason) !== ''
                        ? trim($reason)
                        : 'Cambio de estatus realizado desde Seguridad y acceso.',
                    'sesiones_revocadas' => $nextStatus === 'inactivo',
                ],
                ip: $ip
            );
        }, 3);
    }

    private function assertActiveRoles(array $roleIds): void
    {
        if ($roleIds === []) {
            throw new DomainException('Debes asignar al menos un rol activo al usuario.');
        }

        $activeRoleIds = DB::table('roles')
            ->whereIn('id', $roleIds)
            ->where('activo', 1)
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($roleId) => (int) $roleId)
            ->sort()
            ->values()
            ->all();

        if ($activeRoleIds !== $roleIds) {
            throw new DomainException('Uno de los roles seleccionados ya no está disponible. Actualiza la pantalla y vuelve a intentarlo.');
        }
    }

    private function assertSelfProtection(
        int $actorId,
        int $targetUserId,
        array $currentRoleIds,
        array $nextRoleIds,
        array $validated
    ): void {
        if ($actorId !== $targetUserId) {
            return;
        }

        if ((string) $validated['estatus'] !== 'activo') {
            throw new DomainException('No puedes desactivar o bloquear el usuario con el que tienes la sesión actual.');
        }

        $adminRoleId = $this->administratorRoleId();

        if (
            $adminRoleId !== null
            && in_array($adminRoleId, $currentRoleIds, true)
            && !in_array($adminRoleId, $nextRoleIds, true)
        ) {
            throw new DomainException('No puedes retirar de tu propia cuenta el rol Administrador SWAFI.');
        }

        if (!empty($validated['password'])) {
            throw new DomainException('Para cambiar tu propia contraseña utiliza la opción Perfil; así se conserva el flujo seguro de sesión.');
        }
    }

    private function assertBlockedUserActivation(object $beforeUser, array $validated): void
    {
        if (
            (string) ($beforeUser->estatus ?? '') === 'bloqueado'
            && (string) $validated['estatus'] === 'activo'
            && empty($validated['password'])
        ) {
            throw new DomainException('Para desbloquear un usuario debes capturar una contraseña nueva que cumpla la política de seguridad.');
        }
    }

    private function assertAdministratorContinuity(
        int $targetUserId,
        string $currentStatus,
        string $nextStatus,
        array $currentRoleIds,
        array $nextRoleIds
    ): void {
        $adminRoleId = $this->administratorRoleId();

        if ($adminRoleId === null) {
            throw new DomainException('No fue posible localizar el rol Administrador SWAFI. Verifica la configuración de seguridad.');
        }

        $currentlyAdministrator = $currentStatus === 'activo'
            && in_array($adminRoleId, $currentRoleIds, true);
        $willRemainAdministrator = $nextStatus === 'activo'
            && in_array($adminRoleId, $nextRoleIds, true);

        if (!$currentlyAdministrator || $willRemainAdministrator) {
            return;
        }

        $otherActiveAdministrators = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->where('ru.role_id', $adminRoleId)
            ->where('u.estatus', 'activo')
            ->where('u.id', '<>', $targetUserId)
            ->select('u.id')
            ->distinct()
            ->lockForUpdate()
            ->pluck('u.id')
            ->count();

        if ($otherActiveAdministrators < 1) {
            throw new DomainException('La operación dejaría a SWAFI sin un Administrador activo. Asigna primero el rol a otra cuenta.');
        }
    }

    private function administratorRoleId(): ?int
    {
        $roleId = DB::table('roles')
            ->where('nombre', self::ADMIN_ROLE)
            ->where('activo', 1)
            ->value('id');

        return $roleId !== null ? (int) $roleId : null;
    }

    private function roleIdsForUser(int $userId, bool $lock = false): array
    {
        $query = DB::table('role_user')
            ->where('user_id', $userId)
            ->orderBy('role_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->pluck('role_id')
            ->map(fn ($roleId) => (int) $roleId)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function syncRoles(int $userId, array $roleIds): void
    {
        DB::table('role_user')->where('user_id', $userId)->delete();

        foreach ($roleIds as $roleId) {
            DB::table('role_user')->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function revokeSessions(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();
        DB::table('users')->where('id', $userId)->update(['remember_token' => null]);
    }

    private function safeUserPayload(object $user): array
    {
        return [
            'id' => (int) $user->id,
            'usuario' => (string) ($user->usuario ?? ''),
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'estatus' => (string) ($user->estatus ?? ''),
            'ultimo_acceso' => $user->ultimo_acceso ?? null,
            'ultimo_ip' => $user->ultimo_ip ?? null,
            'intentos_fallidos' => (int) ($user->intentos_fallidos ?? 0),
            'bloqueado_en' => $user->bloqueado_en ?? null,
        ];
    }

    private function audit(
        string $action,
        int $actorId,
        int $targetUserId,
        ?array $before,
        ?array $after,
        ?string $ip
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => $actorId > 0 ? $actorId : null,
            'modulo' => 'M04 Administración y seguridad del sistema',
            'accion' => $action,
            'tabla_afectada' => 'users,role_user',
            'registro_clave' => (string) $targetUserId,
            'antes' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'despues' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip' => $ip,
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

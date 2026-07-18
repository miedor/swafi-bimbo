<?php

namespace App\Services;

use App\Rules\AdministratorBootstrapPasswordPolicy;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class SecureAdministratorProvisioningService
{
    private const ADMIN_ROLE = 'Administrador SWAFI';

    public function administrators(): Collection
    {
        $this->assertRequiredStructure();

        return DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('r.nombre', self::ADMIN_ROLE)
            ->select('u.id', 'u.name', 'u.email', 'u.usuario', 'u.estatus')
            ->distinct()
            ->orderBy('u.id')
            ->get();
    }

    public function inspectTarget(?string $email, ?string $usuario): array
    {
        $this->assertRequiredStructure();

        $matches = $this->matchingUsers($email, $usuario, false);
        $target = $matches->count() === 1 ? $matches->first() : null;
        $adminIds = $this->administratorIds(false);

        return [
            'administrator_count' => $adminIds->count(),
            'identity_conflict' => $matches->count() > 1,
            'target' => $target,
            'target_is_administrator' => $target !== null && $adminIds->contains((int) $target->id),
        ];
    }

    public function provision(
        array $attributes,
        bool $rotatePassword = false,
        bool $promoteExisting = false,
    ): array {
        $this->assertRequiredStructure();

        return DB::transaction(function () use ($attributes, $rotatePassword, $promoteExisting): array {
            $role = DB::table('roles')
                ->where('nombre', self::ADMIN_ROLE)
                ->lockForUpdate()
                ->first();

            if ($role === null) {
                throw new RuntimeException(
                    'No existe el rol Administrador SWAFI. Ejecuta primero las migraciones y el seeder de catálogos.'
                );
            }

            if (!(bool) $role->activo) {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'activo' => 1,
                        'updated_at' => now(),
                    ]);
            }

            $permissionIds = DB::table('permissions')
                ->where('activo', 1)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($permissionIds->isEmpty()) {
                throw new RuntimeException(
                    'No existen permisos SWAFI. Ejecuta primero las migraciones y el seeder de catálogos.'
                );
            }

            $adminIds = DB::table('role_user')
                ->where('role_id', $role->id)
                ->lockForUpdate()
                ->pluck('user_id')
                ->map(static fn ($id): int => (int) $id);

            $matches = $this->matchingUsers(
                $attributes['email'] ?? null,
                $attributes['usuario'] ?? null,
                true
            );

            if ($matches->count() > 1) {
                throw new DomainException(
                    'El correo y el usuario corresponden a registros diferentes. Revisa la identidad antes de continuar.'
                );
            }

            $target = $matches->first();
            $targetIsAdministrator = $target !== null && $adminIds->contains((int) $target->id);

            if ($adminIds->isNotEmpty()) {
                if (!$targetIsAdministrator) {
                    throw new DomainException(
                        'Ya existe un Administrador SWAFI. No se creó ni promovió una segunda cuenta administrativa.'
                    );
                }

                if (!$rotatePassword) {
                    $this->attachAllPermissions((int) $role->id, $permissionIds);

                    return [
                        'status' => 'unchanged',
                        'user_id' => (int) $target->id,
                        'sessions_revoked' => 0,
                    ];
                }

                $this->validatePassword(
                    (string) ($attributes['password'] ?? ''),
                    (string) $target->usuario,
                    (string) $target->email
                );

                $before = $this->safeUserSnapshot($target);
                $payload = $this->securityResetPayload((string) $attributes['password']);

                DB::table('users')
                    ->where('id', $target->id)
                    ->update($payload);

                $this->attachAllPermissions((int) $role->id, $permissionIds);
                $sessionsRevoked = $this->revokeSessions((int) $target->id);
                $after = $this->safeUserSnapshot(
                    DB::table('users')->where('id', $target->id)->first()
                );

                $this->registerAudit(
                    'ADMIN_PASSWORD_ROTACION',
                    (int) $target->id,
                    $before,
                    $after,
                    $sessionsRevoked
                );

                return [
                    'status' => 'rotated',
                    'user_id' => (int) $target->id,
                    'sessions_revoked' => $sessionsRevoked,
                ];
            }

            $targetId = $target?->id !== null ? (int) $target->id : null;
            $validated = $this->validateProvisioningData($attributes, $targetId);

            if ($target !== null && !$promoteExisting) {
                throw new DomainException(
                    'La identidad indicada ya pertenece a un usuario sin rol administrador. Usa la confirmación explícita para promoverlo.'
                );
            }

            $before = $target !== null ? $this->safeUserSnapshot($target) : null;
            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'usuario' => $validated['usuario'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
                'estatus' => 'activo',
                'remember_token' => null,
                'updated_at' => now(),
            ];

            $payload = array_merge($payload, $this->optionalSecurityResetColumns());

            if ($target === null) {
                $payload['created_at'] = now();
                $targetId = (int) DB::table('users')->insertGetId($payload);
                $action = 'ADMIN_BOOTSTRAP_SEGURO';
                $status = 'created';
            } else {
                DB::table('users')
                    ->where('id', $targetId)
                    ->update($payload);

                $action = 'ADMIN_PROMOCION_SEGURA';
                $status = 'promoted';
            }

            $this->attachAllPermissions((int) $role->id, $permissionIds);

            DB::table('role_user')->updateOrInsert([
                'user_id' => $targetId,
                'role_id' => $role->id,
            ]);

            $sessionsRevoked = $this->revokeSessions($targetId);
            $after = $this->safeUserSnapshot(
                DB::table('users')->where('id', $targetId)->first()
            );

            $this->registerAudit(
                $action,
                $targetId,
                $before,
                $after,
                $sessionsRevoked
            );

            return [
                'status' => $status,
                'user_id' => $targetId,
                'sessions_revoked' => $sessionsRevoked,
            ];
        }, 3);
    }

    private function validateProvisioningData(array $attributes, ?int $ignoreUserId): array
    {
        $usuario = trim((string) ($attributes['usuario'] ?? ''));
        $email = mb_strtolower(trim((string) ($attributes['email'] ?? '')));

        return Validator::make(
            [
                'name' => trim((string) ($attributes['name'] ?? '')),
                'email' => $email,
                'usuario' => $usuario,
                'password' => (string) ($attributes['password'] ?? ''),
            ],
            [
                'name' => ['required', 'string', 'min:3', 'max:120'],
                'email' => [
                    'required',
                    'string',
                    'email:rfc',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($ignoreUserId),
                ],
                'usuario' => [
                    'required',
                    'string',
                    'min:4',
                    'max:80',
                    'regex:/^[A-Za-z0-9._-]+$/',
                    Rule::unique('users', 'usuario')->ignore($ignoreUserId),
                ],
                'password' => [
                    'required',
                    'string',
                    'max:120',
                    new AdministratorBootstrapPasswordPolicy($usuario, $email),
                ],
            ],
            [
                'name.required' => 'El nombre de la persona administradora es obligatorio.',
                'name.min' => 'El nombre debe contener al menos 3 caracteres.',
                'name.max' => 'El nombre no puede superar 120 caracteres.',
                'email.required' => 'El correo de la persona administradora es obligatorio.',
                'email.email' => 'Captura un correo electrónico válido.',
                'email.unique' => 'El correo ya está asociado con otro usuario.',
                'usuario.required' => 'El identificador de usuario es obligatorio.',
                'usuario.min' => 'El identificador de usuario debe contener al menos 4 caracteres.',
                'usuario.max' => 'El identificador de usuario no puede superar 80 caracteres.',
                'usuario.regex' => 'El usuario solo puede contener letras, números, punto, guion y guion bajo.',
                'usuario.unique' => 'El identificador de usuario ya está asociado con otra cuenta.',
                'password.required' => 'La contraseña segura es obligatoria para crear o promover al administrador.',
                'password.max' => 'La contraseña no puede superar 120 caracteres.',
            ]
        )->validate();
    }

    private function validatePassword(string $password, string $usuario, string $email): void
    {
        Validator::make(
            ['password' => $password],
            [
                'password' => [
                    'required',
                    'string',
                    'max:120',
                    new AdministratorBootstrapPasswordPolicy($usuario, $email),
                ],
            ],
            [
                'password.required' => 'La nueva contraseña segura es obligatoria.',
                'password.max' => 'La contraseña no puede superar 120 caracteres.',
            ]
        )->validate();
    }

    private function matchingUsers(?string $email, ?string $usuario, bool $lock): Collection
    {
        $email = mb_strtolower(trim((string) $email));
        $usuario = trim((string) $usuario);

        if ($email === '' && $usuario === '') {
            return collect();
        }

        $query = DB::table('users')
            ->where(function ($query) use ($email, $usuario): void {
                if ($email !== '') {
                    $query->where('email', $email);
                }

                if ($usuario !== '') {
                    if ($email !== '') {
                        $query->orWhere('usuario', $usuario);
                    } else {
                        $query->where('usuario', $usuario);
                    }
                }
            })
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function administratorIds(bool $lock): Collection
    {
        $query = DB::table('role_user as ru')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('r.nombre', self::ADMIN_ROLE)
            ->select('ru.user_id')
            ->distinct()
            ->orderBy('ru.user_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->pluck('user_id')->map(static fn ($id): int => (int) $id);
    }

    private function attachAllPermissions(int $roleId, Collection $permissionIds): void
    {
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function securityResetPayload(string $password): array
    {
        return array_merge([
            'password' => Hash::make($password),
            'remember_token' => null,
            'updated_at' => now(),
        ], $this->optionalSecurityResetColumns());
    }

    private function optionalSecurityResetColumns(): array
    {
        $payload = [];

        if (Schema::hasColumn('users', 'password_changed_at')) {
            $payload['password_changed_at'] = now();
        }

        if (Schema::hasColumn('users', 'intentos_fallidos')) {
            $payload['intentos_fallidos'] = 0;
        }

        if (Schema::hasColumn('users', 'ultimo_intento_fallido')) {
            $payload['ultimo_intento_fallido'] = null;
        }

        if (Schema::hasColumn('users', 'bloqueado_en')) {
            $payload['bloqueado_en'] = null;
        }

        if (Schema::hasColumn('users', 'bloqueado_motivo')) {
            $payload['bloqueado_motivo'] = null;
        }

        return $payload;
    }

    private function revokeSessions(int $userId): int
    {
        if (!Schema::hasTable('sessions') || !Schema::hasColumn('sessions', 'user_id')) {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->delete();
    }

    private function safeUserSnapshot(?object $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'usuario' => $user->usuario ?? null,
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
            'estatus' => $user->estatus ?? null,
            'password_changed_at' => $user->password_changed_at ?? null,
        ];
    }

    private function registerAudit(
        string $action,
        int $targetUserId,
        ?array $before,
        array $after,
        int $sessionsRevoked,
    ): void {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => null,
            'modulo' => 'M04 Administración y seguridad',
            'accion' => $action,
            'tabla_afectada' => 'users',
            'registro_clave' => (string) $targetUserId,
            'antes' => $before === null
                ? null
                : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'despues' => json_encode([
                'usuario' => $after,
                'rol_asignado' => self::ADMIN_ROLE,
                'sesiones_revocadas' => $sessionsRevoked,
                'origen' => 'comando_consola_seguro',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => null,
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertRequiredStructure(): void
    {
        $tables = [
            'users',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException(
                    'La estructura de seguridad SWAFI está incompleta. Ejecuta primero php artisan migrate --force.'
                );
            }
        }

        foreach (['usuario', 'estatus'] as $column) {
            if (!Schema::hasColumn('users', $column)) {
                throw new RuntimeException(
                    'La tabla users no contiene todos los campos de seguridad requeridos. Ejecuta las migraciones pendientes.'
                );
            }
        }
    }
}

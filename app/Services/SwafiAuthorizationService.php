<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SwafiAuthorizationService
{
    /**
     * Cache por usuario durante la misma solicitud HTTP.
     *
     * @var array<int, array{roles: array<int, string>, permissions: array<int, string>, is_admin: bool}>
     */
    private array $contextCache = [];

    /**
     * Obtiene los roles y permisos vigentes directamente de la base de datos.
     */
    public function contextForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'roles' => [],
                'permissions' => [],
                'is_admin' => false,
            ];
        }

        if (isset($this->contextCache[$userId])) {
            return $this->contextCache[$userId];
        }

        if (
            !Schema::hasTable('roles') ||
            !Schema::hasTable('role_user') ||
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('permission_role')
        ) {
            return $this->contextCache[$userId] = [
                'roles' => [],
                'permissions' => [],
                'is_admin' => false,
            ];
        }

        $roles = DB::table('roles as r')
            ->join('role_user as ru', 'ru.role_id', '=', 'r.id')
            ->where('ru.user_id', $userId)
            ->where('r.activo', 1)
            ->orderBy('r.nombre')
            ->pluck('r.nombre')
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $permissions = DB::table('permissions as p')
            ->join('permission_role as pr', 'pr.permission_id', '=', 'p.id')
            ->join('role_user as ru', 'ru.role_id', '=', 'pr.role_id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $userId)
            ->where('r.activo', 1)
            ->orderBy('p.clave')
            ->distinct()
            ->pluck('p.clave')
            ->map(fn ($permission) => trim((string) $permission))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $isAdmin = collect($roles)->contains(
            fn (string $role) => $this->normalizeRole($role) === 'administrador swafi'
        );

        /*
        |--------------------------------------------------------------------------
        | Administrador SWAFI = acceso integral
        |--------------------------------------------------------------------------
        | Si el usuario posee el rol Administrador SWAFI, se incorporan todos los
        | permisos existentes. Esto evita una pérdida temporal de acceso cuando una
        | migración crea un permiso nuevo y la tabla pivote aún no se ha sincronizado.
        */
        if ($isAdmin) {
            $allPermissions = DB::table('permissions')
                ->orderBy('clave')
                ->pluck('clave')
                ->map(fn ($permission) => trim((string) $permission))
                ->filter()
                ->all();

            $permissions = array_values(array_unique(array_merge($permissions, $allPermissions)));
            sort($permissions);
        }

        return $this->contextCache[$userId] = [
            'roles' => $roles,
            'permissions' => $permissions,
            'is_admin' => $isAdmin,
        ];
    }

    /**
     * Refresca la información de autorización almacenada en la sesión.
     */
    public function refreshSession(Request $request, int $userId): array
    {
        $this->forget($userId);
        $context = $this->contextForUser($userId);

        $request->session()->put('swafi_roles', $context['roles']);
        $request->session()->put('swafi_permissions', $context['permissions']);

        return $context;
    }

    /**
     * Valida un permiso usando el contexto vigente de la base de datos.
     */
    public function canCurrentUser(string $permission): bool
    {
        $userId = (int) (Auth::id() ?: session('swafi_user_id'));

        if ($userId <= 0) {
            return false;
        }

        $context = $this->contextForUser($userId);

        return $context['is_admin']
            || in_array(trim($permission), $context['permissions'], true);
    }

    /**
     * Valida un permiso con el contexto ya actualizado de la sesión.
     */
    public function canFromSession(Request $request, string $permission): bool
    {
        $roles = collect($request->session()->get('swafi_roles', []))
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->values()
            ->all();

        $permissions = collect($request->session()->get('swafi_permissions', []))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        $isAdmin = collect($roles)->contains(
            fn (string $role) => $this->normalizeRole($role) === 'administrador swafi'
        );

        return $isAdmin || in_array(trim($permission), $permissions, true);
    }

    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->contextCache = [];

            return;
        }

        unset($this->contextCache[$userId]);
    }

    private function normalizeRole(string $role): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $role) ?: $role));
    }
}

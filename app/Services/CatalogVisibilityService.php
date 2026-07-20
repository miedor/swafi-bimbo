<?php

namespace App\Services;

use Illuminate\Http\Request;

class CatalogVisibilityService
{
    /**
     * Devuelve la clave de permiso específica para consultar un catálogo.
     */
    public function permissionFor(string $catalog): string
    {
        return 'catalogos.' . trim($catalog) . '.ver';
    }

    /**
     * @return array<string, string>
     */
    public function permissionMap(): array
    {
        return collect(CatalogManagementService::CATALOGS)
            ->mapWithKeys(fn (array $definition, string $catalog): array => [
                $catalog => $this->permissionFor($catalog),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function visibleCatalogs(Request $request): array
    {
        $definitions = CatalogManagementService::CATALOGS;

        if ($this->canAdminister($request)) {
            return collect($definitions)
                ->mapWithKeys(fn (array $definition, string $catalog): array => [
                    $catalog => (string) $definition['label'],
                ])
                ->all();
        }

        $permissions = $this->permissions($request);

        return collect($definitions)
            ->filter(
                fn (array $definition, string $catalog): bool => in_array(
                    $this->permissionFor($catalog),
                    $permissions,
                    true
                )
            )
            ->mapWithKeys(fn (array $definition, string $catalog): array => [
                $catalog => (string) $definition['label'],
            ])
            ->all();
    }

    public function firstVisible(Request $request): ?string
    {
        $visible = $this->visibleCatalogs($request);
        $first = array_key_first($visible);

        return is_string($first) && $first !== '' ? $first : null;
    }

    public function canView(Request $request, string $catalog): bool
    {
        if (!array_key_exists($catalog, CatalogManagementService::CATALOGS)) {
            return false;
        }

        if ($this->canAdminister($request)) {
            return true;
        }

        return in_array(
            $this->permissionFor($catalog),
            $this->permissions($request),
            true
        );
    }

    public function canAccessAny(Request $request): bool
    {
        return $this->canAdminister($request)
            || $this->visibleCatalogs($request) !== [];
    }

    public function canAdminister(Request $request): bool
    {
        return $this->isAdministrator($request)
            || in_array('catalogos.administrar', $this->permissions($request), true);
    }

    private function isAdministrator(Request $request): bool
    {
        return collect($request->session()->get('swafi_roles', []))
            ->filter(fn (mixed $role): bool => is_scalar($role))
            ->map(fn (mixed $role): string => mb_strtolower(trim((string) $role)))
            ->contains('administrador swafi');
    }

    /**
     * @return array<int, string>
     */
    private function permissions(Request $request): array
    {
        return collect($request->session()->get('swafi_permissions', []))
            ->filter(fn (mixed $permission): bool => is_scalar($permission))
            ->map(fn (mixed $permission): string => trim((string) $permission))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

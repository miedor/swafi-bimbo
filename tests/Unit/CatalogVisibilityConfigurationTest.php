<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogVisibilityConfigurationTest extends TestCase
{
    public function test_incremental_migration_creates_a_permission_for_each_catalog(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_19_000560_add_catalog_visibility_permissions.php'
        );

        foreach ($this->catalogPermissionKeys() as $permission) {
            self::assertStringContainsString("'{$permission}'", $migration);
        }

        self::assertStringContainsString("private const GENERIC_PERMISSION = 'catalogos.ver';", $migration);
        self::assertStringContainsString('preserveCurrentCatalogVisibility', $migration);
        self::assertStringContainsString("->where('permission_id', \$genericPermissionId)", $migration);
        self::assertStringContainsString("->where('nombre', 'Administrador SWAFI')", $migration);
        self::assertStringContainsString("DB::table('permission_role')->insertOrIgnore", $migration);
    }

    public function test_migration_is_reversible_and_records_hu_106(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_19_000560_add_catalog_visibility_permissions.php'
        );

        self::assertStringContainsString("private const AUDIT_ACTION = 'HABILITA_VISIBILIDAD_CATALOGOS';", $migration);
        self::assertStringContainsString("'registro_clave' => 'HU-106'", $migration);
        self::assertStringContainsString("->whereIn('permission_id', \$permissionIds)", $migration);
        self::assertStringContainsString("->whereIn('id', \$permissionIds)", $migration);
    }

    public function test_visibility_service_uses_specific_permissions_and_keeps_administrator_access(): void
    {
        $service = $this->read('app/Services/CatalogVisibilityService.php');

        self::assertStringContainsString("return 'catalogos.' . trim(\$catalog) . '.ver';", $service);
        self::assertStringContainsString('CatalogManagementService::CATALOGS', $service);
        self::assertStringContainsString('public function visibleCatalogs(Request $request): array', $service);
        self::assertStringContainsString('public function firstVisible(Request $request): ?string', $service);
        self::assertStringContainsString('public function canView(Request $request, string $catalog): bool', $service);
        self::assertStringContainsString("in_array('catalogos.administrar'", $service);
        self::assertStringContainsString("->contains('administrador swafi')", $service);
    }

    public function test_catalog_request_selects_the_first_authorized_catalog_and_blocks_direct_access(): void
    {
        $request = $this->read('app/Http/Requests/CatalogIndexRequest.php');

        self::assertStringContainsString('use App\\Services\\CatalogVisibilityService;', $request);
        self::assertStringContainsString('$visibility->canView($this, $catalog)', $request);
        self::assertStringContainsString('$visibility->canAdminister($this)', $request);
        self::assertStringContainsString('app(CatalogVisibilityService::class)->firstVisible($this)', $request);
        self::assertStringContainsString("'catalogo' => ['required', Rule::in", $request);
    }

    public function test_controller_only_exposes_catalogs_allowed_for_the_current_session(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');

        self::assertStringContainsString('private readonly CatalogVisibilityService $catalogVisibility', $controller);
        self::assertStringContainsString('$catalogosDisponibles = $this->catalogVisibility->visibleCatalogs($request);', $controller);
        self::assertStringContainsString('array_key_exists($catalogoActivo, $catalogosDisponibles)', $controller);
        self::assertStringContainsString('No cuentas con permiso para consultar el catálogo solicitado.', $controller);
        self::assertStringContainsString("'catalogosDisponibles' => \$catalogosDisponibles", $controller);
    }

    public function test_navigation_hides_the_catalog_entry_when_no_specific_catalog_is_available(): void
    {
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $dashboard = $this->read('resources/views/swafi/dashboard.blade.php');

        foreach ([$layout, $dashboard] as $view) {
            self::assertStringContainsString('CatalogVisibilityService::class', $view);
            self::assertStringContainsString('$catalogVisibility->canAccessAny(request())', $view);
            self::assertStringContainsString('$catalogVisibility->firstVisible(request())', $view);
            self::assertStringContainsString('href="{{ $catalogosUrl }}"', $view);
        }

        self::assertStringContainsString("\$swafiCan('catalogos.ver')", $layout);
        self::assertStringContainsString("\$can('catalogos.ver')", $dashboard);
    }

    public function test_seeder_keeps_fresh_installations_aligned_with_the_incremental_migration(): void
    {
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');

        foreach ($this->catalogPermissionKeys() as $permission) {
            self::assertStringContainsString("'clave' => '{$permission}'", $seeder);
        }

        self::assertStringContainsString("\$catalogReadPermissionKeys = array_column(\$catalogReadPermissions, 'clave');", $seeder);
        self::assertGreaterThanOrEqual(3, substr_count($seeder, '], $catalogReadPermissionKeys);'));
    }

    public function test_permission_and_audit_keys_fit_existing_database_columns(): void
    {
        foreach ($this->catalogPermissionKeys() as $permission) {
            self::assertLessThanOrEqual(80, strlen($permission), $permission . ' supera VARCHAR(80).');
        }

        self::assertLessThanOrEqual(
            40,
            strlen('HABILITA_VISIBILIDAD_CATALOGOS'),
            'La acción de bitácora supera VARCHAR(40).'
        );
    }

    public function test_existing_module_permission_and_route_names_are_preserved(): void
    {
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $routes = $this->read('routes/web.php');

        self::assertStringContainsString("'catalogos' => 'catalogos.ver'", $middleware);
        self::assertStringContainsString("->name('catalogos')", $routes);
        self::assertStringContainsString("->name('catalogos.store')", $routes);
        self::assertStringContainsString("->name('catalogos.destroy')", $routes);
        self::assertStringContainsString("->name('catalogos.activate')", $routes);
    }

    /**
     * @return array<int, string>
     */
    private function catalogPermissionKeys(): array
    {
        return [
            'catalogos.proveedores.ver',
            'catalogos.plantas.ver',
            'catalogos.centros_costo.ver',
            'catalogos.categorias_activo.ver',
            'catalogos.tipos_activo.ver',
            'catalogos.estatus_documentales.ver',
            'catalogos.estatus_operativos.ver',
            'catalogos.areas.ver',
            'catalogos.ubicaciones.ver',
            'catalogos.responsables.ver',
        ];
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . ltrim($path, '/'));

        self::assertIsString($contents, "No fue posible leer {$path}.");

        return $contents;
    }
}

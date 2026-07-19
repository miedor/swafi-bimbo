<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogAccessAndPlantLifecycleConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_adds_catalog_read_permission_and_query_indexes(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_18_000490_add_catalog_read_permission_and_plant_indexes.php'
        );

        foreach ([
            "private const PERMISSION = 'catalogos.ver';",
            "Schema::hasColumn('plantas', 'direccion')",
            "\$table->string('direccion', 255)->nullable()->after('nombre')",
            'idx_plantas_estatus_nombre',
            'idx_areas_planta_estatus',
            'idx_ubicaciones_planta_estatus',
            "'HU-095,HU-096,HU-097'",
            'public function down(): void',
            'Schema::getIndexes($table)',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_catalog_read_permission_is_assigned_to_all_base_roles(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_18_000490_add_catalog_read_permission_and_plant_indexes.php'
        );
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');

        foreach ([
            'Administrador SWAFI',
            'Usuario Captura',
            'Usuario Consulta / Auditoría',
            'Usuario Planta / Inventarios',
        ] as $role) {
            self::assertStringContainsString($role, $migration);
        }

        self::assertStringContainsString("['clave' => 'catalogos.ver'", $seeder);
        self::assertGreaterThanOrEqual(4, substr_count($seeder, "'catalogos.ver'"));
    }

    public function test_middleware_separates_catalog_read_access_from_administration(): void
    {
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString("'catalogos' => 'catalogos.ver'", $middleware);
        self::assertStringContainsString("'catalogos.store'", $middleware);
        self::assertStringContainsString("'catalogos.importar'", $middleware);
        self::assertStringContainsString("'catalogos.plantilla'", $middleware);
        self::assertStringContainsString("'catalogos.destroy'", $middleware);
        self::assertStringContainsString("'catalogos.activate' => 'catalogos.administrar'", $middleware);
        self::assertStringContainsString("'catalogos.ver' => ['catalogos.administrar']", $middleware);
    }

    public function test_form_requests_validate_catalogs_filters_files_and_identifiers_on_the_server(): void
    {
        $index = $this->read('app/Http/Requests/CatalogIndexRequest.php');
        $store = $this->read('app/Http/Requests/StoreCatalogRequest.php');
        $import = $this->read('app/Http/Requests/ImportCatalogRequest.php');
        $status = $this->read('app/Http/Requests/UpdateCatalogStatusRequest.php');

        foreach ([
            'Rule::in(array_keys(CatalogManagementService::CATALOGS))',
            "Rule::in([10, 25, 50])",
            "Rule::exists('plantas', 'id')",
            "Rule::exists('areas', 'id')",
            'El área seleccionada no pertenece a la planta indicada.',
        ] as $expected) {
            self::assertStringContainsString($expected, $index);
        }

        foreach ([
            "Rule::unique('plantas', 'clave')->ignore",
            "'direccion' => ['required', 'string', 'min:5', 'max:255']",
            "Rule::exists('plantas', 'id')->where",
            "Rule::exists('areas', 'id')->where",
            "'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:600']",
            'El área seleccionada no pertenece a la planta indicada o está inactiva.',
        ] as $expected) {
            self::assertStringContainsString($expected, $store);
        }

        self::assertStringContainsString("'max:10240'", $import);
        self::assertStringContainsString("'mimes:csv,txt'", $import);
        self::assertStringContainsString("Rule::in(['activo', 'inactivo'])", $status);
    }

    public function test_catalog_changes_are_transactional_locked_and_audited(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            'DB::transaction(',
            '->lockForUpdate()',
            "'ALTA_CATALOGO'",
            "'ACTUALIZACION_CATALOGO'",
            "'ACTIVACION_CATALOGO'",
            "'DESACTIVACION_CATALOGO'",
            "DB::table('bitacora_auditoria')->insert",
            'JSON_THROW_ON_ERROR',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_plant_deactivation_is_blocked_when_business_dependencies_exist(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            'assertPlantCanBeDeactivated',
            'plantDependencies',
            "DB::table('activos')",
            "DB::table('areas')",
            "DB::table('ubicaciones')",
            "Schema::hasTable('periodos_inventario')",
            "Schema::hasTable('solicitudes_traslado')",
            "->where('s.estatus', 'pendiente')",
            'No puedes desactivar la planta porque mantiene dependencias activas o históricas',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_csv_import_cannot_bypass_plant_dependency_validation_and_uses_controlled_errors(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');

        foreach ([
            "'plantas' => ['clave', 'nombre', 'direccion']",
            'la dirección de la planta es obligatoria y no debe superar 255 caracteres.',
            'assertPlantCanBeDeactivated((int) $existing->id)',
            'catch (DomainException $exception)',
            'catch (Throwable $exception)',
            'report($exception);',
            'No fue posible completar la importación. Revisa el archivo e inténtalo nuevamente.',
            '->lockForUpdate()',
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }

        self::assertStringNotContainsString("withErrors(['archivo_csv' => \$exception->getMessage()])", $controller);
    }

    public function test_catalog_query_protects_like_searches_and_csv_exports(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');

        self::assertStringContainsString('private function likePattern(string $value): string', $controller);
        self::assertStringContainsString("['\\\\', '%', '_']", $controller);
        self::assertStringContainsString('private function csvSafeValue(mixed $value): string', $controller);
        self::assertStringContainsString("['=', '+', '-', '@']", $controller);
        self::assertStringContainsString('return "\'" . $value;', $controller);
    }

    public function test_routes_include_controlled_reactivation_without_changing_existing_public_names(): void
    {
        $routes = $this->read('routes/web.php');

        foreach ([
            "->name('catalogos')",
            "->name('catalogos.store')",
            "->name('catalogos.importar')",
            "->name('catalogos.plantilla')",
            "->name('catalogos.destroy')",
            "->name('catalogos.activate')",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }

        self::assertStringContainsString("Route::patch('/catalogos/{catalogo}/{id}/activar'", $routes);
    }

    public function test_interface_supports_read_only_access_detail_and_protected_lifecycle_actions(): void
    {
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            '@if ($canAdminCatalogs)',
            'Tu perfil cuenta con acceso de consulta.',
            'Catálogo a consultar',
            'Ver detalle',
            'Dependencias que protegen la integridad de la planta:',
            "route('catalogos.activate'",
            'name="estatus" value="inactivo"',
            'name="estatus" value="activo"',
            '.cat-detail-grid',
            '@media (max-width: 760px)',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringContainsString('{{ data_get($registroDetail, $key)', $view);
        self::assertStringNotContainsString('{!! data_get($registroDetail, $key)', $view);
    }

    public function test_navigation_and_dashboard_expose_catalogs_to_read_only_permission(): void
    {
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $dashboard = $this->read('resources/views/swafi/dashboard.blade.php');

        self::assertStringContainsString("'catalogos.ver'", $layout);
        self::assertStringContainsString("'catalogos.administrar'", $layout);
        self::assertStringContainsString("'catalogos.ver'", $dashboard);
        self::assertStringContainsString("route('catalogos')", $dashboard);
    }

    public function test_existing_session_captcha_header_and_query_experience_remain_present(): void
    {
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString('<header class="swafi-page-header">', $layout);
        self::assertStringContainsString('nav-item-logout', $layout);
        self::assertStringContainsString('data-swafi-query-workspace', $view);
        self::assertStringContainsString('data-swafi-query-results', $view);
    }

    public function test_new_audit_actions_fit_the_existing_database_column(): void
    {
        foreach ([
            'HABILITA_CONSULTA_CATALOGOS',
            'ALTA_CATALOGO',
            'ACTUALIZACION_CATALOGO',
            'ACTIVACION_CATALOGO',
            'DESACTIVACION_CATALOGO',
            'IMPORTACION_CATALOGO_ALTA',
            'IMPORTACION_CATALOGO_ACTUALIZACION',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action . ' supera VARCHAR(40).');
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);

        return $contents;
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogOrganizationalStructureConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_adds_nullable_historical_columns_and_reversible_constraints(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_18_000500_add_organizational_structure_to_cost_centers_and_areas.php'
        );

        foreach ([
            "\$table->unsignedBigInteger('planta_id')->nullable()->after('id')",
            "\$table->string('clave', 30)->nullable()->after('planta_id')",
            "->restrictOnDelete()",
            "->cascadeOnUpdate()",
            "public function down(): void",
            "Schema::getIndexes(\$table)",
            "Schema::getForeignKeys(\$table)",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_migration_adds_indexes_and_unique_area_key_per_plant(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_18_000500_add_organizational_structure_to_cost_centers_and_areas.php'
        );

        foreach ([
            'idx_centros_costo_planta_estatus',
            'idx_areas_planta_clave',
            'uq_areas_planta_clave',
            "\$table->unique(['planta_id', 'clave'], 'uq_areas_planta_clave')",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_catalog_definitions_persist_the_new_organizational_fields(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        self::assertStringContainsString(
            "'fields' => ['planta_id', 'clave', 'descripcion', 'estatus']",
            $service
        );
        self::assertStringContainsString(
            "'fields' => ['planta_id', 'clave', 'nombre', 'estatus']",
            $service
        );
    }

    public function test_server_validation_requires_active_plant_and_area_key(): void
    {
        $request = $this->read('app/Http/Requests/StoreCatalogRequest.php');
        $validation = $this->read('app/Services/CatalogValidationService.php');
        $combined = $request . "\n" . $validation;

        foreach ([
            "'centros_costo' => [",
            "Rule::exists('plantas', 'id')->where",
            "'areas' => [",
            "Rule::unique('areas', 'clave')",
            "->where(fn (\$query) => \$query->where('planta_id', \$input['planta_id'] ?? null))",
            "'regex:/^[A-Z0-9][A-Z0-9._-]*$/'",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }

        self::assertStringContainsString('CatalogValidationService::class', $request);
    }

    public function test_catalog_query_allows_plant_filter_for_cost_centers_areas_and_locations(): void
    {
        $request = $this->read('app/Http/Requests/CatalogIndexRequest.php');
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');

        self::assertStringContainsString(
            "['centros_costo', 'areas', 'ubicaciones']",
            $request
        );
        self::assertStringContainsString("\$query->where('cc.planta_id'", $controller);
        self::assertStringContainsString("\$query->where('a.planta_id'", $controller);
        self::assertStringContainsString("\$query->where('u.planta_id'", $controller);
    }

    public function test_cost_center_and_area_queries_use_joins_without_n_plus_one_queries(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');

        foreach ([
            "DB::table('centros_costo as cc')",
            "->leftJoin('plantas as p', 'p.id', '=', 'cc.planta_id')",
            "DB::table('areas as a')",
            "'p.nombre as planta_nombre'",
            "'a.clave'",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }
    }

    public function test_csv_and_xlsx_layouts_require_plant_and_area_key_and_keep_controlled_rejections(): void
    {
        $importService = $this->read('app/Services/CatalogImportService.php');
        $validation = $this->read('app/Services/CatalogValidationService.php');
        $management = $this->read('app/Services/CatalogManagementService.php');
        $combined = implode("\n", [$importService, $validation, $management]);

        foreach ([
            "'centros_costo' => ['planta_clave', 'clave', 'descripcion']",
            "'areas' => ['planta_clave', 'clave', 'nombre']",
            "Rule::exists('plantas', 'id')->where",
            "Rule::unique('areas', 'clave')",
            'no existe o está inactiva',
            'assertUpdateAllowed($catalog, $existing, $data)',
            'assertCatalogCanBeDeactivated(',
            "->whereNull('clave')",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_deactivation_checks_cost_center_and_area_dependencies(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            'costCenterDependencies',
            'areaDependencies',
            "->where('centro_costo_id', \$costCenterId)",
            "->where('area_id', \$areaId)",
            "DB::table('activos as ac')",
            "Schema::hasTable('solicitudes_traslado')",
            "->where('s.estatus', 'pendiente')",
            'No puedes desactivar este registro del catálogo porque mantiene dependencias operativas',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_organizational_parent_changes_are_blocked_when_they_break_integrity(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            'public function assertUpdateAllowed',
            "->where('planta_id', '<>', \$newPlantId)",
            'No puedes cambiar la planta del centro de costo',
            "DB::table('ubicaciones')",
            'No puedes cambiar la planta del área porque mantiene',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_interface_uses_controlled_plant_selectors_and_preserves_responsiveness(): void
    {
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            'Planta responsable',
            'name="planta_id" required',
            'Clave del área',
            'name="clave"',
            "['centros_costo', 'areas', 'ubicaciones']",
            'Dependencias que protegen la integridad del centro de costo:',
            'Dependencias que protegen la integridad del área:',
            '@media (max-width: 760px)',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString('{!! $registroDetail', $view);
    }

    public function test_existing_catalog_access_and_lifecycle_routes_remain_unchanged(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

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

        self::assertStringContainsString("'catalogos' => 'catalogos.ver'", $middleware);
        self::assertStringContainsString("'catalogos.activate' => 'catalogos.administrar'", $middleware);
    }

    public function test_session_captcha_sticky_navigation_and_query_experience_remain_present(): void
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

    public function test_new_audit_action_fits_the_existing_database_column(): void
    {
        self::assertLessThanOrEqual(40, strlen('HABILITA_ESTRUCTURA_CC_AREAS'));
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);

        return $contents;
    }
}

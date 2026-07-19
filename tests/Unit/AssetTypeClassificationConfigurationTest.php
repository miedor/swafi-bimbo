<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AssetTypeClassificationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_creates_asset_categories_and_nullable_historical_relation(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000510_add_asset_categories_and_classification.php');

        foreach ([
            "Schema::create('categorias_activo'",
            "\$table->string('clave', 30)->unique()",
            "\$table->string('nombre', 120)->unique()",
            "\$table->unsignedBigInteger('categoria_activo_id')->nullable()->after('id')",
            "->restrictOnDelete()",
            "->cascadeOnUpdate()",
            "public function down(): void",
            "Schema::getIndexes(\$table)",
            "Schema::getForeignKeys(\$table)",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_migration_adds_query_indexes_and_audits_the_new_classification(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000510_add_asset_categories_and_classification.php');

        foreach ([
            'idx_categorias_activo_estatus_nombre',
            'idx_tipos_activo_categoria_estatus',
            'idx_tipos_activo_descripcion',
            "private const AUDIT_ACTION = 'HABILITA_CLASIFICACION_ACTIVOS';",
            "'registro_clave' => 'HU-100,HU-105'",
            "'tabla_afectada' => 'categorias_activo,tipos_activo'",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_catalog_definitions_include_categories_and_classified_asset_types(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        self::assertStringContainsString("'categorias_activo' => [", $service);
        self::assertStringContainsString("'table' => 'categorias_activo'", $service);
        self::assertStringContainsString(
            "'fields' => ['categoria_activo_id', 'clave', 'descripcion', 'vida_util_meses', 'estatus']",
            $service
        );
    }

    public function test_server_validation_requires_active_category_and_unique_type_name(): void
    {
        $request = $this->read('app/Http/Requests/StoreCatalogRequest.php');

        foreach ([
            "'categorias_activo' => [",
            "Rule::unique('categorias_activo', 'clave')->ignore",
            "Rule::unique('categorias_activo', 'nombre')->ignore",
            "'categoria_activo_id' => [",
            "Rule::exists('categorias_activo', 'id')",
            "->where(fn (\$query) => \$query->where('estatus', 'activo'))",
            "Rule::unique('tipos_activo', 'descripcion')->ignore",
            "'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:600']",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_catalog_query_joins_category_without_n_plus_one_and_supports_filtering(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');
        $request = $this->read('app/Http/Requests/CatalogIndexRequest.php');
        $combined = $controller . "\n" . $request;

        foreach ([
            "DB::table('tipos_activo as ta')",
            "->leftJoin('categorias_activo as ca', 'ca.id', '=', 'ta.categoria_activo_id')",
            "'ca.nombre as categoria_nombre'",
            "\$query->where('ta.categoria_activo_id'",
            "'categoria_activo_id' => ['nullable', 'integer', Rule::exists('categorias_activo', 'id')]",
            'El filtro de categoría solo está disponible para tipos de activo.',
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_csv_layouts_require_category_before_importing_asset_types(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');

        foreach ([
            "'categorias_activo' => ['clave', 'nombre', 'descripcion', 'estatus']",
            "'tipos_activo' => ['categoria_clave', 'clave', 'descripcion', 'vida_util_meses', 'estatus']",
            "'tipos_activo' => ['categoria_clave', 'clave', 'descripcion']",
            'la categoria_clave es obligatoria.',
            "DB::table('categorias_activo')",
            "->where('estatus', 'activo')",
            'no existe o está inactiva',
            "'categoria_activo_id' => (int) \$categoriaId",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }
    }

    public function test_category_and_type_deactivation_validate_business_dependencies(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            'assetCategoryDependencies',
            'assetTypeDependencies',
            "->where('categoria_activo_id', \$categoryId)",
            "->where('tipo_activo_id', \$typeId)",
            "->where('activo', true)",
            'No puedes desactivar este registro del catálogo porque mantiene dependencias operativas',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_category_change_is_blocked_when_the_type_has_active_assets(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            "\$catalog === 'tipos_activo'",
            "array_key_exists('categoria_activo_id', \$data)",
            "DB::table('activos')",
            "->where('tipo_activo_id', (int) \$before->id)",
            'No puedes cambiar la categoría del tipo de activo',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_interface_uses_controlled_category_selectors_and_preserves_responsiveness(): void
    {
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            'Clave de categoría',
            'Nombre de categoría',
            'name="categoria_activo_id" required',
            "\$opciones['categorias_activo']",
            'Dependencias que protegen la integridad de la categoría de activo:',
            'Dependencias que protegen la integridad del tipo de activo:',
            '@media (max-width: 760px)',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString('{!! $registroDetail', $view);
    }

    public function test_seeder_supports_fresh_installations_without_plain_text_secrets(): void
    {
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');

        foreach ([
            "DB::table('categorias_activo')->updateOrInsert",
            "'nombre' => 'Maquinaria y equipo'",
            "'categoria_activo_id' => \$categoriaIds[\$tipo['categoria_clave']] ?? null",
        ] as $expected) {
            self::assertStringContainsString($expected, $seeder);
        }

        self::assertStringNotContainsString("'password' =>", $seeder);
    }

    public function test_existing_routes_permissions_and_query_experience_remain_unchanged(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

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
        self::assertStringContainsString('data-swafi-query-workspace', $view);
        self::assertStringContainsString('data-swafi-query-results', $view);
    }

    public function test_session_captcha_header_and_back_button_protection_remain_present(): void
    {
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');

        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString('<header class="swafi-page-header">', $layout);
        self::assertStringContainsString('nav-item-logout', $layout);
    }

    public function test_new_audit_action_fits_the_existing_database_column(): void
    {
        self::assertLessThanOrEqual(40, strlen('HABILITA_CLASIFICACION_ACTIVOS'));
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);
        return $contents;
    }
}

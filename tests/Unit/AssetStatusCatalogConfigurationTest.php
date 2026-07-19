<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AssetStatusCatalogConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_creates_both_status_catalogs_and_preserves_historical_values(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000520_create_asset_status_catalogs.php');

        foreach ([
            "createStatusTable('estatus_documentales'",
            "createStatusTable('estatus_operativos'",
            "\$table->string('clave', 20)->unique()",
            "\$table->boolean('es_sistema')->default(false)",
            'syncHistoricalKeys',
            'Valor conservado automáticamente durante la migración',
            'public function down(): void',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_migration_protects_status_relations_with_foreign_keys_and_indexes(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000520_create_asset_status_catalogs.php');

        foreach ([
            'idx_est_doc_estado_orden',
            'idx_est_op_estado_orden',
            "references('clave')",
            "on('estatus_documentales')",
            "on('estatus_operativos')",
            'restrictOnDelete()',
            "onUpdate('restrict')",
            'fk_activos_estatus_documental',
            'fk_activos_estatus_operativo',
            'fk_expedientes_estatus_documental',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_base_statuses_are_seeded_as_protected_system_values(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000520_create_asset_status_catalogs.php');
        $service = $this->read('app/Services/AssetStatusCatalogService.php');

        foreach ([
            "'completo'",
            "'incompleto'",
            "'observado'",
            "'en_operacion'",
            "'traslado'",
            "'baja'",
            "'es_sistema' => true",
            "'estatus' => 'activo'",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration . "\n" . $service);
        }
    }

    public function test_catalog_service_supplies_active_options_and_safe_fallbacks(): void
    {
        $service = $this->read('app/Services/AssetStatusCatalogService.php');

        foreach ([
            'public function documentaryOptions',
            'public function operationalOptions',
            'public function isActiveDocumentary',
            'public function isActiveOperational',
            'public function normalizeOperationalInput',
            "if (!Schema::hasTable(\$table))",
            "\$query->where('estatus', 'activo')",
            'public function clearCache',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_catalog_definitions_and_server_validation_cover_documentary_and_operational_statuses(): void
    {
        $catalogService = $this->read('app/Services/CatalogManagementService.php');
        $request = $this->read('app/Http/Requests/StoreCatalogRequest.php');
        $validation = $this->read('app/Services/CatalogValidationService.php');
        $combined = $catalogService . "\n" . $request . "\n" . $validation;

        foreach ([
            "'estatus_documentales' => [",
            "'estatus_operativos' => [",
            "'fields' => ['clave', 'nombre', 'descripcion', 'orden', 'estatus']",
            "'regex:/^[a-z][a-z0-9_]*$/'",
            "Rule::unique(\n                        \$catalog === 'estatus_documentales'",
            "'orden' => ['required', 'integer', 'min:1', 'max:999']",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_status_keys_are_immutable_and_system_statuses_cannot_be_deactivated(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            'La clave técnica del estatus no puede modificarse después de su creación.',
            'Los estatus base de SWAFI no pueden desactivarse',
            'estatus base protegido por reglas automáticas de SWAFI',
            'Estatus base requerido por las reglas automáticas de SWAFI',
            'Protegido',
        ] as $expected) {
            self::assertStringContainsString($expected, $service . "\n" . $view);
        }
    }

    public function test_deactivation_checks_existing_assets_and_files_before_changing_status_lifecycle(): void
    {
        $service = $this->read('app/Services/CatalogManagementService.php');

        foreach ([
            'documentaryStatusDependencies',
            'operationalStatusDependencies',
            "->where('estatus_documental', \$status->clave)",
            "->where('estatus', \$status->clave)",
            "->where('estatus_operativo', \$status->clave)",
            'No puedes desactivar este registro del catálogo porque mantiene dependencias operativas',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_catalog_interface_and_csv_import_support_status_management_without_free_text(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');
        $importService = $this->read('app/Services/CatalogImportService.php');
        $view = $this->read('resources/views/swafi/catalogos.blade.php');
        $combined = $controller . "\n" . $importService . "\n" . $view;

        foreach ([
            "'estatus_documentales', 'estatus_operativos' => ['clave', 'nombre', 'descripcion', 'orden', 'estatus']",
            'prepareAssetStatus',
            'Clave técnica',
            'Nombre visible',
            'Orden de presentación',
            'Los estatus base de SWAFI pueden actualizar su nombre y descripción',
            '@media (max-width: 760px)',
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }

        self::assertStringNotContainsString('{!! $registroDetail', $view);
    }

    public function test_registration_and_editing_require_active_operational_catalog_values(): void
    {
        $storeRequest = $this->read('app/Http/Requests/StoreRegistroIndividualRequest.php');
        $registrationController = $this->read('app/Http/Controllers/RegistroIndividualController.php');
        $editController = $this->read('app/Http/Controllers/ExpedienteGestionController.php');
        $registrationView = $this->read('resources/views/swafi/registro-individual.blade.php');
        $editView = $this->read('resources/views/swafi/expediente-editar.blade.php');
        $combined = implode("\n", [
            $storeRequest,
            $registrationController,
            $editController,
            $registrationView,
            $editView,
        ]);

        foreach ([
            "Rule::exists('estatus_operativos', 'clave')",
            "->where(fn (\$query) => \$query->where('estatus', 'activo'))",
            'operationalOptions()',
            "@foreach (\$estatusOperativos as \$estatusOperativo)",
            'El estatus operativo seleccionado no existe o está inactivo.',
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_search_location_reports_and_bulk_listing_use_active_catalog_statuses(): void
    {
        $files = [
            'app/Http/Controllers/BusquedaController.php',
            'app/Http/Controllers/UbicacionInventarioController.php',
            'app/Http/Controllers/ReportesController.php',
            'app/Http/Controllers/RegistroMasivoController.php',
            'resources/views/swafi/busqueda.blade.php',
            'resources/views/swafi/ubicacion.blade.php',
            'resources/views/swafi/reportes.blade.php',
            'resources/views/swafi/registro-masivo.blade.php',
        ];
        $combined = $this->readMany($files);

        foreach ([
            "Rule::exists('estatus_documentales', 'clave')",
            "Rule::exists('estatus_operativos', 'clave')",
            "'estatusDocumentales' => \$this->statusCatalogs->documentaryOptions()",
            "'estatusOperativos' => \$this->statusCatalogs->operationalOptions()",
            "'estatusDocumentales' => \$this->assetStatuses->documentaryOptions()",
            "@foreach ((\$catalogos['estatusDocumentales'] ?? collect()) as \$estatusDocumental)",
            "@foreach(\$catalogos['estatusOperativos'] as \$estatusOperativo)",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_saved_searches_and_saved_reports_reject_inactive_or_manipulated_statuses(): void
    {
        $savedSearch = $this->read('app/Http/Requests/StoreBusquedaGuardadaRequest.php');
        $savedReport = $this->read('app/Http/Controllers/ReporteGuardadoController.php');
        $combined = $savedSearch . "\n" . $savedReport;

        foreach ([
            "Rule::exists('estatus_documentales', 'clave')",
            "Rule::exists('estatus_operativos', 'clave')",
            "->where(fn (\$query) => \$query->where('estatus', 'activo'))",
            'El estatus documental seleccionado no existe o está inactivo.',
            'El estatus operativo seleccionado no existe o está inactivo.',
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_bulk_import_resolves_operational_status_through_the_catalog_service(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        foreach ([
            'private readonly AssetStatusCatalogService $statusCatalogs',
            'normalizeOperationalInput($value)',
            'El estatus operativo no existe, está inactivo o no coincide con la clave técnica del catálogo.',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_existing_routes_permissions_sessions_captcha_and_query_experience_remain_present(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $catalogView = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            "->name('catalogos')",
            "->name('catalogos.store')",
            "->name('catalogos.importar')",
            "->name('catalogos.destroy')",
            "->name('catalogos.activate')",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }

        self::assertStringContainsString("'catalogos' => 'catalogos.ver'", $middleware);
        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString('<header class="swafi-page-header">', $layout);
        self::assertStringContainsString('nav-item-logout', $layout);
        self::assertStringContainsString('data-swafi-query-workspace', $catalogView);
        self::assertStringContainsString('data-swafi-query-results', $catalogView);
    }

    public function test_audit_action_respects_the_existing_column_limit(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000520_create_asset_status_catalogs.php');

        self::assertStringContainsString("private const AUDIT_ACTION = 'HABILITA_CATALOGOS_ESTATUS';", $migration);
        self::assertLessThanOrEqual(40, strlen('HABILITA_CATALOGOS_ESTATUS'));
    }

    private function readMany(array $relativePaths): string
    {
        return implode("\n", array_map(fn (string $path): string => $this->read($path), $relativePaths));
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);

        return $contents;
    }
}

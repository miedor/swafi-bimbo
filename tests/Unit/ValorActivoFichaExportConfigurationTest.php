<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValorActivoFichaExportConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_route_and_middleware_protect_the_individual_value_sheet_export(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString('ValorActivoExportController', $routes);
        self::assertStringContainsString("->where('formato', 'xlsx|pdf')", $routes);
        self::assertStringContainsString("->name('valores.exportar-ficha')", $routes);
        self::assertStringContainsString("'valores.exportar-ficha' => 'valores.ver'", $middleware);
    }

    public function test_incremental_migration_grants_individual_sheet_formats_to_the_capture_role(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_19_000550_grant_value_sheet_export_to_capture_role.php'
        );

        self::assertStringContainsString("private const ROLE_NAME = 'Usuario Captura'", $migration);
        self::assertStringContainsString("'reportes.exportar_excel'", $migration);
        self::assertStringContainsString("'reportes.exportar_pdf'", $migration);
        self::assertStringContainsString("DB::table('permission_role')->insertOrIgnore", $migration);
        self::assertStringContainsString("'HABILITA_FICHA_VALORES_CAPTURA'", $migration);
        self::assertStringContainsString("'HU-039'", $migration);
        self::assertStringContainsString('public function down(): void', $migration);
    }

    public function test_server_request_validates_asset_format_and_active_value_record(): void
    {
        $request = $this->read('app/Http/Requests/ExportValorActivoFichaRequest.php');

        self::assertStringContainsString("Rule::exists('valores_activo', 'numero_activo')", $request);
        self::assertStringContainsString("->where(fn (\$query) => \$query->whereNull('deleted_at'))", $request);
        self::assertStringContainsString("Rule::in(['xlsx', 'pdf'])", $request);
        self::assertStringContainsString("'regex:/^[A-Z0-9._-]+$/'", $request);
        self::assertStringContainsString('prepareForValidation()', $request);
        self::assertStringContainsString("canCurrentUser('valores.administrar')", $request);
        self::assertStringContainsString("canCurrentUser('reportes.valores')", $request);
    }

    public function test_controller_enforces_format_specific_permissions_and_safe_errors(): void
    {
        $controller = $this->read('app/Http/Controllers/ValorActivoExportController.php');

        self::assertStringContainsString("'reportes.exportar_excel'", $controller);
        self::assertStringContainsString("'reportes.exportar_pdf'", $controller);
        self::assertStringContainsString("'reportes.valores'", $controller);
        self::assertStringContainsString("'valores.administrar'", $controller);
        self::assertStringContainsString('SafeExceptionReporter', $controller);
        self::assertStringContainsString('asset_value_sheet_export', $controller);
        self::assertStringContainsString('asset_value_sheet_export_audit', $controller);
        self::assertStringNotContainsString('getMessage()', $controller);
        self::assertStringContainsString("'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0'", $controller);
        self::assertStringContainsString("'X-Content-Type-Options' => 'nosniff'", $controller);
    }

    public function test_exports_reuse_existing_excel_and_pdf_services_and_register_traceability(): void
    {
        $controller = $this->read('app/Http/Controllers/ValorActivoExportController.php');
        $service = $this->read('app/Services/ValorActivoFichaService.php');

        self::assertStringContainsString('$this->xlsxExporter->exportBytes(', $controller);
        self::assertStringContainsString('$this->pdfExporter->export(', $controller);
        self::assertStringContainsString("'EXPORTA_FICHA_VALOR_XLSX'", $controller);
        self::assertStringContainsString("'EXPORTA_FICHA_VALOR_PDF'", $controller);
        self::assertStringContainsString("'tabla_afectada' => 'valores_activo'", $controller);
        self::assertStringContainsString("->where('v.numero_activo', \$numeroActivo)", $service);
        self::assertStringContainsString("->whereNull('v.deleted_at')", $service);
        self::assertStringContainsString("'Ficha fiscal y financiera · ' . \$record->numero_activo", $service);
    }

    public function test_interface_preserves_current_actions_and_adds_accessible_export_links(): void
    {
        $view = $this->read('resources/views/swafi/valores.blade.php');
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');

        foreach (['Consultar', 'Historial', 'Editar', 'Dar de baja', 'Ficha Excel', 'Ficha PDF'] as $feature) {
            self::assertStringContainsString($feature, $view);
        }

        self::assertStringContainsString('aria-label="Exportar ficha fiscal y financiera', $view);
        self::assertStringContainsString("'canExportarExcel'", $controller);
        self::assertStringContainsString("'canExportarPdf'", $controller);
        self::assertStringContainsString("'reportes.exportar_excel'", $controller);
        self::assertStringContainsString("'reportes.exportar_pdf'", $controller);
        self::assertStringContainsString("'reportes.valores'", $controller);
        self::assertStringContainsString("'valores.administrar'", $controller);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, $relativePath);

        return $contents;
    }
}

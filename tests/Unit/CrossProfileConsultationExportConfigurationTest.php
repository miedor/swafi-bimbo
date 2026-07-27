<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CrossProfileConsultationExportConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_advanced_search_export_follows_the_existing_consultation_permission(): void
    {
        $controller = $this->read('app/Http/Controllers/BusquedaController.php');
        $view = $this->read('resources/views/swafi/busqueda.blade.php');

        self::assertStringContainsString("canCurrentUser('expedientes.ver')", $controller);
        self::assertStringNotContainsString("in_array('Administrador SWAFI', \$roles, true)", $controller);

        foreach (['Exportar CSV', 'Exportar Excel', 'Exportar PDF'] as $label) {
            self::assertStringContainsString($label, $view);
        }
    }

    public function test_report_center_does_not_restrict_excel_or_pdf_beyond_module_access(): void
    {
        $controller = $this->read('app/Http/Controllers/ReportesController.php');
        $exportMethod = $this->section($controller, 'private function exportReport(', 'private function registerExportAudit(');

        self::assertStringContainsString("'canExportExcel' => \$this->can('reportes.exportar')", $controller);
        self::assertStringContainsString("'canExportPdf' => \$this->can('reportes.exportar')", $controller);
        self::assertStringContainsString("\$this->can('reportes.exportar')", $exportMethod);
        self::assertStringNotContainsString('reportes.exportar_excel', $exportMethod);
        self::assertStringNotContainsString('reportes.exportar_pdf', $exportMethod);
    }

    public function test_read_only_catalog_users_can_export_only_catalogs_they_can_view(): void
    {
        $request = $this->read('app/Http/Requests/CatalogIndexRequest.php');
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');
        $view = $this->read('resources/views/swafi/catalogos.blade.php');
        $authorize = $this->section($request, 'public function authorize(): bool', 'public function rules(): array');

        self::assertStringContainsString('$visibility->canView($this, $catalog)', $authorize);
        self::assertStringNotContainsString("\$this->filled('export')", $authorize);
        self::assertStringContainsString("Rule::in(['csv', 'xlsx', 'pdf'])", $request);
        self::assertStringContainsString('private function exportCatalog(', $controller);
        self::assertStringContainsString('private const EXPORT_LIMIT = 5000;', $controller);
        self::assertStringContainsString('catalog_list_export', $controller);

        foreach (['Exportar CSV', 'Exportar Excel', 'Exportar PDF'] as $label) {
            self::assertStringContainsString($label, $view);
        }
    }

    public function test_value_exports_preserve_the_data_projection_authorized_for_each_profile(): void
    {
        $request = $this->read('app/Http/Requests/FilterValoresActivoRequest.php');
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');
        $view = $this->read('resources/views/swafi/valores.blade.php');

        self::assertStringContainsString("Rule::in(['csv', 'xlsx', 'pdf'])", $request);
        self::assertStringContainsString("canCurrentUser('valores.ver')", $controller);
        self::assertStringContainsString('exportColumns(bool $includeSensitiveValues)', $controller);
        self::assertStringContainsString("'operativo_basico'", $controller);
        self::assertStringContainsString("'completo'", $controller);
        self::assertStringContainsString('asset_values_list_export', $controller);
        self::assertStringContainsString('Las exportaciones incluyen únicamente las columnas operativas visibles para tu perfil.', $view);

        foreach (['Exportar CSV', 'Exportar Excel', 'Exportar PDF'] as $label) {
            self::assertStringContainsString($label, $view);
        }
    }

    public function test_mass_registration_and_inventory_queries_offer_the_three_formats(): void
    {
        foreach ([
            [
                'controller' => 'app/Http/Controllers/RegistroMasivoController.php',
                'view' => 'resources/views/swafi/registro-masivo.blade.php',
                'context' => 'mass_registration_list_export',
            ],
            [
                'controller' => 'app/Http/Controllers/UbicacionInventarioController.php',
                'view' => 'resources/views/swafi/ubicacion.blade.php',
                'context' => 'inventory_location_list_export',
            ],
        ] as $target) {
            $controller = $this->read($target['controller']);
            $view = $this->read($target['view']);

            self::assertStringContainsString("Rule::in(['csv', 'xlsx', 'pdf'])", $controller);
            self::assertStringContainsString('private const EXPORT_LIMIT = 5000;', $controller);
            self::assertStringContainsString('SimpleXlsxExporter', $controller);
            self::assertStringContainsString('SimplePdfTableExporter', $controller);
            self::assertStringContainsString($target['context'], $controller);

            foreach (['Exportar CSV', 'Exportar Excel', 'Exportar PDF'] as $label) {
                self::assertStringContainsString($label, $view);
            }
        }
    }

    public function test_sensitive_value_history_exports_use_the_same_history_authorization(): void
    {
        $request = $this->read('app/Http/Requests/FilterValorActivoHistoryRequest.php');
        $controller = $this->read('app/Http/Controllers/ValorActivoHistoryController.php');
        $service = $this->read('app/Services/ValorActivoHistoryService.php');
        $view = $this->read('resources/views/swafi/valores-historial.blade.php');

        self::assertStringContainsString("canCurrentUser('valores.administrar')", $request);
        self::assertStringContainsString("canCurrentUser('reportes.valores')", $request);
        self::assertStringContainsString("Rule::in(['csv', 'xlsx', 'pdf'])", $request);
        self::assertStringContainsString('public function exportRows(', $service);
        self::assertStringContainsString('asset_value_history_export', $controller);
        self::assertStringContainsString('private const EXPORT_LIMIT = 5000;', $controller);

        foreach (['Exportar CSV', 'Exportar Excel', 'Exportar PDF'] as $label) {
            self::assertStringContainsString($label, $view);
        }
    }

    private function section(string $contents, string $startMarker, string $endMarker): string
    {
        $start = strpos($contents, $startMarker);
        $end = strpos($contents, $endMarker, $start === false ? 0 : $start + strlen($startMarker));

        self::assertNotFalse($start, "No se encontró {$startMarker}.");
        self::assertNotFalse($end, "No se encontró {$endMarker}.");

        return substr($contents, (int) $start, (int) $end - (int) $start);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, $relativePath);

        return $contents;
    }
}

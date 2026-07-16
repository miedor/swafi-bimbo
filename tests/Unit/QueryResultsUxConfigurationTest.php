<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QueryResultsUxConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_layout_loads_the_shared_query_results_assets(): void
    {
        $layout = $this->read('resources/views/layouts/app.blade.php');

        self::assertStringContainsString('assets/swafi/css/swafi-query-results.css', $layout);
        self::assertStringContainsString('assets/swafi/js/swafi-query-results.js', $layout);
    }

    public function test_shared_script_focuses_results_without_interfering_with_exports(): void
    {
        $script = $this->read('public/assets/swafi/js/swafi-query-results.js');

        self::assertStringContainsString("const FOCUS_PARAMETER = 'swafi_focus';", $script);
        self::assertStringContainsString("submitter.name === 'export'", $script);
        self::assertStringContainsString('addFocusMarker(form, key);', $script);
        self::assertStringContainsString('scrollToElement(results, \'auto\');', $script);
        self::assertStringContainsString('panel.hidden = true;', $script);
        self::assertStringContainsString('panel.hidden = false;', $script);
        self::assertStringContainsString('Modificar filtros', $script);
        self::assertStringContainsString('VALIDATION_ERROR_SELECTOR', $script);
        self::assertStringContainsString('requestedFocus === key && !hasValidationErrors', $script);
    }

    public function test_advanced_search_collapses_the_complete_filter_and_saved_search_area(): void
    {
        $view = $this->read('resources/views/swafi/busqueda.blade.php');

        self::assertStringContainsString('data-swafi-query-key="busqueda"', $view);
        self::assertStringContainsString('class="search-top-grid" data-swafi-query-panel', $view);
        self::assertStringContainsString('id="swafiSearchFiltersForm"', $view);
        self::assertStringContainsString('data-swafi-query-form', $view);
        self::assertStringContainsString('id="swafi-busqueda-resultados"', $view);

        // Las funciones existentes deben permanecer disponibles.
        self::assertStringContainsString('Guardar búsqueda', $view);
        self::assertStringContainsString('Exportar CSV', $view);
        self::assertStringContainsString('Exportar Excel', $view);
        self::assertStringContainsString('Exportar PDF', $view);
    }

    public function test_all_primary_filter_pages_use_the_same_focus_pattern(): void
    {
        $views = [
            'resources/views/swafi/dashboard.blade.php' => ['dashboard', 'swafi-dashboard-resultados'],
            'resources/views/swafi/registro-masivo.blade.php' => ['registro-masivo', 'swafi-registro-masivo-resultados'],
            'resources/views/swafi/valores.blade.php' => ['valores', 'swafi-valores-resultados'],
            'resources/views/swafi/ubicacion.blade.php' => ['ubicacion', 'swafi-ubicacion-resultados'],
            'resources/views/swafi/reportes.blade.php' => ['reportes', 'swafi-reportes-resultados'],
            'resources/views/swafi/catalogos.blade.php' => ['catalogos', 'swafi-catalogos-resultados'],
            'resources/views/swafi/seguridad.blade.php' => ['seguridad-usuarios', 'swafi-seguridad-usuarios-resultados'],
        ];

        foreach ($views as $path => [$key, $resultId]) {
            $view = $this->read($path);

            self::assertStringContainsString('data-swafi-query-key="'.$key.'"', $view, $path);
            self::assertStringContainsString('data-swafi-query-panel', $view, $path);
            self::assertStringContainsString('data-swafi-query-form', $view, $path);
            self::assertStringContainsString('id="'.$resultId.'"', $view, $path);
        }
    }

    public function test_security_audit_filters_focus_their_own_result_table(): void
    {
        $view = $this->read('resources/views/swafi/seguridad.blade.php');

        self::assertStringContainsString('data-swafi-query-key="seguridad-bitacora"', $view);
        self::assertStringContainsString('id="swafi-seguridad-bitacora-resultados"', $view);
        self::assertStringContainsString('Filtros de bitácora', $view);
        self::assertStringContainsString('Bitácora de auditoría', $view);
    }

    public function test_saved_searches_and_reports_redirect_directly_to_visible_results(): void
    {
        $savedSearchController = $this->read('app/Http/Controllers/BusquedaGuardadaController.php');
        $savedReportController = $this->read('app/Http/Controllers/ReporteGuardadoController.php');

        self::assertStringContainsString("\$filtros['swafi_focus'] = 'busqueda';", $savedSearchController);
        self::assertStringContainsString("\$parameters['swafi_focus'] = 'reportes';", $savedReportController);
    }

    public function test_component_styles_keep_results_below_the_sticky_header_and_are_responsive(): void
    {
        $styles = $this->read('public/assets/swafi/css/swafi-query-results.css');

        self::assertStringContainsString('[data-swafi-query-panel][hidden]', $styles);
        self::assertStringContainsString('scroll-margin-top: 128px;', $styles);
        self::assertStringContainsString('@media (max-width: 700px)', $styles);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}

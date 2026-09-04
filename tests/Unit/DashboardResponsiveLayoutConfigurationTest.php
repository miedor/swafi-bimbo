<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DashboardResponsiveLayoutConfigurationTest extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2).'/resources/views/swafi/dashboard.blade.php';
        $view = file_get_contents($path);

        self::assertIsString($view, 'No fue posible leer la vista del Dashboard de SWAFI.');

        $this->view = $view;
    }

    public function test_dashboard_root_grid_cannot_grow_beyond_the_available_main_column(): void
    {
        self::assertMatchesRegularExpression(
            '/\.dash-exec-shell\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\);[^}]*max-width:\s*100%;[^}]*min-width:\s*0;/s',
            $this->view,
            'El contenedor principal debe limitar su pista al ancho disponible y permitir que sus hijos se contraigan.'
        );

        self::assertMatchesRegularExpression(
            '/\.dash-exec-shell\s*>\s*\*,\s*\.dash-top-row\s*>\s*\*,\s*\.dash-kpi-row\s*>\s*\*,\s*\.dash-panel-grid\s*>\s*\*\s*\{[^}]*min-width:\s*0;/s',
            $this->view,
            'Los hijos de las rejillas no deben imponer su ancho mínimo de contenido al Dashboard.'
        );
    }

    public function test_dashboard_uses_container_queries_for_the_real_content_width(): void
    {
        self::assertStringContainsString('container-type: inline-size;', $this->view);
        self::assertStringContainsString('container-name: swafi-dashboard;', $this->view);

        foreach (['1180px', '860px', '620px'] as $breakpoint) {
            self::assertStringContainsString("@container swafi-dashboard (max-width: {$breakpoint})", $this->view);
        }

        self::assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr));', $this->view);
        self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $this->view);
    }

    public function test_tabs_and_tables_scroll_inside_their_own_cards_instead_of_expanding_the_page(): void
    {
        self::assertMatchesRegularExpression(
            '/\.dash-tabs-card\s*\{[^}]*min-width:\s*0;[^}]*max-width:\s*100%;[^}]*overflow:\s*hidden;/s',
            $this->view
        );

        self::assertMatchesRegularExpression(
            '/\.dash-tabbar\s*\{[^}]*max-width:\s*100%;[^}]*min-width:\s*0;[^}]*overflow-x:\s*auto;/s',
            $this->view
        );

        self::assertMatchesRegularExpression(
            '/\.dash-table-wrap\s*\{[^}]*max-width:\s*100%;[^}]*min-width:\s*0;[^}]*overflow-x:\s*auto;[^}]*overflow-y:\s*auto;/s',
            $this->view
        );
    }

    public function test_existing_dashboard_workflows_and_navigation_controls_are_preserved(): void
    {
        foreach ([
            'data-dashboard-tab="seguimiento"',
            'data-dashboard-tab="asignaciones"',
            'data-dashboard-tab="validaciones"',
            'data-dashboard-tab="transferencias-aprobar"',
            'data-dashboard-tab="mis-transferencias"',
            'data-dashboard-tab="resumen"',
            'data-dashboard-tab="documentos"',
            'data-dashboard-tab="accesos"',
            'data-swafi-query-form',
            'id="swafi-dashboard-resultados"',
        ] as $expected) {
            self::assertStringContainsString($expected, $this->view, $expected);
        }
    }
}

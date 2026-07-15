<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PtiiNavigationRegressionTest extends TestCase
{
    private string $layout;

    protected function setUp(): void
    {
        parent::setUp();

        $layoutPath = dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php';
        $layout = file_get_contents($layoutPath);

        self::assertIsString($layout, 'No fue posible leer el layout principal de SWAFI.');

        $this->layout = $layout;
    }

    public function test_page_header_remains_sticky_during_vertical_scroll(): void
    {
        self::assertStringContainsString('.swafi-page-header {', $this->layout);
        self::assertMatchesRegularExpression(
            '/\.swafi-page-header\s*\{[^}]*position:\s*sticky\s*!important;[^}]*top:\s*0\s*!important;[^}]*z-index:\s*999\s*!important;/s',
            $this->layout,
            'El encabezado principal debe permanecer fijo durante el desplazamiento.'
        );
    }

    public function test_sticky_header_ancestors_do_not_create_vertical_scroll_containers(): void
    {
        self::assertMatchesRegularExpression(
            '/html,\s*body\s*\{[^}]*overflow-x:\s*clip;/s',
            $this->layout
        );
        self::assertMatchesRegularExpression(
            '/\.app-shell\s*\{[^}]*overflow:\s*visible;[^}]*overflow-x:\s*clip;/s',
            $this->layout
        );
        self::assertMatchesRegularExpression(
            '/\.main\s*\{[^}]*overflow:\s*visible\s*!important;[^}]*overflow-x:\s*clip\s*!important;/s',
            $this->layout
        );

        self::assertDoesNotMatchRegularExpression(
            '/\.main\s*\{[^}]*overflow-x:\s*hidden\s*!important;/s',
            $this->layout,
            'overflow-x:hidden en .main rompe el comportamiento sticky del encabezado.'
        );
    }

    public function test_dashboard_and_user_controls_stay_inside_the_sticky_header(): void
    {
        $headerStart = strpos($this->layout, '<header class="swafi-page-header">');
        $headerEnd = strpos($this->layout, '</header>', $headerStart ?: 0);

        self::assertNotFalse($headerStart, 'No se encontró el encabezado principal.');
        self::assertNotFalse($headerEnd, 'No se encontró el cierre del encabezado principal.');

        $headerMarkup = substr(
            $this->layout,
            (int) $headerStart,
            ((int) $headerEnd - (int) $headerStart) + strlen('</header>')
        );

        self::assertStringContainsString('class="swafi-dashboard-slot"', $headerMarkup);
        self::assertStringContainsString('class="global-dashboard-btn"', $headerMarkup);
        self::assertStringContainsString('class="swafi-user-slot"', $headerMarkup);
        self::assertStringContainsString('class="swafi-profile-toggle"', $headerMarkup);
    }

    public function test_primary_sidebar_remains_visible_on_desktop(): void
    {
        $stylesheetPath = dirname(__DIR__, 2).'/public/assets/swafi/css/swafi.css';
        $stylesheet = file_get_contents($stylesheetPath);

        self::assertIsString($stylesheet, 'No fue posible leer la hoja de estilos principal.');
        self::assertMatchesRegularExpression(
            '/\.sidebar\s*\{[^}]*position:\s*sticky;[^}]*top:\s*0;[^}]*height:\s*100vh;/s',
            $stylesheet,
            'El menú lateral debe permanecer visible en resoluciones de escritorio.'
        );
    }
}

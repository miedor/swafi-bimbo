<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PtiiAccessibilityResponsiveConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_authenticated_layout_has_keyboard_landmarks_and_current_page_state(): void
    {
        $layout = $this->read('resources/views/layouts/app.blade.php');

        self::assertStringContainsString('class="swafi-skip-link"', $layout);
        self::assertStringContainsString('href="#swafi-main-content"', $layout);
        self::assertStringContainsString('id="swafi-main-content"', $layout);
        self::assertStringContainsString('tabindex="-1"', $layout);
        self::assertStringContainsString('aria-current="page"', $layout);
        self::assertStringContainsString('aria-controls="swafi-nav-m01"', $layout);
        self::assertStringContainsString('aria-controls="swafi-profile-dropdown"', $layout);
        self::assertStringContainsString('id="swafiAccessibilityStatus"', $layout);
    }

    public function test_public_authentication_views_have_a_skip_link_and_main_landmark(): void
    {
        foreach ([
            'resources/views/auth/login.blade.php',
            'resources/views/auth/forgot-password.blade.php',
            'resources/views/auth/reset-password.blade.php',
        ] as $path) {
            $view = $this->read($path);
            self::assertStringContainsString('class="swafi-skip-link"', $view, $path);
            self::assertStringContainsString('id="swafi-main-content"', $view, $path);
            self::assertStringContainsString('swafi-ptii.css', $view, $path);
        }
    }

    public function test_error_and_welcome_views_expose_a_keyboard_main_landmark(): void
    {
        foreach ([
            'resources/views/errors/layout.blade.php',
            'resources/views/welcome.blade.php',
        ] as $path) {
            $view = $this->read($path);
            self::assertStringContainsString('class="swafi-skip-link"', $view, $path);
            self::assertStringContainsString('id="swafi-main-content"', $view, $path);
            self::assertStringContainsString('tabindex="-1"', $view, $path);
            self::assertStringContainsString('swafi-ptii.css', $view, $path);
        }
    }

    public function test_global_responsive_layer_covers_required_breakpoints_and_controls(): void
    {
        $css = $this->read('public/assets/swafi/css/swafi-ptii.css');

        foreach (['1024px', '768px', '480px'] as $breakpoint) {
            self::assertStringContainsString("max-width: {$breakpoint}", $css);
        }

        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
        self::assertStringContainsString('forced-colors: active', $css);
        self::assertStringContainsString('overflow-x: auto', $css);
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('max-height: 90vh', $css);
        self::assertStringContainsString('flex-wrap: wrap', $css);
    }

    public function test_main_functions_remain_reachable_from_the_persistent_menu_in_at_most_two_actions(): void
    {
        $layout = $this->read('resources/views/layouts/app.blade.php');

        foreach ([
            "route('registro-individual')",
            "route('registro-masivo')",
            "route('valores')",
            "route('ubicacion')",
            "route('busqueda')",
            "route('reportes')",
            "route('catalogos')",
            "route('seguridad', ['tab' => 'usuarios'])",
        ] as $routeReference) {
            self::assertStringContainsString($routeReference, $layout, $routeReference);
        }

        self::assertStringContainsString("route('dashboard')", $layout);
        self::assertStringContainsString('data-nav-toggle="m01"', $layout);
        self::assertStringContainsString('data-nav-toggle="m04"', $layout);
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->root.'/'.$relative);
        self::assertIsString($contents, $relative);

        return $contents;
    }
}

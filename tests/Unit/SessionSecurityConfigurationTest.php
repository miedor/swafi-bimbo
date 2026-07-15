<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SessionSecurityConfigurationTest extends TestCase
{
    public function test_logout_and_heartbeat_routes_use_post(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("Route::post('/logout'", $routes);
        $this->assertStringNotContainsString("Route::get('/logout'", $routes);
        $this->assertStringContainsString("Route::post('/sesion/actividad'", $routes);
    }

    public function test_browser_history_guard_closes_the_session(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/public/assets/swafi/js/swafi-session.js');

        $this->assertIsString($script);
        $this->assertStringContainsString("addEventListener('popstate'", $script);
        $this->assertStringContainsString("event.persisted", $script);
        $this->assertStringContainsString("navigationEntry.type === 'back_forward'", $script);
        $this->assertStringContainsString("terminateSession('navegacion_atras')", $script);
        $this->assertStringContainsString("terminateSession('cache_restaurada')", $script);
    }

    public function test_session_defaults_are_strict(): void
    {
        $configuration = file_get_contents(dirname(__DIR__, 2).'/config/session.php');

        $this->assertIsString($configuration);
        $this->assertStringContainsString("env('SESSION_LIFETIME', 10)", $configuration);
        $this->assertStringContainsString("env('SESSION_EXPIRE_ON_CLOSE', true)", $configuration);
        $this->assertStringContainsString("env('SESSION_ENCRYPT', true)", $configuration);
        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'strict')", $configuration);
    }

    public function test_sensitive_pages_load_the_session_guard(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

        $this->assertIsString($layout);
        $this->assertStringContainsString('swafi-session.js', $layout);
        $this->assertStringContainsString('data-swafi-logout-url', $layout);
        $this->assertStringContainsString('data-swafi-heartbeat-url', $layout);
        $this->assertStringContainsString('method="POST"', $layout);
    }
}

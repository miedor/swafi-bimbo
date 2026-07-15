<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    public function test_login_response_is_not_cached(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertIsString($cacheControl);

        $actualDirectives = array_values(array_filter(
            array_map(
                static fn (string $directive): string => strtolower(trim($directive)),
                explode(',', $cacheControl)
            ),
            static fn (string $directive): bool => $directive !== ''
        ));

        $expectedDirectives = [
            'max-age=0',
            'must-revalidate',
            'no-cache',
            'no-store',
            'private',
        ];

        sort($actualDirectives);
        sort($expectedDirectives);

        $this->assertSame(
            $expectedDirectives,
            $actualDirectives,
            'Cache-Control debe contener exactamente las directivas de no almacenamiento, sin depender de su orden.'
        );
    }

    public function test_logout_only_accepts_post_requests(): void
    {
        $this->get('/logout')->assertStatus(405);

        $this->post('/logout', [
            'motivo' => 'manual',
        ])->assertRedirect(route('login', ['motivo' => 'manual']));
    }

    public function test_stale_swafi_session_cannot_reenter_a_protected_route(): void
    {
        $response = $this
            ->withSession([
                'swafi_autenticado' => true,
                'swafi_user_id' => 999,
            ])
            ->get('/dashboard');

        $response->assertRedirect(route('login', ['motivo' => 'sesion_invalida']));
        $this->assertFalse((bool) session('swafi_autenticado', false));
    }

    public function test_login_does_not_offer_a_persistent_session(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Recordar sesión')
            ->assertSee('Sesión no persistente por seguridad');
    }
}

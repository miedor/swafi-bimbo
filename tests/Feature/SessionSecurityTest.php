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
            ->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
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

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GlobalExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::get('/__swafi-tests/error-403', static fn () => abort(403));
        Route::get('/__swafi-tests/error-419', static fn () => abort(419));
        Route::get('/__swafi-tests/error-422', static fn () => abort(422));
        Route::get('/__swafi-tests/error-500', static function (): never {
            throw new RuntimeException('Detalle interno que nunca debe mostrarse al usuario.');
        });
    }

    public function test_403_has_a_controlled_swafi_response(): void
    {
        $response = $this->get('/__swafi-tests/error-403');

        $response
            ->assertStatus(403)
            ->assertSeeText('No tienes permiso para esta operación')
            ->assertDontSee('Stack trace');

        $this->assertProtectedErrorHeaders($response->baseResponse);
    }

    public function test_404_has_a_controlled_swafi_response(): void
    {
        $response = $this->get('/__swafi-tests/recurso-inexistente');

        $response
            ->assertStatus(404)
            ->assertSeeText('No encontramos la página solicitada');

        $this->assertProtectedErrorHeaders($response->baseResponse);
    }

    public function test_419_explains_the_session_expiration_without_exposing_details(): void
    {
        $response = $this->get('/__swafi-tests/error-419');

        $response
            ->assertStatus(419)
            ->assertSeeText('La sesión o el formulario expiró')
            ->assertSeeText('Volver a iniciar sesión');

        $this->assertProtectedErrorHeaders($response->baseResponse);
    }

    public function test_422_has_a_controlled_swafi_response(): void
    {
        $response = $this->get('/__swafi-tests/error-422');

        $response
            ->assertStatus(422)
            ->assertSeeText('No fue posible procesar la solicitud');

        $this->assertProtectedErrorHeaders($response->baseResponse);
    }

    public function test_500_hides_the_internal_exception_and_shows_a_support_reference(): void
    {
        $response = $this->get('/__swafi-tests/error-500');

        $response
            ->assertStatus(500)
            ->assertSeeText('Ocurrió un error inesperado')
            ->assertSeeText('Referencia para soporte')
            ->assertDontSee('Detalle interno que nunca debe mostrarse al usuario.')
            ->assertDontSee('RuntimeException');

        $this->assertProtectedErrorHeaders($response->baseResponse);
    }

    public function test_normal_web_responses_also_receive_a_request_identifier(): void
    {
        $response = $this->get('/login');

        $response->assertOk()->assertHeader('X-SWAFI-Request-ID');

        self::assertMatchesRegularExpression(
            '/^[A-Za-z0-9._-]{8,100}$/',
            (string) $response->headers->get('X-SWAFI-Request-ID')
        );
    }

    private function assertProtectedErrorHeaders(Response $response): void
    {
        $requestId = (string) $response->headers->get('X-SWAFI-Request-ID');
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9._-]{8,100}$/', $requestId);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('no-cache', $cacheControl);
        self::assertSame('no-cache', $response->headers->get('Pragma'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }
}

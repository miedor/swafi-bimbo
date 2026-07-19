<?php

use App\Http\Middleware\AssignSwafiRequestId;
use App\Http\Middleware\NoCacheResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SwafiAuth;
use App\Services\SecurityHeaderPolicy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |--------------------------------------------------------------------------
        | Identificador global de solicitudes
        |--------------------------------------------------------------------------
        | Se ejecuta incluso cuando no existe una ruta coincidente, por lo que los
        | errores 404 y 500 también pueden mostrar una referencia de seguimiento.
        */
        $middleware->prepend(AssignSwafiRequestId::class);

        /*
        |--------------------------------------------------------------------------
        | Middleware personalizado SWAFI
        |--------------------------------------------------------------------------
        | NoCacheResponse protege las respuestas web y swafi.auth restringe el
        | acceso a las rutas internas de acuerdo con la sesión y los permisos.
        */
        $middleware->web(append: [
            SecurityHeaders::class,
            NoCacheResponse::class,
        ]);

        $middleware->alias([
            'swafi.auth' => SwafiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /* Evita registrar dos veces la misma instancia de una excepción. */
        $exceptions->dontReportDuplicates();

        /*
        |--------------------------------------------------------------------------
        | Contexto técnico seguro para los registros de Laravel Cloud
        |--------------------------------------------------------------------------
        | No se guardan contraseñas, tokens, contenido de formularios ni datos de
        | archivos. El identificador permite relacionar la pantalla con el log.
        */
        $exceptions->context(function (): array {
            if (! app()->bound('request')) {
                return [
                    'application' => 'SWAFI',
                    'execution' => 'console',
                ];
            }

            /** @var Request $request */
            $request = request();
            $route = $request->route();

            return [
                'application' => 'SWAFI',
                'swafi_request_id' => $request->attributes->get('swafi_request_id'),
                'http_method' => $request->method(),
                'request_path' => '/'.ltrim($request->path(), '/'),
                'route_name' => is_object($route) && method_exists($route, 'getName')
                    ? $route->getName()
                    : null,
            ];
        });

        /* Las solicitudes JSON conservan el formato esperado por integraciones. */
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request, \Throwable $exception): bool =>
                $request->is('api/*') || $request->expectsJson()
        );

        /*
        |--------------------------------------------------------------------------
        | Endurecimiento de todas las respuestas de error
        |--------------------------------------------------------------------------
        | Las vistas resources/views/errors se encargan del contenido visual. Este
        | bloque garantiza referencia, no caché y cabeceras defensivas incluso si
        | el error ocurrió antes de ejecutar el middleware del grupo web.
        */
        $exceptions->respond(function (Response $response): Response {
            if (! app()->bound('request')) {
                return $response;
            }

            /** @var Request $request */
            $request = request();
            $requestId = trim((string) $request->attributes->get('swafi_request_id', ''));

            if ($requestId === '') {
                $requestId = (string) Str::uuid();
                $request->attributes->set('swafi_request_id', $requestId);
            }

            $response->headers->set('X-SWAFI-Request-ID', $requestId);
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
            $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

            if ((bool) config('swafi.security_headers.csp_enabled', true)) {
                $nonce = trim((string) $request->attributes->get('csp_nonce', ''));

                if ($nonce === '') {
                    $nonce = app(SecurityHeaderPolicy::class)->nonce();
                    $request->attributes->set('csp_nonce', $nonce);
                }

                $header = (bool) config('swafi.security_headers.csp_report_only', false)
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy';

                $response->headers->set(
                    $header,
                    app(SecurityHeaderPolicy::class)->contentSecurityPolicy($nonce)
                );
            }

            return $response;
        });
    })->create();

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoCacheResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
        |--------------------------------------------------------------------------
        | Protección contra contenido sensible almacenado en caché
        |--------------------------------------------------------------------------
        | Estas cabeceras se aplican también al login y a las redirecciones para
        | impedir que el navegador restaure pantallas privadas después del cierre
        | de sesión mediante Atrás, Adelante o la caché de navegación (BFCache).
        */
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        /* Cabeceras defensivas que no alteran la operación normal de SWAFI. */
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}

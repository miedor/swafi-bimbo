<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwafiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
        |--------------------------------------------------------------------------
        | Validación de sesión SWAFI
        |--------------------------------------------------------------------------
        | Este middleware protege las rutas internas del prototipo.
        | Si el usuario no inició sesión correctamente, se redirige al login.
        */

        if (!$request->session()->get('swafi_autenticado')) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'usuario' => 'Debes iniciar sesión para acceder al sistema SWAFI.',
                ]);
        }

        $response = $next($request);

        /*
        |--------------------------------------------------------------------------
        | Evitar caché de pantallas internas
        |--------------------------------------------------------------------------
        | Esto ayuda a que después de cerrar sesión el navegador no conserve vistas
        | internas al regresar con el botón "Atrás".
        */

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        return $response;
    }
}

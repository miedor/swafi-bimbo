<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignSwafiRequestId
{
    private const ATTRIBUTE = 'swafi_request_id';

    private const HEADER = 'X-SWAFI-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->attributes->set(self::ATTRIBUTE, $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $candidate = trim((string) $request->headers->get(self::HEADER, ''));

        /*
        |--------------------------------------------------------------------------
        | Aceptar identificadores confiables del proxy o generar uno propio
        |--------------------------------------------------------------------------
        | Se limita el formato para evitar que valores arbitrarios o saltos de
        | línea terminen en cabeceras o registros técnicos de SWAFI.
        */
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]{8,100}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}

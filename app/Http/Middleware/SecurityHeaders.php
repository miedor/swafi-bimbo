<?php

namespace App\Http\Middleware;

use App\Services\SecurityHeaderPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public const NONCE_ATTRIBUTE = 'csp_nonce';

    public function __construct(private readonly SecurityHeaderPolicy $policy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->policy->nonce();
        $request->attributes->set(self::NONCE_ATTRIBUTE, $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        if ((bool) config('swafi.security_headers.csp_enabled', true)) {
            $header = (bool) config('swafi.security_headers.csp_report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->policy->contentSecurityPolicy($nonce));
        }

        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        return $response;
    }
}

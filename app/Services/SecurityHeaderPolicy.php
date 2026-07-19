<?php

namespace App\Services;

final class SecurityHeaderPolicy
{
    public function nonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public function contentSecurityPolicy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://www.google.com https://www.gstatic.com",
            "script-src-attr 'none'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.bunny.net",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self' https://www.google.com https://www.gstatic.com https://fonts.bunny.net",
            "frame-src https://www.google.com https://recaptcha.google.com",
            "media-src 'self'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];

        if (app()->environment('production')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives) . ';';
    }
}

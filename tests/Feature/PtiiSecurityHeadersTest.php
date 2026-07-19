<?php

namespace Tests\Feature;

use Tests\TestCase;

class PtiiSecurityHeadersTest extends TestCase
{
    public function test_login_response_uses_an_enforced_nonce_based_content_security_policy(): void
    {
        config([
            'swafi.security_headers.csp_enabled' => true,
            'swafi.security_headers.csp_report_only' => false,
        ]);

        $response = $this->get('/login');

        $response->assertOk()->assertHeader('Content-Security-Policy');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $body = (string) $response->getContent();

        self::assertStringContainsString("default-src 'self'", $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("script-src-attr 'none'", $policy);
        self::assertStringContainsString("frame-ancestors 'self'", $policy);
        self::assertStringContainsString("form-action 'self'", $policy);
        self::assertMatchesRegularExpression("/script-src[^;]*'nonce-[A-Za-z0-9_-]{20,40}'/", $policy);

        preg_match("/'nonce-([A-Za-z0-9_-]{20,40})'/", $policy, $matches);
        $nonce = $matches[1] ?? '';

        self::assertNotSame('', $nonce);
        self::assertStringContainsString('nonce="'.$nonce.'"', $body);
        self::assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        self::assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
        self::assertSame('none', $response->headers->get('X-Permitted-Cross-Domain-Policies'));
    }

    public function test_login_old_input_is_rendered_as_escaped_text_instead_of_executable_html(): void
    {
        $payload = '<img src=x onerror=alert("SWAFI-XSS")>';

        $response = $this
            ->withSession(['_old_input' => ['usuario' => $payload]])
            ->get('/login');

        $response->assertOk();
        self::assertStringContainsString(e($payload), (string) $response->getContent());
        self::assertStringNotContainsString('value="'.$payload.'"', (string) $response->getContent());
    }

    public function test_report_only_mode_does_not_emit_the_enforcement_header(): void
    {
        config([
            'swafi.security_headers.csp_enabled' => true,
            'swafi.security_headers.csp_report_only' => true,
        ]);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertHeader('Content-Security-Policy-Report-Only')
            ->assertHeaderMissing('Content-Security-Policy');
    }
}

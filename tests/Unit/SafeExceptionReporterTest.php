<?php

namespace Tests\Unit;

use App\Services\SafeExceptionReporter;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SafeExceptionReporterTest extends TestCase
{
    public function test_warning_logs_only_safe_operational_metadata(): void
    {
        Log::spy();

        $exception = new RuntimeException(
            'Detalle que podría contener password=NoDebeRegistrarse token=NoDebeRegistrarse'
        );

        app(SafeExceptionReporter::class)->warning(
            $exception,
            'password_reset_mail_send',
            [
                'user_id' => 25,
                'route_name' => 'password.email',
                'password' => 'ClaveNoDebeRegistrarse',
                'nested' => [
                    'token' => 'TokenNoDebeRegistrarse',
                    'attempt' => 3,
                ],
                'documento' => '<xml>ContenidoNoDebeRegistrarse</xml>',
            ]
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = json_encode($context, JSON_UNESCAPED_UNICODE);

                self::assertSame(
                    'SWAFI detectó un fallo técnico en una operación secundaria.',
                    $message
                );
                self::assertSame('password_reset_mail_send', $context['operation'] ?? null);
                self::assertSame(25, $context['user_id'] ?? null);
                self::assertSame('password.email', $context['route_name'] ?? null);
                self::assertSame(3, $context['nested']['attempt'] ?? null);
                self::assertArrayNotHasKey('password', $context);
                self::assertArrayNotHasKey('token', $context['nested'] ?? []);
                self::assertArrayNotHasKey('documento', $context);
                self::assertSame(RuntimeException::class, $context['exception_type'] ?? null);
                self::assertArrayHasKey('exception_fingerprint', $context);
                self::assertIsString($serialized);
                self::assertStringNotContainsString('NoDebeRegistrarse', $serialized);
                self::assertStringNotContainsString('Detalle que podría contener', $serialized);

                return true;
            });
    }

    public function test_security_flows_report_secondary_failures_instead_of_silencing_them(): void
    {
        $root = dirname(__DIR__, 2);

        $auth = file_get_contents($root.'/app/Http/Controllers/AuthController.php');
        $passwordReset = file_get_contents($root.'/app/Http/Controllers/PasswordResetController.php');
        $middleware = file_get_contents($root.'/app/Http/Middleware/SwafiAuth.php');
        $recaptcha = file_get_contents($root.'/app/Rules/RecaptchaV3.php');

        self::assertIsString($auth);
        self::assertIsString($passwordReset);
        self::assertIsString($middleware);
        self::assertIsString($recaptcha);

        self::assertStringContainsString("warning(\$exception, 'auth_audit_write'", $auth);
        self::assertStringContainsString("warning(\$exception, 'password_reset_mail_send'", $passwordReset);
        self::assertStringContainsString("warning(\$exception, 'password_reset_audit_write'", $passwordReset);
        self::assertStringContainsString("warning(\$exception, 'session_security_audit_write'", $middleware);
        self::assertStringContainsString("warning(\$exception, 'access_denied_audit_write'", $middleware);
        self::assertStringContainsString("warning(\$exception, 'recaptcha_validation'", $recaptcha);
        self::assertStringNotContainsString("'error' => \$exception->getMessage()", $passwordReset);
    }
}

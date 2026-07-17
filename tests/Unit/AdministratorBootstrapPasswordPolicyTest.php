<?php

namespace Tests\Unit;

use App\Rules\AdministratorBootstrapPasswordPolicy;
use PHPUnit\Framework\TestCase;

class AdministratorBootstrapPasswordPolicyTest extends TestCase
{
    public function test_accepts_a_long_unique_password_with_all_required_character_groups(): void
    {
        self::assertSame([], $this->failuresFor('C0ntr0l-Fiscal#2026!'));
    }

    public function test_rejects_a_password_shorter_than_twelve_characters(): void
    {
        $failures = $this->failuresFor('Segura#1Aa');

        self::assertNotEmpty($failures);
        self::assertStringContainsString('al menos 12 caracteres', implode(' ', $failures));
    }

    public function test_rejects_a_known_or_predictable_administrator_pattern(): void
    {
        $failures = $this->failuresFor('Admin@12345678');

        self::assertNotEmpty($failures);
        self::assertStringContainsString('predecible', implode(' ', $failures));
    }

    public function test_rejects_a_password_that_contains_the_username(): void
    {
        $failures = $this->failuresFor('Mi#admin.seguro2026');

        self::assertNotEmpty($failures);
        self::assertStringContainsString('no debe contener el usuario', implode(' ', $failures));
    }

    public function test_rejects_a_password_that_contains_the_email_local_part(): void
    {
        $rule = new AdministratorBootstrapPasswordPolicy(
            'usuario.distinto',
            'responsable.seguridad@example.test'
        );
        $failures = [];

        $rule->validate(
            'password',
            'Responsable.Seguridad#2026',
            static function (string $message) use (&$failures): void {
                $failures[] = $message;
            }
        );

        self::assertNotEmpty($failures);
        self::assertStringContainsString('parte principal del correo', implode(' ', $failures));
    }

    public function test_rejects_missing_uppercase_lowercase_number_or_special_character(): void
    {
        foreach ([
            'minusculas#2026',
            'MAYUSCULAS#2026',
            'SinNumero#Seguro',
            'SinEspecial2026Aa',
        ] as $password) {
            self::assertNotEmpty(
                $this->failuresFor($password),
                'La contraseña '.$password.' debió rechazarse.'
            );
        }
    }

    private function failuresFor(string $password): array
    {
        $rule = new AdministratorBootstrapPasswordPolicy(
            'admin.seguro',
            'cuenta.inicial@example.test'
        );
        $failures = [];

        $rule->validate(
            'password',
            $password,
            static function (string $message) use (&$failures): void {
                $failures[] = $message;
            }
        );

        return $failures;
    }
}

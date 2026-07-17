<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AdministratorBootstrapPasswordPolicy implements ValidationRule
{
    public function __construct(
        private readonly string $usuario,
        private readonly string $email,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;
        $baseFailed = false;

        (new SwafiPasswordPolicy())->validate(
            $attribute,
            $password,
            function (string $message) use ($fail, &$baseFailed): void {
                $baseFailed = true;
                $fail($message);
            }
        );

        if ($baseFailed) {
            return;
        }

        if (mb_strlen($password) < 12) {
            $fail('La contraseña inicial del Administrador SWAFI debe tener al menos 12 caracteres.');

            return;
        }

        $normalizedPassword = $this->normalize($password);

        if ($this->isCommonOrPredictable($normalizedPassword)) {
            $fail('La contraseña elegida es predecible o corresponde a un valor de uso común. Utiliza una contraseña única.');

            return;
        }

        $normalizedUser = $this->normalize($this->usuario);
        $emailLocalPart = explode('@', mb_strtolower(trim($this->email)), 2)[0] ?? '';
        $normalizedEmailLocalPart = $this->normalize($emailLocalPart);

        if (
            ($normalizedUser !== '' && mb_strlen($normalizedUser) >= 4 && str_contains($normalizedPassword, $normalizedUser)) ||
            ($normalizedEmailLocalPart !== '' && mb_strlen($normalizedEmailLocalPart) >= 4 && str_contains($normalizedPassword, $normalizedEmailLocalPart))
        ) {
            $fail('La contraseña no debe contener el usuario ni la parte principal del correo electrónico.');
        }
    }

    private function isCommonOrPredictable(string $password): bool
    {
        $commonValues = [
            '12345678',
            '123456789',
            'password',
            'password123',
            'contrasena',
            'contrasena123',
            'qwerty123',
        ];

        if (in_array($password, $commonValues, true)) {
            return true;
        }

        return preg_match(
            '/^(admin|administrador|swafi|bimbo)(12345678|123456789|1234|2026)$/u',
            $password
        ) === 1;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return (string) preg_replace('/[^a-z0-9]+/u', '', $value);
    }
}

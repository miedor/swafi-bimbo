<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SwafiPasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (mb_strlen($password) < 8) {
            $fail('La contraseña debe tener al menos 8 caracteres.');
            return;
        }

        if (!preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password)) {
            $fail('La contraseña debe incluir al menos una letra mayúscula.');
            return;
        }

        if (!preg_match('/[a-záéíóúñ]/u', $password)) {
            $fail('La contraseña debe incluir al menos una letra minúscula.');
            return;
        }

        if (!preg_match('/[0-9]/', $password)) {
            $fail('La contraseña debe incluir al menos un número.');
            return;
        }

        if (!preg_match('/[^A-Za-z0-9ÁÉÍÓÚÑáéíóúñ]/u', $password)) {
            $fail('La contraseña debe incluir al menos un carácter especial, por ejemplo: @, #, $, %, &, *, ! o ?.');
            return;
        }
    }
}

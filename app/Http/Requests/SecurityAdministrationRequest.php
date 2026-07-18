<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class SecurityAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $roles = collect($this->session()->get('swafi_roles', []))
            ->filter(fn ($role) => is_scalar($role))
            ->map(fn ($role) => mb_strtolower(trim((string) $role)));

        $permissions = collect($this->session()->get('swafi_permissions', []))
            ->filter(fn ($permission) => is_scalar($permission))
            ->map(fn ($permission) => trim((string) $permission));

        return $roles->contains('administrador swafi')
            || $permissions->contains('seguridad.administrar');
    }

    protected function normalizedString(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value)
            ? trim((string) $value)
            : '';
    }

    protected function normalizedOptionalInteger(string $key): mixed
    {
        if (!$this->filled($key)) {
            return null;
        }

        $value = $this->input($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return $value;
    }

    protected function normalizedIntegerArray(string $key): mixed
    {
        $value = $this->input($key, []);

        if (!is_array($value)) {
            return $value;
        }

        return array_map(static function ($item) {
            if (is_int($item)) {
                return $item;
            }

            if (is_string($item) && preg_match('/^\d+$/', trim($item)) === 1) {
                return (int) trim($item);
            }

            return $item;
        }, $value);
    }
}

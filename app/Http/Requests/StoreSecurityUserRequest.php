<?php

namespace App\Http\Requests;

use App\Rules\SwafiPasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecurityUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $roles = collect($this->session()->get('swafi_roles', []))
            ->map(fn ($role) => mb_strtolower(trim((string) $role)));

        $permissions = collect($this->session()->get('swafi_permissions', []))
            ->map(fn ($permission) => trim((string) $permission));

        return $roles->contains('administrador swafi')
            || $permissions->contains('seguridad.administrar');
    }

    protected function prepareForValidation(): void
    {
        $roleIds = collect($this->input('role_ids', []))
            ->filter(fn ($value) => is_scalar($value) && preg_match('/^\d+$/', (string) $value) === 1)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'id' => $this->filled('id') ? (int) $this->input('id') : null,
            'usuario' => trim((string) $this->input('usuario', '')),
            'name' => trim((string) $this->input('name', '')),
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
            'estatus' => mb_strtolower(trim((string) $this->input('estatus', 'activo'))),
            'role_ids' => $roleIds,
        ]);
    }

    public function rules(): array
    {
        $userId = $this->input('id');

        return [
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'usuario' => [
                'required',
                'string',
                'min:4',
                'max:80',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'usuario')->ignore($userId),
            ],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [
                $userId ? 'nullable' : 'required',
                'string',
                'max:120',
                new SwafiPasswordPolicy(),
            ],
            'estatus' => ['required', Rule::in(['activo', 'inactivo', 'bloqueado'])],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('activo', 1)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.required' => 'El nombre de usuario es obligatorio.',
            'usuario.min' => 'El nombre de usuario debe tener al menos 4 caracteres.',
            'usuario.max' => 'El nombre de usuario no puede superar 80 caracteres.',
            'usuario.regex' => 'El nombre de usuario solo puede contener letras, números, punto, guion y guion bajo.',
            'usuario.unique' => 'El nombre de usuario ya está registrado.',
            'name.required' => 'El nombre completo es obligatorio.',
            'name.min' => 'El nombre completo debe tener al menos 3 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.required' => 'La contraseña inicial es obligatoria.',
            'password.max' => 'La contraseña supera la longitud permitida.',
            'estatus.in' => 'El estatus seleccionado no es válido.',
            'role_ids.required' => 'Debes asignar al menos un rol activo al usuario.',
            'role_ids.min' => 'Debes asignar al menos un rol activo al usuario.',
            'role_ids.*.exists' => 'Uno de los roles seleccionados no existe o está inactivo.',
            'role_ids.*.distinct' => 'No puedes asignar el mismo rol más de una vez.',
        ];
    }

    public function attributes(): array
    {
        return [
            'usuario' => 'usuario',
            'name' => 'nombre completo',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'estatus' => 'estatus',
            'role_ids' => 'roles',
            'role_ids.*' => 'rol',
        ];
    }
}

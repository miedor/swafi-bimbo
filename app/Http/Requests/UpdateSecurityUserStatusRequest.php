<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSecurityUserStatusRequest extends FormRequest
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
        $this->merge([
            'estatus' => mb_strtolower(trim((string) $this->input('estatus', ''))),
            'motivo' => trim((string) $this->input('motivo', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'estatus' => ['required', Rule::in(['activo', 'inactivo'])],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estatus.required' => 'Debes indicar el nuevo estatus del usuario.',
            'estatus.in' => 'La operación de estatus solicitada no es válida.',
            'motivo.max' => 'El motivo no puede superar 500 caracteres.',
        ];
    }
}

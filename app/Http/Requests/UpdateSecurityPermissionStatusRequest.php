<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSecurityPermissionStatusRequest extends SecurityAdministrationRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'estatus' => mb_strtolower($this->normalizedString('estatus')),
            'motivo' => $this->normalizedString('motivo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'estatus' => ['required', Rule::in(['activo', 'inactivo'])],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estatus.required' => 'Debes indicar el nuevo estatus del permiso.',
            'estatus.in' => 'La operación solicitada para el permiso no es válida.',
            'motivo.required' => 'El motivo del cambio de estatus es obligatorio.',
            'motivo.min' => 'El motivo debe contener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede superar 500 caracteres.',
        ];
    }
}

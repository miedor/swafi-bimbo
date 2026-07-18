<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSecurityPermissionRequest extends SecurityAdministrationRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->normalizedOptionalInteger('id'),
            'clave' => mb_strtolower($this->normalizedString('clave')),
            'descripcion' => $this->normalizedString('descripcion'),
        ]);
    }

    public function rules(): array
    {
        $requestedPermissionId = $this->input('id');
        $permissionId = is_int($requestedPermissionId) && $requestedPermissionId > 0
            ? $requestedPermissionId
            : null;

        return [
            'id' => ['nullable', 'integer', 'exists:permissions,id'],
            'clave' => [
                'required',
                'string',
                'min:5',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/',
                Rule::unique('permissions', 'clave')->ignore($permissionId),
            ],
            'descripcion' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'clave.required' => 'La clave técnica del permiso es obligatoria.',
            'clave.min' => 'La clave técnica debe contener al menos 5 caracteres.',
            'clave.max' => 'La clave técnica no puede superar 80 caracteres.',
            'clave.regex' => 'La clave debe utilizar el formato modulo.accion, en minúsculas y sin espacios.',
            'clave.unique' => 'Ya existe un permiso con esa clave técnica.',
            'descripcion.required' => 'La descripción del permiso es obligatoria.',
            'descripcion.min' => 'La descripción del permiso debe contener al menos 10 caracteres.',
            'descripcion.max' => 'La descripción del permiso no puede superar 255 caracteres.',
        ];
    }
}

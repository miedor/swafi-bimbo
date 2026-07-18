<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSecurityRoleRequest extends SecurityAdministrationRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->normalizedOptionalInteger('id'),
            'nombre' => $this->normalizedString('nombre'),
            'descripcion' => $this->normalizedString('descripcion'),
            'activo' => $this->normalizedString('activo', '1'),
            'permission_ids' => $this->normalizedIntegerArray('permission_ids'),
        ]);
    }

    public function rules(): array
    {
        $requestedRoleId = $this->input('id');
        $roleId = is_int($requestedRoleId) && $requestedRoleId > 0 ? $requestedRoleId : null;
        $isAdministratorRole = $roleId !== null
            && (string) \Illuminate\Support\Facades\DB::table('roles')
                ->where('id', $roleId)
                ->value('nombre') === 'Administrador SWAFI';

        $permissionRule = $this->input('activo') === '1' && !$isAdministratorRole
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        return [
            'id' => ['nullable', 'integer', 'exists:roles,id'],
            'nombre' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[\pL\pN][\pL\pN ._\/-]*$/u',
                Rule::unique('roles', 'nombre')->ignore($roleId),
            ],
            'descripcion' => ['required', 'string', 'min:10', 'max:255'],
            'activo' => ['required', Rule::in(['1', '0'])],
            'permission_ids' => $permissionRule,
            'permission_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where(
                    fn ($query) => $query->where('activo', 1)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del rol es obligatorio.',
            'nombre.min' => 'El nombre del rol debe contener al menos 3 caracteres.',
            'nombre.max' => 'El nombre del rol no puede superar 50 caracteres.',
            'nombre.regex' => 'El nombre del rol contiene caracteres no permitidos.',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'descripcion.required' => 'La descripción del rol es obligatoria.',
            'descripcion.min' => 'La descripción del rol debe contener al menos 10 caracteres.',
            'descripcion.max' => 'La descripción del rol no puede superar 255 caracteres.',
            'activo.in' => 'El estatus seleccionado para el rol no es válido.',
            'permission_ids.required' => 'Un rol activo debe tener al menos un permiso activo.',
            'permission_ids.min' => 'Un rol activo debe tener al menos un permiso activo.',
            'permission_ids.*.exists' => 'Uno de los permisos seleccionados no existe o está inactivo.',
            'permission_ids.*.distinct' => 'No puedes asignar el mismo permiso más de una vez.',
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;

class CatalogValidationService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $catalog, ?int $recordId, array $input = []): array
    {
        $common = [
            'catalogo' => ['required', Rule::in(array_keys(CatalogManagementService::CATALOGS))],
            'id' => ['nullable', 'integer', 'min:1'],
            'estatus' => ['required', Rule::in(['activo', 'inactivo'])],
        ];

        $catalogRules = match ($catalog) {
            'proveedores' => [
                'rfc' => [
                    'required',
                    'string',
                    'min:12',
                    'max:13',
                    'regex:/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u',
                    Rule::unique('proveedores', 'rfc')->ignore($recordId),
                ],
                'nombre' => ['required', 'string', 'min:3', 'max:150'],
                'correo' => ['nullable', 'email:rfc', 'max:120'],
                'telefono' => ['nullable', 'string', 'max:30'],
            ],

            'plantas' => [
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('plantas', 'clave')->ignore($recordId),
                ],
                'nombre' => ['required', 'string', 'min:3', 'max:150'],
                'direccion' => ['required', 'string', 'min:5', 'max:255'],
                'estado' => ['nullable', 'string', 'max:100'],
                'pais' => ['required', 'string', 'min:2', 'max:80'],
            ],

            'centros_costo' => [
                'planta_id' => [
                    'required',
                    'integer',
                    Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('centros_costo', 'clave')->ignore($recordId),
                ],
                'descripcion' => ['required', 'string', 'min:3', 'max:150'],
            ],

            'categorias_activo' => [
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('categorias_activo', 'clave')->ignore($recordId),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'min:3',
                    'max:120',
                    Rule::unique('categorias_activo', 'nombre')->ignore($recordId),
                ],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],

            'tipos_activo' => [
                'categoria_activo_id' => [
                    'required',
                    'integer',
                    Rule::exists('categorias_activo', 'id')
                        ->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('tipos_activo', 'clave')->ignore($recordId),
                ],
                'descripcion' => [
                    'required',
                    'string',
                    'min:3',
                    'max:120',
                    Rule::unique('tipos_activo', 'descripcion')->ignore($recordId),
                ],
                'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:600'],
            ],

            'estatus_documentales', 'estatus_operativos' => [
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:20',
                    'regex:/^[a-z][a-z0-9_]*$/',
                    Rule::unique(
                        $catalog === 'estatus_documentales'
                            ? 'estatus_documentales'
                            : 'estatus_operativos',
                        'clave'
                    )->ignore($recordId),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'min:3',
                    'max:80',
                    Rule::unique(
                        $catalog === 'estatus_documentales'
                            ? 'estatus_documentales'
                            : 'estatus_operativos',
                        'nombre'
                    )->ignore($recordId),
                ],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'orden' => ['required', 'integer', 'min:1', 'max:999'],
            ],

            'areas' => [
                'planta_id' => [
                    'required',
                    'integer',
                    Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('areas', 'clave')
                        ->where(fn ($query) => $query->where('planta_id', $input['planta_id'] ?? null))
                        ->ignore($recordId),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'min:2',
                    'max:120',
                    Rule::unique('areas', 'nombre')
                        ->where(fn ($query) => $query->where('planta_id', $input['planta_id'] ?? null))
                        ->ignore($recordId),
                ],
            ],

            'ubicaciones' => [
                'planta_id' => [
                    'required',
                    'integer',
                    Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'area_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('areas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'codigo_interno' => [
                    'required',
                    'string',
                    'max:60',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('ubicaciones', 'codigo_interno')->ignore($recordId),
                ],
                'edificio' => ['nullable', 'string', 'max:100'],
                'piso' => ['nullable', 'string', 'max:50'],
                'pasillo' => ['nullable', 'string', 'max:50'],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],

            'responsables' => [
                'nombre' => ['required', 'string', 'min:3', 'max:120'],
                'correo' => ['nullable', 'email:rfc', 'max:120'],
                'telefono' => ['nullable', 'string', 'max:30'],
            ],

            default => [],
        };

        return array_merge($common, $catalogRules);
    }

    /**
     * @return array<string, string>
     */
    public function messages(string $catalog): array
    {
        return [
            'catalogo.in' => 'El catálogo seleccionado no es válido.',
            'id.integer' => 'El identificador del registro no es válido.',
            'estatus.in' => 'El estatus seleccionado no es válido.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe contener texto.',
            'integer' => 'El campo :attribute debe contener un número entero.',
            'min' => 'El campo :attribute no cumple el valor mínimo permitido.',
            'max' => 'El campo :attribute supera el límite permitido.',
            'unique' => 'El valor capturado en :attribute ya está registrado.',
            'exists' => 'La opción seleccionada en :attribute no existe o está inactiva.',
            'email' => 'El correo electrónico no tiene un formato válido.',
            'regex' => 'El formato capturado en :attribute no es válido.',
            'rfc.regex' => 'El RFC debe contener 12 o 13 caracteres con estructura válida.',
            'clave.regex' => in_array($catalog, ['estatus_documentales', 'estatus_operativos'], true)
                ? 'La clave técnica debe iniciar con una letra minúscula y solo puede contener letras minúsculas, números y guion bajo.'
                : 'La clave solo puede contener letras mayúsculas, números, punto, guion y guion bajo.',
            'codigo_interno.regex' => 'El código interno solo puede contener letras mayúsculas, números, punto, guion y guion bajo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rfc' => 'RFC',
            'nombre' => 'nombre',
            'correo' => 'correo electrónico',
            'telefono' => 'teléfono',
            'clave' => 'clave',
            'direccion' => 'dirección',
            'estado' => 'estado',
            'pais' => 'país',
            'descripcion' => 'descripción',
            'vida_util_meses' => 'vida útil en meses',
            'orden' => 'orden de presentación',
            'categoria_activo_id' => 'categoría de activo',
            'planta_id' => 'planta',
            'area_id' => 'área',
            'codigo_interno' => 'código interno',
            'edificio' => 'edificio',
            'piso' => 'piso',
            'pasillo' => 'pasillo',
            'estatus' => 'estatus',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function makeValidator(array $data, string $catalog, ?int $recordId): LaravelValidator
    {
        $data['catalogo'] = $catalog;
        $data['id'] = $recordId;

        return Validator::make(
            $data,
            $this->rules($catalog, $recordId, $data),
            $this->messages($catalog),
            $this->attributes()
        );
    }
}

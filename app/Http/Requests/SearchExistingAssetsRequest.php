<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchExistingAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $query = strtoupper(trim((string) $this->input('q')));

        $this->merge([
            'q' => $query !== '' ? $query : null,
            'proveedor_id' => $this->nullableInteger($this->input('proveedor_id')),
            'planta_id' => $this->nullableInteger($this->input('planta_id')),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 8),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => [
                'nullable',
                'required_without_all:proveedor_id,planta_id',
                'string',
                'min:2',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
            ],
            'proveedor_id' => [
                'nullable',
                'integer',
                Rule::exists('proveedores', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'planta_id' => [
                'nullable',
                'integer',
                Rule::exists('plantas', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in([5, 8, 10, 15, 20])],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required_without_all' => 'Captura al menos dos caracteres del número de activo o selecciona un proveedor o una planta.',
            'q.min' => 'Captura al menos dos caracteres para buscar por número de activo.',
            'q.max' => 'El criterio de búsqueda no debe superar 30 caracteres.',
            'q.regex' => 'El criterio solo puede contener letras, números, punto, guion y guion bajo.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe o se encuentra inactivo.',
            'planta_id.exists' => 'La planta seleccionada no existe o se encuentra inactiva.',
            'page.min' => 'La página solicitada no es válida.',
            'per_page.in' => 'La cantidad de resultados por página no es válida.',
        ];
    }

    private function nullableInteger(mixed $value): mixed
    {
        return $value === null || $value === '' ? null : $value;
    }
}

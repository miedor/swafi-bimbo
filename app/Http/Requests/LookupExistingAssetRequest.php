<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LookupExistingAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_activo' => strtoupper(trim((string) $this->input('numero_activo'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'numero_activo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9\-]*$/',
                Rule::exists('activos', 'numero_activo')
                    ->where(fn ($query) => $query->where('activo', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'Captura el número del activo que deseas localizar.',
            'numero_activo.regex' => 'El número de activo solo puede contener letras mayúsculas, números y guiones.',
            'numero_activo.exists' => 'No se encontró un activo vigente con ese número.',
        ];
    }
}

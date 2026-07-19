<?php

namespace App\Http\Requests;

use App\Services\SwafiAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportValorActivoFichaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authorization = app(SwafiAuthorizationService::class);

        return $authorization->canCurrentUser('valores.administrar')
            || $authorization->canCurrentUser('reportes.valores');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_activo' => mb_strtoupper(
                trim((string) $this->route('numeroActivo')),
                'UTF-8'
            ),
            'formato' => mb_strtolower(
                trim((string) $this->route('formato')),
                'UTF-8'
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'numero_activo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::exists('valores_activo', 'numero_activo')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'formato' => [
                'required',
                Rule::in(['xlsx', 'pdf']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'El número de activo es obligatorio para generar la ficha.',
            'numero_activo.max' => 'El número de activo excede la longitud permitida.',
            'numero_activo.regex' => 'El número de activo contiene caracteres no permitidos.',
            'numero_activo.exists' => 'El activo no cuenta con valores fiscales y financieros vigentes.',
            'formato.required' => 'Selecciona un formato de exportación.',
            'formato.in' => 'La ficha solo puede exportarse a Excel o PDF.',
        ];
    }
}

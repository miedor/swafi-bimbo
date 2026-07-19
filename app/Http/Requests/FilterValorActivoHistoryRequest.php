<?php

namespace App\Http\Requests;

use App\Services\SwafiAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterValorActivoHistoryRequest extends FormRequest
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
            'accion' => mb_strtoupper(trim((string) $this->input('accion')), 'UTF-8'),
        ]);
    }

    public function rules(): array
    {
        return [
            'accion' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_]+$/',
            ],
            'usuario_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:fecha_desde',
            ],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'swafi_focus' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.max' => 'La acción seleccionada no es válida.',
            'accion.regex' => 'La acción seleccionada contiene un formato no permitido.',
            'usuario_id.integer' => 'El usuario seleccionado no es válido.',
            'usuario_id.exists' => 'El usuario seleccionado ya no existe.',
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato año-mes-día.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato año-mes-día.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'per_page.in' => 'Selecciona 10, 25 o 50 registros por página.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateObservationDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nueva_fecha_compromiso' => trim(
                (string) $this->input('nueva_fecha_compromiso')
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'nueva_fecha_compromiso' => [
                'required',
                'date_format:Y-m-d',
                'after:today',
            ],
            'observacion_contexto' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'nueva_fecha_compromiso.required' => 'Debes indicar la nueva fecha compromiso.',
            'nueva_fecha_compromiso.date_format' => 'La nueva fecha compromiso debe utilizar el formato año-mes-día.',
            'nueva_fecha_compromiso.after' => 'La nueva fecha compromiso debe ser posterior al día de hoy.',
            'observacion_contexto.integer' => 'La referencia de la observación no es válida.',
        ];
    }
}

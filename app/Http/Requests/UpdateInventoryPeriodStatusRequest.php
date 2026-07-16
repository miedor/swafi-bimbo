<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryPeriodStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_estado' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_estado.required' => 'Captura el motivo del bloqueo o desbloqueo del periodo.',
            'motivo_estado.min' => 'El motivo debe contener al menos 10 caracteres para conservar trazabilidad.',
            'motivo_estado.max' => 'El motivo no debe superar los 1000 caracteres.',
        ];
    }
}

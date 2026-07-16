<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comentario_resolucion' => [
                Rule::requiredIf(fn () => $this->routeIs('ubicacion.traslados.rechazar')),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'comentario_resolucion.required' => 'El motivo de rechazo es obligatorio.',
            'comentario_resolucion.min' => 'El comentario debe contener al menos 10 caracteres.',
            'comentario_resolucion.max' => 'El comentario no debe superar los 1000 caracteres.',
        ];
    }
}

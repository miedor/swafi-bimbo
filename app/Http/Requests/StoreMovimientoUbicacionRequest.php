<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovimientoUbicacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_activo' => [
                'required',
                'string',
                'max:30',
                'exists:activos,numero_activo',
            ],

            'ubicacion_destino_id' => [
                'required',
                'integer',
                'exists:ubicaciones,id',
            ],

            'responsable_id' => [
                'nullable',
                'integer',
                'exists:responsables,id',
            ],

            'fecha_movimiento' => [
                'required',
                'date',
            ],

            'motivo' => [
                'nullable',
                'string',
                'max:120',
            ],

            'evidencia' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'Debes seleccionar un activo.',
            'numero_activo.exists' => 'El activo seleccionado no existe en SWAFI.',

            'ubicacion_destino_id.required' => 'Debes seleccionar la nueva ubicación física.',
            'ubicacion_destino_id.exists' => 'La ubicación seleccionada no existe.',

            'responsable_id.exists' => 'El responsable seleccionado no existe.',

            'fecha_movimiento.required' => 'La fecha del movimiento es obligatoria.',
            'fecha_movimiento.date' => 'La fecha del movimiento no tiene un formato válido.',

            'motivo.max' => 'El motivo no debe superar los 120 caracteres.',
            'evidencia.max' => 'La evidencia u observación no debe superar los 2000 caracteres.',
        ];
    }
}

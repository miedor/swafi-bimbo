<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventarioActivoRequest extends FormRequest
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

            'fecha_inventario' => [
                'required',
                'date',
            ],

            'estatus_localizacion' => [
                'required',
                'string',
                Rule::in([
                    'localizado',
                    'no_encontrado',
                    'diferencia',
                    'pendiente',
                ]),
            ],

            'ubicacion_verificada_id' => [
                'nullable',
                'integer',
                'exists:ubicaciones,id',
            ],

            'actualizar_ubicacion' => [
                'nullable',
                'boolean',
            ],

            'observaciones' => [
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

            'fecha_inventario.required' => 'La fecha de inventario es obligatoria.',
            'fecha_inventario.date' => 'La fecha de inventario no tiene un formato válido.',

            'estatus_localizacion.required' => 'El estatus de localización es obligatorio.',
            'estatus_localizacion.in' => 'El estatus de localización seleccionado no es válido.',

            'ubicacion_verificada_id.exists' => 'La ubicación verificada seleccionada no existe.',

            'observaciones.max' => 'Las observaciones no deben superar los 2000 caracteres.',
        ];
    }
}

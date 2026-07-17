<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                Rule::exists('activos', 'numero_activo')->where(fn ($query) => $query->where('activo', true)),
            ],

            'ubicacion_destino_id' => [
                'required',
                'integer',
                Rule::exists('ubicaciones', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],

            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('responsables', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],

            /*
             * Este campo solo es obligatorio cuando la ubicación pertenece a otra
             * planta. La validación de esa regla de negocio y del rol Usuario
             * Captura se ejecuta dentro de TransferWorkflowService, después de
             * bloquear el activo y resolver la planta destino.
             */
            'aprobador_asignado_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],

            'fecha_movimiento' => [
                'required',
                'date',
                'before_or_equal:now',
            ],

            'motivo' => [
                'required',
                'string',
                'min:10',
                'max:500',
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
            'numero_activo.exists' => 'El activo seleccionado no existe o se encuentra inactivo en SWAFI.',

            'ubicacion_destino_id.required' => 'Debes seleccionar la nueva ubicación física.',
            'ubicacion_destino_id.exists' => 'La ubicación seleccionada no existe o se encuentra inactiva.',

            'responsable_id.exists' => 'El responsable seleccionado no existe o se encuentra inactivo.',

            'aprobador_asignado_id.integer' => 'El Usuario Captura seleccionado no es válido.',
            'aprobador_asignado_id.exists' => 'El Usuario Captura seleccionado no existe o se encuentra inactivo.',

            'fecha_movimiento.required' => 'La fecha del movimiento es obligatoria.',
            'fecha_movimiento.date' => 'La fecha del movimiento no tiene un formato válido.',
            'fecha_movimiento.before_or_equal' => 'La fecha del movimiento no puede ser posterior al momento actual.',

            'motivo.required' => 'El motivo del movimiento o traslado es obligatorio.',
            'motivo.min' => 'El motivo debe contener al menos 10 caracteres para conservar trazabilidad.',
            'motivo.max' => 'El motivo no debe superar los 500 caracteres.',
            'evidencia.max' => 'La evidencia u observación no debe superar los 2000 caracteres.',
        ];
    }
}

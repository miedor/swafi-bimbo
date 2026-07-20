<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpedienteObservacionRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private const OBSERVATION_TYPES = [
        'falta_pdf',
        'falta_xml',
        'falta_valores',
        'falta_ubicacion',
        'ubicacion_incorrecta',
        'datos_inconsistentes',
        'documento_incorrecto',
        'otro',
    ];

    /**
     * @var array<int, string>
     */
    private const PRIORITIES = [
        'baja',
        'media',
        'alta',
        'critica',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descripcion' => trim((string) $this->input('descripcion')),
            'fecha_compromiso' => trim((string) $this->input('fecha_compromiso')),
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo_observacion' => ['required', Rule::in(self::OBSERVATION_TYPES)],
            'prioridad' => ['required', Rule::in(self::PRIORITIES)],
            'rol_destino' => [
                'required',
                Rule::in(['Usuario Captura', 'Usuario Planta / Inventarios']),
            ],
            'asignado_a' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'fecha_compromiso' => [
                'required',
                'date_format:Y-m-d',
                'after:today',
            ],
            'descripcion' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_observacion.required' => 'Debes seleccionar el tipo de observación.',
            'tipo_observacion.in' => 'El tipo de observación seleccionado no es válido.',
            'prioridad.required' => 'Debes seleccionar la prioridad.',
            'prioridad.in' => 'La prioridad seleccionada no es válida.',
            'rol_destino.required' => 'Debes seleccionar el rol responsable de atender la observación.',
            'rol_destino.in' => 'El rol responsable seleccionado no es válido.',
            'asignado_a.required' => 'Debes seleccionar el usuario que atenderá la observación.',
            'asignado_a.integer' => 'El usuario seleccionado no es válido.',
            'asignado_a.exists' => 'El usuario seleccionado no existe o se encuentra inactivo.',
            'fecha_compromiso.required' => 'Debes indicar la fecha compromiso para atender la observación.',
            'fecha_compromiso.date_format' => 'La fecha compromiso debe utilizar el formato año-mes-día.',
            'fecha_compromiso.after' => 'La fecha compromiso debe ser posterior al día de hoy.',
            'descripcion.required' => 'Debes capturar la descripción de la observación.',
            'descripcion.min' => 'La observación debe tener al menos 5 caracteres.',
            'descripcion.max' => 'La observación no debe superar 2000 caracteres.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Services\SwafiAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ConfirmInitialAssetLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = (int) (Auth::id() ?: $this->session()->get('swafi_user_id'));

        if ($userId <= 0) {
            return false;
        }

        $context = app(SwafiAuthorizationService::class)->contextForUser($userId);

        return $context['is_admin'] === true
            || in_array('ubicaciones.administrar', $context['permissions'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => trim((string) $this->input('motivo', '')),
            'evidencia' => $this->filled('evidencia')
                ? trim((string) $this->input('evidencia'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'ubicacion_id' => [
                'required',
                'integer',
                Rule::exists('ubicaciones', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('responsables', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'fecha_asignacion' => [
                'required',
                'date',
                'before_or_equal:today',
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
            'ubicacion_id.required' => 'Debes seleccionar la ubicación inicial del activo.',
            'ubicacion_id.integer' => 'La ubicación seleccionada no tiene un identificador válido.',
            'ubicacion_id.exists' => 'La ubicación seleccionada no existe o se encuentra inactiva.',
            'responsable_id.integer' => 'El responsable seleccionado no tiene un identificador válido.',
            'responsable_id.exists' => 'El responsable seleccionado no existe o se encuentra inactivo.',
            'fecha_asignacion.required' => 'La fecha de asignación inicial es obligatoria.',
            'fecha_asignacion.date' => 'La fecha de asignación inicial no tiene un formato válido.',
            'fecha_asignacion.before_or_equal' => 'La fecha de asignación inicial no puede ser posterior al día actual.',
            'motivo.required' => 'Debes indicar el motivo o fundamento de la ubicación inicial.',
            'motivo.min' => 'El motivo debe contener al menos 10 caracteres para conservar trazabilidad.',
            'motivo.max' => 'El motivo no debe superar los 500 caracteres.',
            'evidencia.max' => 'La referencia de evidencia no debe superar los 2000 caracteres.',
        ];
    }
}

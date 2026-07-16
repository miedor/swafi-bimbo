<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                Rule::exists('activos', 'numero_activo')->where(fn ($query) => $query->where('activo', true)),
            ],

            'fecha_inventario' => [
                'required',
                'date',
                'before_or_equal:today',
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
                Rule::exists('ubicaciones', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
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

            'notificar_a' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],

            'evidencias' => [
                'nullable',
                'array',
                'max:5',
            ],

            'evidencias.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:6144',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $estatus = (string) $this->input('estatus_localizacion');
            $ubicacionId = $this->input('ubicacion_verificada_id');
            $observaciones = trim((string) $this->input('observaciones'));
            $notificarA = $this->input('notificar_a');
            $evidencias = $this->file('evidencias', []);
            $actualizarUbicacion = $this->boolean('actualizar_ubicacion');

            if (in_array($estatus, ['localizado', 'diferencia'], true) && empty($ubicacionId)) {
                $validator->errors()->add(
                    'ubicacion_verificada_id',
                    'Debes indicar la ubicación verificada cuando el activo fue localizado o existe una diferencia de ubicación.'
                );
            }

            if ($actualizarUbicacion && empty($ubicacionId)) {
                $validator->errors()->add(
                    'actualizar_ubicacion',
                    'Para actualizar la ubicación actual debes seleccionar una ubicación verificada.'
                );
            }

            if (in_array($estatus, ['no_encontrado', 'diferencia', 'pendiente'], true)) {
                if (mb_strlen($observaciones) < 10) {
                    $validator->errors()->add(
                        'observaciones',
                        'Cuando existe una discrepancia o pendiente, captura una explicación de al menos 10 caracteres.'
                    );
                }

                if (empty($notificarA)) {
                    $validator->errors()->add(
                        'notificar_a',
                        'Selecciona a la persona de Consulta / Auditoría que recibirá la notificación de la discrepancia.'
                    );
                }
            }

            if (in_array($estatus, ['no_encontrado', 'diferencia'], true) && count($evidencias) === 0) {
                $validator->errors()->add(
                    'evidencias',
                    'Para un activo no encontrado o con diferencia de ubicación debes adjuntar al menos una fotografía o documento de evidencia.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'Debes seleccionar un activo.',
            'numero_activo.exists' => 'El activo seleccionado no existe en SWAFI.',

            'fecha_inventario.required' => 'La fecha de inventario es obligatoria.',
            'fecha_inventario.date' => 'La fecha de inventario no tiene un formato válido.',
            'fecha_inventario.before_or_equal' => 'La fecha de inventario no puede ser posterior a la fecha actual.',

            'estatus_localizacion.required' => 'El estatus de localización es obligatorio.',
            'estatus_localizacion.in' => 'El estatus de localización seleccionado no es válido.',

            'ubicacion_verificada_id.exists' => 'La ubicación verificada seleccionada no existe.',

            'observaciones.max' => 'Las observaciones no deben superar los 2000 caracteres.',

            'notificar_a.exists' => 'La persona seleccionada para notificación no existe o no se encuentra activa.',

            'evidencias.array' => 'La evidencia seleccionada no tiene un formato válido.',
            'evidencias.max' => 'Puedes adjuntar como máximo 5 evidencias por toma de inventario.',
            'evidencias.*.file' => 'Cada evidencia debe ser un archivo válido.',
            'evidencias.*.mimes' => 'Las evidencias solo pueden ser JPG, JPEG, PNG, WEBP o PDF.',
            'evidencias.*.max' => 'Cada evidencia no debe superar los 6 MB.',
        ];
    }
}

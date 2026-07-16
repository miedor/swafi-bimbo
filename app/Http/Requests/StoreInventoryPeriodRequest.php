<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'planta_id' => [
                'required',
                'integer',
                Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'nombre' => ['required', 'string', 'min:5', 'max:120'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                !$this->filled('planta_id')
                || !$this->filled('fecha_inicio')
                || !$this->filled('fecha_fin')
                || $validator->errors()->any()
            ) {
                return;
            }

            $overlaps = \App\Models\PeriodoInventario::query()
                ->where('planta_id', (int) $this->input('planta_id'))
                ->whereDate('fecha_inicio', '<=', $this->input('fecha_fin'))
                ->whereDate('fecha_fin', '>=', $this->input('fecha_inicio'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'fecha_inicio',
                    'Ya existe un periodo de inventario que se cruza con las fechas seleccionadas para esta planta.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'planta_id.required' => 'Selecciona la planta del periodo de inventario.',
            'planta_id.exists' => 'La planta seleccionada no existe o se encuentra inactiva.',
            'nombre.required' => 'Captura un nombre identificable para el periodo.',
            'nombre.min' => 'El nombre del periodo debe contener al menos 5 caracteres.',
            'nombre.max' => 'El nombre del periodo no debe superar los 120 caracteres.',
            'fecha_inicio.required' => 'La fecha inicial es obligatoria.',
            'fecha_inicio.date' => 'La fecha inicial no tiene un formato válido.',
            'fecha_fin.required' => 'La fecha final es obligatoria.',
            'fecha_fin.date' => 'La fecha final no tiene un formato válido.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'observaciones.max' => 'Las observaciones no deben superar los 1000 caracteres.',
        ];
    }
}

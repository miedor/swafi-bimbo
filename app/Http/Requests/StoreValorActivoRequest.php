<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreValorActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valor_id' => ['nullable', 'integer', 'exists:valores_activo,id'],

            'numero_activo' => [
                'required',
                'string',
                'max:30',
                'exists:activos,numero_activo',
            ],

            'valor_fiscal' => ['required', 'numeric', 'min:0'],
            'valor_financiero' => ['required', 'numeric', 'min:0'],
            'depreciacion_acumulada' => ['required', 'numeric', 'min:0'],
            'valor_en_libros' => ['nullable', 'numeric', 'min:0'],
            'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:600'],

            'estatus_contable' => [
                'required',
                'string',
                Rule::in(['vigente', 'en_revision', 'baja']),
            ],

            'fecha_corte' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $estatus = (string) $this->input('estatus_contable');
            $valorFiscal = (float) $this->input('valor_fiscal', 0);
            $valorFinanciero = (float) $this->input('valor_financiero', 0);

            if (in_array($estatus, ['vigente', 'en_revision'], true)) {
                if ($valorFiscal <= 0 || $valorFinanciero <= 0) {
                    $validator->errors()->add(
                        'valor_fiscal',
                        'Un activo con estatus contable vigente o en revisión debe tener valor fiscal y valor financiero mayores a cero. Solo el estatus baja permite valores en cero.'
                    );
                }
            }

            $valorEnLibros = $this->input('valor_en_libros');

            if ($valorEnLibros !== null && $valorEnLibros !== '') {
                $valorEnLibros = (float) $valorEnLibros;

                if ($estatus !== 'baja' && $valorEnLibros < 0) {
                    $validator->errors()->add(
                        'valor_en_libros',
                        'El valor en libros no puede ser negativo.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'El número de activo es obligatorio.',
            'numero_activo.exists' => 'El activo seleccionado no existe en la base de datos.',

            'valor_fiscal.required' => 'El valor fiscal es obligatorio.',
            'valor_fiscal.numeric' => 'El valor fiscal debe ser numérico.',
            'valor_fiscal.min' => 'El valor fiscal no puede ser negativo.',

            'valor_financiero.required' => 'El valor financiero es obligatorio.',
            'valor_financiero.numeric' => 'El valor financiero debe ser numérico.',
            'valor_financiero.min' => 'El valor financiero no puede ser negativo.',

            'depreciacion_acumulada.required' => 'La depreciación acumulada es obligatoria.',
            'depreciacion_acumulada.numeric' => 'La depreciación acumulada debe ser numérica.',
            'depreciacion_acumulada.min' => 'La depreciación acumulada no puede ser negativa.',

            'valor_en_libros.numeric' => 'El valor en libros debe ser numérico.',
            'valor_en_libros.min' => 'El valor en libros no puede ser negativo.',

            'vida_util_meses.integer' => 'La vida útil debe capturarse en meses.',
            'vida_util_meses.min' => 'La vida útil debe ser mayor a cero.',
            'vida_util_meses.max' => 'La vida útil capturada excede el rango permitido.',

            'estatus_contable.required' => 'El estatus contable es obligatorio.',
            'estatus_contable.in' => 'El estatus contable seleccionado no es válido.',

            'fecha_corte.required' => 'La fecha de corte es obligatoria.',
            'fecha_corte.date' => 'La fecha de corte no tiene un formato válido.',
        ];
    }
}

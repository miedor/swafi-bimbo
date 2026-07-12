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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_activo' => mb_strtoupper(trim((string) $this->input('numero_activo')), 'UTF-8'),
            'moneda' => mb_strtoupper(trim((string) $this->input('moneda', 'MXN')), 'UTF-8'),
            'origen_tipo_cambio' => trim((string) $this->input('origen_tipo_cambio')),
            'motivo_cambio' => trim((string) $this->input('motivo_cambio')),
        ]);
    }

    public function rules(): array
    {
        return [
            'valor_id' => ['nullable', 'integer', 'exists:valores_activo,id'],
            'numero_activo' => ['required', 'string', 'max:30', 'exists:activos,numero_activo'],
            'valor_fiscal' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'valor_financiero' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'moneda' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'tipo_cambio' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.999999'],
            'fecha_tipo_cambio' => ['nullable', 'date'],
            'origen_tipo_cambio' => ['nullable', 'string', 'max:120'],
            'depreciacion_acumulada' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'valor_en_libros' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'estatus_contable' => ['required', 'string', Rule::in(['vigente', 'en_revision', 'baja'])],
            'motivo_cambio' => ['nullable', 'string', 'max:1000'],
            'fecha_corte' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $estatus = (string) $this->input('estatus_contable');
            $valorFiscal = (float) $this->input('valor_fiscal', 0);
            $valorFinanciero = (float) $this->input('valor_financiero', 0);
            $depreciacion = (float) $this->input('depreciacion_acumulada', 0);
            $valorEnLibros = $this->filled('valor_en_libros')
                ? (float) $this->input('valor_en_libros')
                : max($valorFiscal - $depreciacion, 0);
            $moneda = (string) $this->input('moneda', 'MXN');
            $tipoCambio = $this->input('tipo_cambio');

            if (in_array($estatus, ['vigente', 'en_revision'], true)) {
                if ($valorFiscal <= 0 || $valorFinanciero <= 0) {
                    $validator->errors()->add(
                        'valor_fiscal',
                        'Un activo vigente o en revisión debe tener valor fiscal y valor financiero mayores a cero. Solo Baja permite valores en cero.'
                    );
                }
            }

            if ($estatus !== 'baja' && $depreciacion > $valorFiscal) {
                $validator->errors()->add(
                    'depreciacion_acumulada',
                    'La depreciación acumulada no puede ser mayor que el valor fiscal mientras el activo no esté dado de baja.'
                );
            }

            if ($estatus !== 'baja' && $valorEnLibros > $valorFiscal) {
                $validator->errors()->add(
                    'valor_en_libros',
                    'El valor en libros no puede ser mayor que el valor fiscal.'
                );
            }

            if ($moneda !== 'MXN') {
                if ($tipoCambio === null || (float) $tipoCambio <= 0) {
                    $validator->errors()->add(
                        'tipo_cambio',
                        'La moneda extranjera requiere un tipo de cambio mayor a cero.'
                    );
                }

                if (!$this->filled('fecha_tipo_cambio')) {
                    $validator->errors()->add(
                        'fecha_tipo_cambio',
                        'Captura la fecha utilizada para el tipo de cambio.'
                    );
                }

                if (!$this->filled('origen_tipo_cambio')) {
                    $validator->errors()->add(
                        'origen_tipo_cambio',
                        'Captura el origen o referencia del tipo de cambio.'
                    );
                }
            }

            if ($moneda === 'MXN' && $tipoCambio !== null && abs((float) $tipoCambio - 1.0) > 0.000001) {
                $validator->errors()->add(
                    'tipo_cambio',
                    'Para moneda MXN el tipo de cambio debe ser 1 o quedar vacío.'
                );
            }

            if ($this->filled('valor_id') && !$this->filled('motivo_cambio')) {
                $validator->errors()->add(
                    'motivo_cambio',
                    'Toda edición de valores debe incluir el motivo del cambio para mantener trazabilidad.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'El número de activo es obligatorio.',
            'numero_activo.exists' => 'El activo seleccionado no existe en SWAFI.',
            'valor_fiscal.required' => 'El valor fiscal es obligatorio.',
            'valor_fiscal.numeric' => 'El valor fiscal debe ser numérico.',
            'valor_financiero.required' => 'El valor financiero es obligatorio.',
            'valor_financiero.numeric' => 'El valor financiero debe ser numérico.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.size' => 'La moneda debe capturarse con tres letras, por ejemplo MXN o USD.',
            'moneda.regex' => 'La moneda solo puede contener tres letras mayúsculas.',
            'tipo_cambio.numeric' => 'El tipo de cambio debe ser numérico.',
            'tipo_cambio.gt' => 'El tipo de cambio debe ser mayor a cero.',
            'fecha_tipo_cambio.date' => 'La fecha de tipo de cambio no es válida.',
            'origen_tipo_cambio.max' => 'El origen del tipo de cambio no debe superar 120 caracteres.',
            'depreciacion_acumulada.required' => 'La depreciación acumulada es obligatoria.',
            'depreciacion_acumulada.numeric' => 'La depreciación acumulada debe ser numérica.',
            'valor_en_libros.numeric' => 'El valor en libros debe ser numérico.',
            'vida_util_meses.integer' => 'La vida útil debe capturarse en meses enteros.',
            'vida_util_meses.min' => 'La vida útil debe ser mayor a cero.',
            'vida_util_meses.max' => 'La vida útil excede el rango permitido.',
            'estatus_contable.required' => 'El estatus contable es obligatorio.',
            'estatus_contable.in' => 'El estatus contable seleccionado no es válido.',
            'motivo_cambio.max' => 'El motivo del cambio no debe superar 1000 caracteres.',
            'fecha_corte.required' => 'La fecha de corte es obligatoria.',
            'fecha_corte.date' => 'La fecha de corte no tiene un formato válido.',
        ];
    }
}

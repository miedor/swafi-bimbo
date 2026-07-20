<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
        $method = trim((string) $this->input('metodo_depreciacion'));

        $this->merge([
            'numero_activo' => mb_strtoupper(trim((string) $this->input('numero_activo')), 'UTF-8'),
            'moneda' => mb_strtoupper(trim((string) $this->input('moneda', 'MXN')), 'UTF-8'),
            'estatus_contable' => mb_strtolower(trim((string) $this->input('estatus_contable', 'vigente')), 'UTF-8'),
            'metodo_depreciacion' => $method !== '' ? mb_strtolower($method, 'UTF-8') : null,
            'origen_tipo_cambio' => trim((string) $this->input('origen_tipo_cambio')),
            'motivo_cambio' => trim((string) $this->input('motivo_cambio')),
            'valor_residual' => $this->filled('valor_residual')
                ? $this->input('valor_residual')
                : ($method !== '' ? 0 : null),
        ]);
    }

    public function rules(): array
    {
        $methods = array_keys((array) config('swafi.depreciacion.metodos', []));

        return [
            'valor_id' => ['nullable', 'integer', 'exists:valores_activo,id'],
            'numero_activo' => ['required', 'string', 'max:30', 'exists:activos,numero_activo'],
            'valor_fiscal' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'valor_financiero' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'moneda' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('monedas', 'clave')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'tipo_cambio' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.999999'],
            'fecha_tipo_cambio' => ['nullable', 'date'],
            'origen_tipo_cambio' => ['nullable', 'string', 'max:120'],
            'depreciacion_acumulada' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'valor_en_libros' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'metodo_depreciacion' => ['nullable', 'string', Rule::in($methods)],
            'fecha_inicio_depreciacion' => ['nullable', 'date'],
            'valor_residual' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'estatus_contable' => [
                'required',
                'string',
                Rule::exists('estatus_contables', 'clave')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
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
            $currency = (string) $this->input('moneda', 'MXN');
            $exchangeRate = $this->input('tipo_cambio');
            $method = trim((string) $this->input('metodo_depreciacion'));
            $residualValue = $this->filled('valor_residual')
                ? (float) $this->input('valor_residual')
                : 0.0;

            if ($estatus !== 'baja' && ($valorFiscal <= 0 || $valorFinanciero <= 0)) {
                $validator->errors()->add(
                    'valor_fiscal',
                    'Todo estatus distinto de Baja requiere valor fiscal y valor financiero mayores a cero.'
                );
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

            $currencyRecord = DB::table('monedas')
                ->where('clave', $currency)
                ->where('estatus', 'activo')
                ->first();

            if ($currencyRecord !== null && (bool) $currencyRecord->requiere_tipo_cambio) {
                if ($exchangeRate === null || (float) $exchangeRate <= 0) {
                    $validator->errors()->add(
                        'tipo_cambio',
                        'La moneda seleccionada requiere un tipo de cambio mayor a cero.'
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

            if ($currencyRecord !== null && !(bool) $currencyRecord->requiere_tipo_cambio) {
                if ($exchangeRate !== null && abs((float) $exchangeRate - 1.0) > 0.000001) {
                    $validator->errors()->add(
                        'tipo_cambio',
                        'Para la moneda base el tipo de cambio debe ser 1 o quedar vacío.'
                    );
                }
            }

            $hasDepreciationData = $method !== ''
                || $this->filled('fecha_inicio_depreciacion')
                || $this->filled('valor_residual');

            if ($hasDepreciationData && $method === '') {
                $validator->errors()->add(
                    'metodo_depreciacion',
                    'Selecciona un método para registrar o calcular la depreciación referencial.'
                );
            }

            if ($method !== '') {
                if (!$this->filled('vida_util_meses')) {
                    $validator->errors()->add(
                        'vida_util_meses',
                        'La vida útil es obligatoria cuando se calcula depreciación referencial.'
                    );
                }

                if (!$this->filled('fecha_inicio_depreciacion')) {
                    $validator->errors()->add(
                        'fecha_inicio_depreciacion',
                        'La fecha de inicio es obligatoria cuando se calcula depreciación referencial.'
                    );
                }

                if ($residualValue > $valorFinanciero) {
                    $validator->errors()->add(
                        'valor_residual',
                        'El valor residual no puede ser mayor que el valor financiero utilizado como base.'
                    );
                }

                if (
                    $this->filled('fecha_inicio_depreciacion')
                    && $this->filled('fecha_corte')
                    && strtotime((string) $this->input('fecha_inicio_depreciacion'))
                        > strtotime((string) $this->input('fecha_corte'))
                ) {
                    $validator->errors()->add(
                        'fecha_inicio_depreciacion',
                        'La fecha de inicio de depreciación no puede ser posterior a la fecha de corte.'
                    );
                }
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
            'moneda.exists' => 'La moneda seleccionada no existe o está inactiva.',
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
            'metodo_depreciacion.in' => 'El método de depreciación seleccionado no está implementado en SWAFI.',
            'fecha_inicio_depreciacion.date' => 'La fecha de inicio de depreciación no es válida.',
            'valor_residual.numeric' => 'El valor residual debe ser numérico.',
            'valor_residual.min' => 'El valor residual no puede ser negativo.',
            'estatus_contable.required' => 'El estatus contable es obligatorio.',
            'estatus_contable.exists' => 'El estatus contable seleccionado no existe o está inactivo.',
            'motivo_cambio.max' => 'El motivo del cambio no debe superar 1000 caracteres.',
            'fecha_corte.required' => 'La fecha de corte es obligatoria.',
            'fecha_corte.date' => 'La fecha de corte no tiene un formato válido.',
        ];
    }
}

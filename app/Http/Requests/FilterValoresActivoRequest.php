<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterValoresActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_activo' => trim((string) $this->input('numero_activo')),
            'moneda' => $this->filled('moneda')
                ? mb_strtoupper(trim((string) $this->input('moneda')), 'UTF-8')
                : null,
            'estatus_contable' => $this->filled('estatus_contable')
                ? mb_strtolower(trim((string) $this->input('estatus_contable')), 'UTF-8')
                : null,
            'conciliacion_cfdi' => $this->filled('conciliacion_cfdi')
                ? mb_strtolower(trim((string) $this->input('conciliacion_cfdi')), 'UTF-8')
                : null,
            'export' => $this->filled('export')
                ? mb_strtolower(trim((string) $this->input('export')), 'UTF-8')
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'panel' => ['nullable', Rule::in(['consulta', 'captura', 'importar'])],
            'numero_activo' => ['nullable', 'string', 'max:30'],
            'planta_id' => [
                'nullable',
                'integer',
                Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'proveedor_id' => [
                'nullable',
                'integer',
                Rule::exists('proveedores', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'centro_costo_id' => [
                'nullable',
                'integer',
                Rule::exists('centros_costo', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'tipo_activo_id' => [
                'nullable',
                'integer',
                Rule::exists('tipos_activo', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'estatus_contable' => [
                'nullable',
                'string',
                Rule::exists('estatus_contables', 'clave')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'conciliacion_cfdi' => ['nullable', Rule::in(['validado', 'observado', 'sin_xml'])],
            'moneda' => [
                'nullable',
                'string',
                'size:3',
                Rule::exists('monedas', 'clave')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'valor_desde' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'valor_hasta' => ['nullable', 'numeric', 'gte:valor_desde', 'max:9999999999999999.99'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'export' => ['nullable', Rule::in(['csv'])],
            'editar_valor' => ['nullable', 'integer', 'exists:valores_activo,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'panel.in' => 'El panel solicitado no es válido.',
            'planta_id.exists' => 'La planta seleccionada no existe o está inactiva.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe o está inactivo.',
            'centro_costo_id.exists' => 'El centro de costo seleccionado no existe o está inactivo.',
            'tipo_activo_id.exists' => 'El tipo de activo seleccionado no existe o está inactivo.',
            'estatus_contable.exists' => 'El estatus contable seleccionado no existe o está inactivo.',
            'conciliacion_cfdi.in' => 'El resultado de conciliación CFDI no es válido.',
            'moneda.exists' => 'La moneda seleccionada no existe o está inactiva.',
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'valor_hasta.gte' => 'El valor máximo debe ser igual o mayor que el valor mínimo.',
            'per_page.in' => 'Selecciona una cantidad de registros permitida.',
            'export.in' => 'El formato de exportación solicitado no está permitido.',
        ];
    }
}

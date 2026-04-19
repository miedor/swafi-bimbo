<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistroIndividualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_activo' => ['required', 'string', 'max:30'],
            'tipo_activo_id' => ['required', 'integer', 'exists:tipos_activo,id'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'centro_costo_id' => ['required', 'integer', 'exists:centros_costo,id'],
            'planta_id' => ['required', 'integer', 'exists:plantas,id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:responsables,id'],

            'descripcion' => ['required', 'string', 'max:255'],
            'serie' => ['nullable', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'fecha_adquisicion' => ['nullable', 'date'],
            'estatus_operativo' => ['required', 'string', 'max:20'],

            'folio_factura' => [
                'required',
                'string',
                'max:80',
                Rule::unique('expedientes', 'folio_factura')->where(function ($query) {
                    return $query->where('numero_activo', $this->input('numero_activo'));
                }),
            ],
            'uuid_cfdi' => ['nullable', 'string', 'max:50', 'unique:expedientes,uuid_cfdi'],
            'fecha_factura' => ['required', 'date'],
            'monto_factura' => ['required', 'numeric', 'min:0'],
            'moneda' => ['required', 'string', 'max:10'],
            'observaciones' => ['nullable', 'string'],
            'documentos_referencia' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'El número de activo es obligatorio.',
            'folio_factura.unique' => 'Ya existe un expediente con ese folio para este número de activo.',
            'uuid_cfdi.unique' => 'El UUID CFDI ya está registrado.',
        ];
    }
}

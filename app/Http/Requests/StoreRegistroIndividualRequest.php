<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRegistroIndividualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_activo' => strtoupper(trim((string) $this->input('numero_activo'))),
            'folio_factura' => trim((string) $this->input('folio_factura')),
            'uuid_cfdi' => $this->filled('uuid_cfdi')
                ? strtoupper(trim((string) $this->input('uuid_cfdi')))
                : null,
            'moneda' => strtoupper(trim((string) $this->input('moneda', 'MXN'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'numero_activo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9\-]*$/',
            ],

            'descripcion' => ['required', 'string', 'min:3', 'max:255'],
            'tipo_activo_id' => ['required', 'integer', 'exists:tipos_activo,id'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'centro_costo_id' => ['required', 'integer', 'exists:centros_costo,id'],
            'planta_id' => ['required', 'integer', 'exists:plantas,id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:responsables,id'],

            'serie' => ['nullable', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'fecha_adquisicion' => ['nullable', 'date'],
            'estatus_operativo' => [
                'required',
                'string',
                'max:20',
                Rule::exists('estatus_operativos', 'clave')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],

            'folio_factura' => [
                'required',
                'string',
                'max:80',
                Rule::unique('expedientes', 'folio_factura')
                    ->where(fn ($query) => $query->where('numero_activo', $this->input('numero_activo'))),
            ],
            'uuid_cfdi' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-F0-9\-]{32,36}$/',
                Rule::unique('expedientes', 'uuid_cfdi'),
            ],
            'fecha_factura' => ['required', 'date'],
            'monto_factura' => ['required', 'numeric', 'gt:0'],
            'moneda' => ['required', Rule::in(['MXN', 'USD', 'EUR'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            'documentos' => ['nullable', 'array', 'max:20'],
            'documentos.*' => [
                'file',
                'mimes:pdf,xml',
                'max:10240',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fechaFactura = $this->input('fecha_factura');
            $fechaAdquisicion = $this->input('fecha_adquisicion');

            if ($fechaFactura && $fechaAdquisicion && $fechaAdquisicion > $fechaFactura) {
                $validator->errors()->add(
                    'fecha_adquisicion',
                    'La fecha de adquisición no puede ser posterior a la fecha de la factura.'
                );
            }

            $files = $this->file('documentos', []);

            foreach ($files as $index => $file) {
                if (!$file || !$file->isValid()) {
                    $validator->errors()->add(
                        "documentos.{$index}",
                        'Uno de los documentos seleccionados no pudo cargarse correctamente.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'numero_activo.required' => 'El número de activo es obligatorio.',
            'numero_activo.regex' => 'El número de activo solo puede contener letras mayúsculas, números y guiones.',
            'descripcion.required' => 'La descripción del activo es obligatoria.',
            'descripcion.min' => 'La descripción debe contener al menos 3 caracteres.',

            'tipo_activo_id.required' => 'Debes seleccionar el tipo de activo.',
            'tipo_activo_id.exists' => 'El tipo de activo seleccionado no existe.',
            'proveedor_id.required' => 'Debes seleccionar el proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
            'centro_costo_id.required' => 'Debes seleccionar el centro de costo.',
            'centro_costo_id.exists' => 'El centro de costo seleccionado no existe.',
            'planta_id.required' => 'Debes seleccionar la planta.',
            'planta_id.exists' => 'La planta seleccionada no existe.',
            'ubicacion_id.exists' => 'La ubicación seleccionada no existe.',
            'responsable_id.exists' => 'El responsable seleccionado no existe.',

            'estatus_operativo.required' => 'El estatus operativo es obligatorio.',
            'estatus_operativo.exists' => 'El estatus operativo seleccionado no existe o está inactivo.',

            'folio_factura.required' => 'El folio de factura es obligatorio.',
            'folio_factura.unique' => 'El activo ya tiene registrado un expediente con ese folio de factura.',
            'uuid_cfdi.regex' => 'El UUID CFDI debe contener entre 32 y 36 caracteres hexadecimales y guiones.',
            'uuid_cfdi.unique' => 'El UUID CFDI ya está registrado en otro expediente.',
            'fecha_factura.required' => 'La fecha de factura es obligatoria.',
            'fecha_factura.date' => 'La fecha de factura no tiene un formato válido.',
            'monto_factura.required' => 'El monto de la factura es obligatorio.',
            'monto_factura.numeric' => 'El monto de la factura debe ser numérico.',
            'monto_factura.gt' => 'El monto de la factura debe ser mayor a cero.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.in' => 'La moneda debe ser MXN, USD o EUR.',

            'documentos.array' => 'La selección de documentos no tiene un formato válido.',
            'documentos.max' => 'Puedes adjuntar como máximo 20 documentos por expediente.',
            'documentos.*.file' => 'Cada documento debe ser un archivo válido.',
            'documentos.*.mimes' => 'Los documentos del expediente deben ser PDF o XML.',
            'documentos.*.max' => 'Cada documento no debe superar los 10 MB.',
        ];
    }
}

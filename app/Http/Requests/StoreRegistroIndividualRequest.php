<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            'asset_mode' => strtolower(trim((string) $this->input('asset_mode', 'new'))),
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
        $numeroActivoRules = [
            'required',
            'string',
            'max:30',
            'regex:/^[A-Z0-9][A-Z0-9\-]*$/',
        ];

        $numeroActivoRules[] = $this->isExistingAsset()
            ? Rule::exists('activos', 'numero_activo')
                ->where(fn ($query) => $query->where('activo', true))
            : Rule::unique('activos', 'numero_activo');

        return [
            'asset_mode' => ['required', Rule::in(['new', 'existing'])],
            'numero_activo' => $numeroActivoRules,

            'descripcion' => $this->newAssetRules(['string', 'min:3', 'max:255'], true),
            'tipo_activo_id' => $this->newAssetRules([
                'integer',
                Rule::exists('tipos_activo', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ], true),
            'proveedor_id' => $this->newAssetRules([
                'integer',
                Rule::exists('proveedores', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ], true),
            'centro_costo_id' => $this->newAssetRules([
                'integer',
                Rule::exists('centros_costo', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ], true),
            'planta_id' => $this->newAssetRules([
                'integer',
                Rule::exists('plantas', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ], true),
            'ubicacion_id' => $this->newAssetRules([
                'integer',
                Rule::exists('ubicaciones', 'id')
                    ->where(function ($query): void {
                        $query->where('estatus', 'activo');

                        if ($this->filled('planta_id')) {
                            $query->where('planta_id', (int) $this->input('planta_id'));
                        }
                    }),
            ]),
            'responsable_id' => $this->newAssetRules([
                'integer',
                Rule::exists('responsables', 'id')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ]),

            'serie' => $this->newAssetRules(['string', 'max:120']),
            'marca' => $this->newAssetRules(['string', 'max:100']),
            'modelo' => $this->newAssetRules(['string', 'max:100']),
            'fecha_adquisicion' => $this->newAssetRules(['date']),
            'estatus_operativo' => $this->newAssetRules([
                'string',
                'max:20',
                Rule::exists('estatus_operativos', 'clave')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ], true),

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
            ],
            'fecha_factura' => ['required', 'date'],
            'monto_factura' => ['required', 'numeric', 'gt:0'],
            'moneda' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('monedas', 'clave')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
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
        $validator->after(function (Validator $validator): void {
            if ($this->isNewAsset()) {
                $this->validateAssetDates($validator);
                $this->validateCostCenterPlant($validator);
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
            'asset_mode.required' => 'Selecciona si registrarás un activo nuevo o utilizarás uno existente.',
            'asset_mode.in' => 'El modo de registro del activo no es válido.',
            'numero_activo.required' => 'El número de activo es obligatorio.',
            'numero_activo.regex' => 'El número de activo solo puede contener letras mayúsculas, números y guiones.',
            'numero_activo.unique' => 'El activo ya existe. Utiliza la opción “Buscar activo existente” para asociar el nuevo expediente sin modificar sus datos maestros.',
            'numero_activo.exists' => 'El activo seleccionado no existe o se encuentra inactivo.',
            'descripcion.required' => 'La descripción del activo es obligatoria.',
            'descripcion.min' => 'La descripción debe contener al menos 3 caracteres.',

            'tipo_activo_id.required' => 'Debes seleccionar el tipo de activo.',
            'tipo_activo_id.exists' => 'El tipo de activo seleccionado no existe o está inactivo.',
            'proveedor_id.required' => 'Debes seleccionar el proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe o está inactivo.',
            'centro_costo_id.required' => 'Debes seleccionar el centro de costo.',
            'centro_costo_id.exists' => 'El centro de costo seleccionado no existe o está inactivo.',
            'planta_id.required' => 'Debes seleccionar la planta.',
            'planta_id.exists' => 'La planta seleccionada no existe o está inactiva.',
            'ubicacion_id.exists' => 'La ubicación seleccionada no existe, está inactiva o no pertenece a la planta indicada.',
            'responsable_id.exists' => 'El responsable seleccionado no existe o está inactivo.',

            'estatus_operativo.required' => 'El estatus operativo es obligatorio.',
            'estatus_operativo.exists' => 'El estatus operativo seleccionado no existe o está inactivo.',

            'folio_factura.required' => 'El folio de factura es obligatorio.',
            'folio_factura.unique' => 'El activo ya tiene registrado un expediente con ese folio de factura.',
            'uuid_cfdi.regex' => 'El UUID CFDI debe contener entre 32 y 36 caracteres hexadecimales y guiones.',
            'fecha_factura.required' => 'La fecha de factura es obligatoria.',
            'fecha_factura.date' => 'La fecha de factura no tiene un formato válido.',
            'monto_factura.required' => 'El monto de la factura es obligatorio.',
            'monto_factura.numeric' => 'El monto de la factura debe ser numérico.',
            'monto_factura.gt' => 'El monto de la factura debe ser mayor a cero.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.size' => 'La moneda debe capturarse con tres letras.',
            'moneda.regex' => 'La moneda solo puede contener letras mayúsculas.',
            'moneda.exists' => 'La moneda seleccionada no existe o se encuentra inactiva.',

            'descripcion.prohibited' => 'Los datos maestros de un activo existente no pueden modificarse desde el registro de expedientes.',
            'tipo_activo_id.prohibited' => 'Los datos maestros de un activo existente no pueden modificarse desde el registro de expedientes.',
            'proveedor_id.prohibited' => 'El proveedor del activo existente no puede modificarse desde el registro de expedientes.',
            'centro_costo_id.prohibited' => 'El centro de costo del activo existente no puede modificarse desde el registro de expedientes.',
            'planta_id.prohibited' => 'La planta del activo existente no puede modificarse desde el registro de expedientes.',
            'ubicacion_id.prohibited' => 'La ubicación del activo existente no puede modificarse desde el registro de expedientes.',
            'responsable_id.prohibited' => 'El responsable del activo existente no puede modificarse desde el registro de expedientes.',
            'serie.prohibited' => 'La serie del activo existente no puede modificarse desde el registro de expedientes.',
            'marca.prohibited' => 'La marca del activo existente no puede modificarse desde el registro de expedientes.',
            'modelo.prohibited' => 'El modelo del activo existente no puede modificarse desde el registro de expedientes.',
            'fecha_adquisicion.prohibited' => 'La fecha de adquisición del activo existente no puede modificarse desde el registro de expedientes.',
            'estatus_operativo.prohibited' => 'El estatus del activo existente no puede modificarse desde el registro de expedientes.',

            'documentos.array' => 'La selección de documentos no tiene un formato válido.',
            'documentos.max' => 'Puedes adjuntar como máximo 20 documentos por expediente.',
            'documentos.*.file' => 'Cada documento debe ser un archivo válido.',
            'documentos.*.mimes' => 'Los documentos del expediente deben ser PDF o XML.',
            'documentos.*.max' => 'Cada documento no debe superar los 10 MB.',
        ];
    }

    private function newAssetRules(array $rules, bool $required = false): array
    {
        if ($this->isExistingAsset()) {
            return ['prohibited'];
        }

        return array_merge([$required ? 'required' : 'nullable'], $rules);
    }

    private function isExistingAsset(): bool
    {
        return $this->input('asset_mode') === 'existing';
    }

    private function isNewAsset(): bool
    {
        return !$this->isExistingAsset();
    }

    private function validateAssetDates(Validator $validator): void
    {
        $fechaFactura = $this->input('fecha_factura');
        $fechaAdquisicion = $this->input('fecha_adquisicion');

        if ($fechaFactura && $fechaAdquisicion && $fechaAdquisicion > $fechaFactura) {
            $validator->errors()->add(
                'fecha_adquisicion',
                'La fecha de adquisición no puede ser posterior a la fecha de la factura.'
            );
        }
    }

    private function validateCostCenterPlant(Validator $validator): void
    {
        if (!$this->filled('centro_costo_id') || !$this->filled('planta_id')) {
            return;
        }

        $costCenter = DB::table('centros_costo')
            ->where('id', (int) $this->input('centro_costo_id'))
            ->where('estatus', 'activo')
            ->first(['id', 'planta_id']);

        if (
            $costCenter
            && $costCenter->planta_id !== null
            && (int) $costCenter->planta_id !== (int) $this->input('planta_id')
        ) {
            $validator->errors()->add(
                'centro_costo_id',
                'El centro de costo seleccionado no pertenece a la planta indicada.'
            );
        }
    }
}

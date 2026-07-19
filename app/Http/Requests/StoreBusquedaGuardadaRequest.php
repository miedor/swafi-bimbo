<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBusquedaGuardadaRequest extends FormRequest
{
    private const FILTER_FIELDS = [
        'folio_factura',
        'uuid_cfdi',
        'proveedor',
        'rfc',
        'numero_activo',
        'planta_id',
        'centro_costo_id',
        'area_id',
        'ubicacion_id',
        'estatus',
        'estatus_operativo',
        'fecha_desde',
        'fecha_hasta',
        'monto_desde',
        'monto_hasta',
        'ordenar_por',
        'direccion',
        'per_page',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->input('filtros');

        if (!is_array($filters)) {
            $filters = [];
        }

        foreach (self::FILTER_FIELDS as $field) {
            if (!array_key_exists($field, $filters)) {
                continue;
            }

            if (is_string($filters[$field])) {
                $filters[$field] = trim($filters[$field]);
            }
        }

        $this->merge([
            'nombre' => trim((string) $this->input('nombre', '')),
            'filtros' => $filters,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
            'filtros' => [
                'required',
                'array:' . implode(',', self::FILTER_FIELDS),
            ],
            'filtros.folio_factura' => ['nullable', 'string', 'max:100'],
            'filtros.uuid_cfdi' => ['nullable', 'string', 'max:64'],
            'filtros.proveedor' => ['nullable', 'string', 'max:180'],
            'filtros.rfc' => ['nullable', 'string', 'max:20'],
            'filtros.numero_activo' => ['nullable', 'string', 'max:60'],
            'filtros.planta_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('plantas', 'id')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'filtros.centro_costo_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('centros_costo', 'id')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'filtros.area_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('areas', 'id')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'filtros.ubicacion_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('ubicaciones', 'id')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'filtros.estatus' => [
                'nullable',
                'string',
                'max:20',
                Rule::exists('estatus_documentales', 'clave')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'filtros.estatus_operativo' => [
                'nullable',
                'string',
                'max:20',
                Rule::exists('estatus_operativos', 'clave')->where(
                    static fn ($query) => $query->where('estatus', 'activo')
                ),
            ],
            'filtros.fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'filtros.fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'filtros.monto_desde' => ['nullable', 'numeric', 'min:0'],
            'filtros.monto_hasta' => ['nullable', 'numeric', 'min:0'],
            'filtros.ordenar_por' => [
                'nullable',
                Rule::in([
                    'fecha_factura',
                    'fecha_registro',
                    'numero_activo',
                    'folio_factura',
                    'proveedor',
                    'planta',
                    'monto_factura',
                    'estatus',
                ]),
            ],
            'filtros.direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'filtros.per_page' => [
                'nullable',
                Rule::in(['10', '25', '50', '100', 10, 25, 50, 100]),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $filters = (array) $this->input('filtros', []);

                $fechaDesde = $filters['fecha_desde'] ?? null;
                $fechaHasta = $filters['fecha_hasta'] ?? null;

                if (
                    is_string($fechaDesde)
                    && $fechaDesde !== ''
                    && is_string($fechaHasta)
                    && $fechaHasta !== ''
                    && $fechaHasta < $fechaDesde
                ) {
                    $validator->errors()->add(
                        'filtros.fecha_hasta',
                        'La fecha final debe ser igual o posterior a la fecha inicial.'
                    );
                }

                $montoDesde = $filters['monto_desde'] ?? null;
                $montoHasta = $filters['monto_hasta'] ?? null;

                if (
                    $montoDesde !== null
                    && $montoDesde !== ''
                    && $montoHasta !== null
                    && $montoHasta !== ''
                    && is_numeric($montoDesde)
                    && is_numeric($montoHasta)
                    && (float) $montoHasta < (float) $montoDesde
                ) {
                    $validator->errors()->add(
                        'filtros.monto_hasta',
                        'El monto final debe ser igual o mayor que el monto inicial.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Debes asignar un nombre a la búsqueda.',
            'nombre.min' => 'El nombre de la búsqueda debe contener al menos 3 caracteres.',
            'nombre.max' => 'El nombre de la búsqueda no debe superar 100 caracteres.',
            'filtros.required' => 'No se recibieron filtros para guardar.',
            'filtros.array' => 'Los filtros recibidos no tienen un formato válido.',
            'filtros.*.integer' => 'El criterio seleccionado no tiene un identificador válido.',
            'filtros.*.exists' => 'El criterio seleccionado ya no está disponible en el catálogo.',
            'filtros.*.date_format' => 'Las fechas deben utilizar el formato AAAA-MM-DD.',
            'filtros.*.numeric' => 'Los montos deben contener únicamente valores numéricos.',
            'filtros.*.min' => 'El valor capturado no puede ser negativo.',
            'filtros.*.max' => 'Uno de los criterios supera la longitud permitida.',
            'filtros.*.in' => 'Uno de los criterios recibidos no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la búsqueda',
            'filtros.folio_factura' => 'folio de factura',
            'filtros.uuid_cfdi' => 'UUID CFDI',
            'filtros.proveedor' => 'proveedor',
            'filtros.rfc' => 'RFC',
            'filtros.numero_activo' => 'número de activo',
            'filtros.planta_id' => 'planta',
            'filtros.centro_costo_id' => 'centro de costo',
            'filtros.area_id' => 'área',
            'filtros.ubicacion_id' => 'ubicación física',
            'filtros.estatus' => 'estatus documental',
            'filtros.estatus_operativo' => 'estatus operativo',
            'filtros.fecha_desde' => 'fecha inicial',
            'filtros.fecha_hasta' => 'fecha final',
            'filtros.monto_desde' => 'monto inicial',
            'filtros.monto_hasta' => 'monto final',
            'filtros.ordenar_por' => 'campo de ordenamiento',
            'filtros.direccion' => 'dirección de ordenamiento',
            'filtros.per_page' => 'registros por página',
        ];
    }
}

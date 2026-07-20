<?php

namespace App\Http\Requests;

use App\Services\CatalogManagementService;
use App\Services\CatalogVisibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visibility = app(CatalogVisibilityService::class);
        $catalog = trim((string) $this->input('catalogo', ''));

        if (!$visibility->canView($this, $catalog)) {
            return false;
        }

        if ((
            $this->filled('editar')
            || $this->filled('export')
            || $this->filled('lote')
            || $this->filled('import_status')
        ) && !$visibility->canAdminister($this)) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'catalogo' => ['required', Rule::in(array_keys(CatalogManagementService::CATALOGS))],
            'buscar' => ['nullable', 'string', 'max:120'],
            'estatus' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'planta_id' => ['nullable', 'integer', Rule::exists('plantas', 'id')],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'categoria_activo_id' => ['nullable', 'integer', Rule::exists('categorias_activo', 'id')],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'export' => ['nullable', Rule::in(['csv'])],
            'editar' => ['nullable', 'integer', 'min:1'],
            'detalle' => ['nullable', 'integer', 'min:1'],
            'swafi_focus' => ['nullable', 'string', 'max:80'],
            'lote' => ['nullable', 'uuid'],
            'import_status' => ['nullable', Rule::in(['aceptada', 'observada', 'rechazada'])],
        ];
    }

    public function messages(): array
    {
        return [
            'catalogo.in' => 'El catálogo seleccionado no es válido.',
            'buscar.max' => 'La búsqueda no puede superar 120 caracteres.',
            'estatus.in' => 'El estatus seleccionado no es válido.',
            'planta_id.exists' => 'La planta seleccionada ya no existe.',
            'area_id.exists' => 'El área seleccionada ya no existe.',
            'categoria_activo_id.exists' => 'La categoría de activo seleccionada ya no existe.',
            'per_page.in' => 'Selecciona 10, 25 o 50 registros por página.',
            'export.in' => 'El formato de exportación solicitado no es válido.',
            'editar.integer' => 'El registro solicitado para edición no es válido.',
            'detalle.integer' => 'El registro solicitado para consulta no es válido.',
            'lote.uuid' => 'El identificador de la previsualización no es válido.',
            'import_status.in' => 'El filtro de clasificación de la previsualización no es válido.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $catalog = (string) $this->input('catalogo', 'proveedores');
                $definition = CatalogManagementService::CATALOGS[$catalog] ?? null;

                if ($definition === null) {
                    return;
                }

                if ($this->filled('planta_id') && !in_array($catalog, ['centros_costo', 'areas', 'ubicaciones'], true)) {
                    $validator->errors()->add(
                        'planta_id',
                        'El filtro de planta solo está disponible para centros de costo, áreas y ubicaciones.'
                    );
                }

                if ($this->filled('area_id') && $catalog !== 'ubicaciones') {
                    $validator->errors()->add(
                        'area_id',
                        'El filtro de área solo está disponible para ubicaciones.'
                    );
                }

                if ($this->filled('categoria_activo_id') && $catalog !== 'tipos_activo') {
                    $validator->errors()->add(
                        'categoria_activo_id',
                        'El filtro de categoría solo está disponible para tipos de activo.'
                    );
                }

                if ($catalog === 'ubicaciones' && $this->filled('planta_id') && $this->filled('area_id')) {
                    $belongsToPlant = DB::table('areas')
                        ->where('id', (int) $this->input('area_id'))
                        ->where('planta_id', (int) $this->input('planta_id'))
                        ->exists();

                    if (!$belongsToPlant) {
                        $validator->errors()->add(
                            'area_id',
                            'El área seleccionada no pertenece a la planta indicada.'
                        );
                    }
                }

                foreach (['editar', 'detalle'] as $field) {
                    if (!$this->filled($field)) {
                        continue;
                    }

                    $exists = DB::table($definition['table'])
                        ->where('id', (int) $this->input($field))
                        ->exists();

                    if (!$exists) {
                        $validator->errors()->add(
                            $field,
                            $field === 'editar'
                                ? 'El registro solicitado para edición ya no existe.'
                                : 'El registro solicitado para consulta ya no existe.'
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $catalog = trim((string) $this->input('catalogo', ''));

        if ($catalog === '') {
            $catalog = app(CatalogVisibilityService::class)->firstVisible($this)
                ?? 'proveedores';
        }

        $normalized = [
            'catalogo' => $catalog,
        ];

        foreach (['buscar', 'estatus', 'export', 'swafi_focus', 'lote', 'import_status'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        foreach (['planta_id', 'area_id', 'categoria_activo_id', 'per_page', 'editar', 'detalle'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
                $normalized[$field] = (int) trim($value);
            }
        }

        $this->merge($normalized);
    }
}

<?php

namespace App\Http\Requests;

use App\Services\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $roles = collect($this->session()->get('swafi_roles', []))
            ->filter(fn ($role) => is_scalar($role))
            ->map(fn ($role) => mb_strtolower(trim((string) $role)));

        $permissions = collect($this->session()->get('swafi_permissions', []))
            ->filter(fn ($permission) => is_scalar($permission))
            ->map(fn ($permission) => trim((string) $permission));

        return $roles->contains('administrador swafi')
            || $permissions->contains('catalogos.administrar');
    }

    public function rules(): array
    {
        $catalog = $this->catalog();
        $recordId = $this->recordId();

        $common = [
            'catalogo' => ['required', Rule::in(array_keys(CatalogManagementService::CATALOGS))],
            'id' => ['nullable', 'integer', 'min:1'],
            'estatus' => ['required', Rule::in(['activo', 'inactivo'])],
        ];

        $catalogRules = match ($catalog) {
            'proveedores' => [
                'rfc' => [
                    'required',
                    'string',
                    'min:12',
                    'max:13',
                    'regex:/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u',
                    Rule::unique('proveedores', 'rfc')->ignore($recordId),
                ],
                'nombre' => ['required', 'string', 'min:3', 'max:150'],
                'correo' => ['nullable', 'email:rfc', 'max:120'],
                'telefono' => ['nullable', 'string', 'max:30'],
            ],

            'plantas' => [
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('plantas', 'clave')->ignore($recordId),
                ],
                'nombre' => ['required', 'string', 'min:3', 'max:150'],
                'direccion' => ['required', 'string', 'min:5', 'max:255'],
                'estado' => ['nullable', 'string', 'max:100'],
                'pais' => ['required', 'string', 'min:2', 'max:80'],
            ],

            'centros_costo' => [
                'planta_id' => [
                    'required',
                    'integer',
                    Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('centros_costo', 'clave')->ignore($recordId),
                ],
                'descripcion' => ['required', 'string', 'min:3', 'max:150'],
            ],

            'categorias_activo' => [
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('categorias_activo', 'clave')->ignore($recordId),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'min:3',
                    'max:120',
                    Rule::unique('categorias_activo', 'nombre')->ignore($recordId),
                ],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],

            'tipos_activo' => [
                'categoria_activo_id' => [
                    'required',
                    'integer',
                    Rule::exists('categorias_activo', 'id')
                        ->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('tipos_activo', 'clave')->ignore($recordId),
                ],
                'descripcion' => [
                    'required',
                    'string',
                    'min:3',
                    'max:120',
                    Rule::unique('tipos_activo', 'descripcion')->ignore($recordId),
                ],
                'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:600'],
            ],

            'estatus_documentales', 'estatus_operativos' => [
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:20',
                    'regex:/^[a-z][a-z0-9_]*$/',
                    Rule::unique(
                        $catalog === 'estatus_documentales'
                            ? 'estatus_documentales'
                            : 'estatus_operativos',
                        'clave'
                    )->ignore($recordId),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'min:3',
                    'max:80',
                    Rule::unique(
                        $catalog === 'estatus_documentales'
                            ? 'estatus_documentales'
                            : 'estatus_operativos',
                        'nombre'
                    )->ignore($recordId),
                ],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'orden' => ['required', 'integer', 'min:1', 'max:999'],
            ],

            'areas' => [
                'planta_id' => [
                    'required',
                    'integer',
                    Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'clave' => [
                    'required',
                    'string',
                    'min:2',
                    'max:30',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('areas', 'clave')
                        ->where(fn ($query) => $query->where('planta_id', $this->input('planta_id')))
                        ->ignore($recordId),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'min:2',
                    'max:120',
                    Rule::unique('areas', 'nombre')
                        ->where(fn ($query) => $query->where('planta_id', $this->input('planta_id')))
                        ->ignore($recordId),
                ],
            ],

            'ubicaciones' => [
                'planta_id' => [
                    'required',
                    'integer',
                    Rule::exists('plantas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'area_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('areas', 'id')->where(fn ($query) => $query->where('estatus', 'activo')),
                ],
                'codigo_interno' => [
                    'nullable',
                    'string',
                    'max:60',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                    Rule::unique('ubicaciones', 'codigo_interno')->ignore($recordId),
                ],
                'edificio' => ['nullable', 'string', 'max:100'],
                'piso' => ['nullable', 'string', 'max:50'],
                'pasillo' => ['nullable', 'string', 'max:50'],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],

            'responsables' => [
                'nombre' => ['required', 'string', 'min:3', 'max:120'],
                'correo' => ['nullable', 'email:rfc', 'max:120'],
                'telefono' => ['nullable', 'string', 'max:30'],
            ],

            default => [],
        };

        return array_merge($common, $catalogRules);
    }

    public function messages(): array
    {
        return [
            'catalogo.in' => 'El catálogo seleccionado no es válido.',
            'id.integer' => 'El identificador del registro no es válido.',
            'estatus.in' => 'El estatus seleccionado no es válido.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe contener texto.',
            'integer' => 'El campo :attribute debe contener un número entero.',
            'min' => 'El campo :attribute no cumple el valor mínimo permitido.',
            'max' => 'El campo :attribute supera el límite permitido.',
            'unique' => 'El valor capturado en :attribute ya está registrado.',
            'exists' => 'La opción seleccionada en :attribute no existe o está inactiva.',
            'email' => 'El correo electrónico no tiene un formato válido.',
            'regex' => 'El formato capturado en :attribute no es válido.',
            'rfc.regex' => 'El RFC debe contener 12 o 13 caracteres con estructura válida.',
            'clave.regex' => in_array($this->catalog(), ['estatus_documentales', 'estatus_operativos'], true)
                ? 'La clave técnica debe iniciar con una letra minúscula y solo puede contener letras minúsculas, números y guion bajo.'
                : 'La clave solo puede contener letras mayúsculas, números, punto, guion y guion bajo.',
            'codigo_interno.regex' => 'El código interno solo puede contener letras mayúsculas, números, punto, guion y guion bajo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rfc' => 'RFC',
            'nombre' => 'nombre',
            'correo' => 'correo electrónico',
            'telefono' => 'teléfono',
            'clave' => 'clave',
            'direccion' => 'dirección',
            'estado' => 'estado',
            'pais' => 'país',
            'descripcion' => 'descripción',
            'vida_util_meses' => 'vida útil en meses',
            'orden' => 'orden de presentación',
            'categoria_activo_id' => 'categoría de activo',
            'planta_id' => 'planta',
            'area_id' => 'área',
            'codigo_interno' => 'código interno',
            'edificio' => 'edificio',
            'piso' => 'piso',
            'pasillo' => 'pasillo',
            'estatus' => 'estatus',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $definition = CatalogManagementService::CATALOGS[$this->catalog()] ?? null;

                if ($definition === null) {
                    return;
                }

                if ($this->recordId() !== null) {
                    $exists = DB::table($definition['table'])
                        ->where('id', $this->recordId())
                        ->exists();

                    if (!$exists) {
                        $validator->errors()->add(
                            'id',
                            'El registro que intentas actualizar ya no existe.'
                        );
                    }
                }

                if ($this->catalog() === 'ubicaciones' && $this->filled('area_id')) {
                    $belongsToPlant = DB::table('areas')
                        ->where('id', (int) $this->input('area_id'))
                        ->where('planta_id', (int) $this->input('planta_id'))
                        ->where('estatus', 'activo')
                        ->exists();

                    if (!$belongsToPlant) {
                        $validator->errors()->add(
                            'area_id',
                            'El área seleccionada no pertenece a la planta indicada o está inactiva.'
                        );
                    }
                }
            },
        ];
    }

    public function catalog(): string
    {
        return (string) $this->input('catalogo', 'proveedores');
    }

    public function recordId(): ?int
    {
        $id = $this->input('id');

        return is_int($id) && $id > 0 ? $id : null;
    }

    public function catalogData(): array
    {
        $fields = CatalogManagementService::CATALOGS[$this->catalog()]['fields'] ?? [];

        return Arr::only($this->validated(), $fields);
    }

    protected function prepareForValidation(): void
    {
        $catalog = trim((string) $this->input('catalogo', 'proveedores')) ?: 'proveedores';
        $normalized = [
            'catalogo' => $catalog,
            'id' => $this->normalizeInteger($this->input('id')),
            'estatus' => mb_strtolower(trim((string) $this->input('estatus', 'activo'))),
        ];

        foreach (['rfc', 'codigo_interno'] as $field) {
            $normalized[$field] = $this->normalizeUppercase($this->input($field));
        }

        $normalized['clave'] = in_array($catalog, ['estatus_documentales', 'estatus_operativos'], true)
            ? $this->normalizeStatusKey($this->input('clave'))
            : $this->normalizeUppercase($this->input('clave'));

        foreach ([
            'nombre',
            'correo',
            'telefono',
            'direccion',
            'estado',
            'pais',
            'descripcion',
            'edificio',
            'piso',
            'pasillo',
        ] as $field) {
            $normalized[$field] = $this->normalizeNullableString($this->input($field));
        }

        if ($catalog === 'plantas' && ($normalized['pais'] ?? null) === null) {
            $normalized['pais'] = 'México';
        }

        foreach (['planta_id', 'area_id', 'categoria_activo_id', 'vida_util_meses', 'orden'] as $field) {
            $normalized[$field] = $this->normalizeInteger($this->input($field));
        }

        $this->merge($normalized);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeUppercase(mixed $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);

        return $normalized === null ? null : mb_strtoupper($normalized);
    }

    private function normalizeStatusKey(mixed $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);

        if ($normalized === null) {
            return null;
        }

        $normalized = mb_strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_') ?: null;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $integer = (int) trim($value);

            return $integer > 0 ? $integer : null;
        }

        return null;
    }
}

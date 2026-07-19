<?php

namespace App\Http\Requests;

use App\Services\CatalogManagementService;
use App\Services\CatalogValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        return app(CatalogValidationService::class)->rules(
            $this->catalog(),
            $this->recordId(),
            $this->all()
        );
    }

    public function messages(): array
    {
        return app(CatalogValidationService::class)->messages($this->catalog());
    }

    public function attributes(): array
    {
        return app(CatalogValidationService::class)->attributes();
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

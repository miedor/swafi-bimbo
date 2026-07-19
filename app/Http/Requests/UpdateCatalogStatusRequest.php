<?php

namespace App\Http\Requests;

use App\Services\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCatalogStatusRequest extends FormRequest
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
        return [
            'catalogo' => ['required', Rule::in(array_keys(CatalogManagementService::CATALOGS))],
            'id' => ['required', 'integer', 'min:1'],
            'estatus' => ['required', Rule::in(['activo', 'inactivo'])],
        ];
    }

    public function messages(): array
    {
        return [
            'catalogo.in' => 'El catálogo solicitado no es válido.',
            'id.required' => 'No fue posible identificar el registro del catálogo.',
            'id.integer' => 'El identificador del registro no es válido.',
            'estatus.in' => 'El estatus solicitado no es válido.',
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

                $exists = DB::table($definition['table'])
                    ->where('id', $this->recordId())
                    ->exists();

                if (!$exists) {
                    $validator->errors()->add(
                        'id',
                        'El registro seleccionado ya no existe.'
                    );
                }
            },
        ];
    }

    public function catalog(): string
    {
        return (string) $this->input('catalogo');
    }

    public function recordId(): int
    {
        return (int) $this->input('id');
    }

    public function status(): string
    {
        return (string) $this->input('estatus');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'catalogo' => trim((string) $this->route('catalogo')),
            'id' => (int) $this->route('id'),
            'estatus' => mb_strtolower(trim((string) $this->input('estatus'))),
        ]);
    }
}

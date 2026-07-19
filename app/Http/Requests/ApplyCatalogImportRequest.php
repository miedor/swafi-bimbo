<?php

namespace App\Http\Requests;

use App\Services\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyCatalogImportRequest extends FormRequest
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
            'confirmar_aplicacion' => ['required', 'accepted'],
            'catalogo' => ['required', 'string', Rule::in(array_keys(CatalogManagementService::CATALOGS))],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmar_aplicacion.required' => 'Debes confirmar que revisaste la previsualización antes de aplicar el lote.',
            'confirmar_aplicacion.accepted' => 'Debes confirmar que revisaste la previsualización antes de aplicar el lote.',
            'catalogo.in' => 'El catálogo asociado al lote no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'catalogo' => trim((string) $this->input('catalogo')),
        ]);
    }
}

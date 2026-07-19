<?php

namespace App\Http\Requests;

use App\Services\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportCatalogRequest extends FormRequest
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
            'archivo_csv' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'catalogo.in' => 'El catálogo seleccionado no es válido.',
            'archivo_csv.required' => 'Debes seleccionar un layout CSV o XLSX para previsualizar.',
            'archivo_csv.file' => 'El archivo seleccionado no es válido.',
            'archivo_csv.mimes' => 'El layout debe tener extensión CSV, TXT o XLSX.',
            'archivo_csv.mimetypes' => 'El contenido del archivo no corresponde a un layout CSV o XLSX válido.',
            'archivo_csv.max' => 'El layout no debe superar los 10 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'catalogo' => trim((string) $this->input('catalogo', 'proveedores')) ?: 'proveedores',
        ]);
    }
}

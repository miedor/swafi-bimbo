<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRegistroMasivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo_csv' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo_csv.required' => 'Debes seleccionar un archivo CSV para importar.',
            'archivo_csv.file' => 'El archivo seleccionado no es válido.',
            'archivo_csv.mimes' => 'El archivo debe tener extensión CSV o TXT.',
            'archivo_csv.max' => 'El archivo no debe superar los 10 MB.',
        ];
    }
}

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

            'archivo_zip' => [
                'required',
                'file',
                'mimes:zip',
                'max:51200',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo_csv.required' => 'Debes seleccionar el archivo CSV para importar.',
            'archivo_csv.file' => 'El archivo CSV seleccionado no es válido.',
            'archivo_csv.mimes' => 'El archivo de datos debe tener extensión CSV o TXT.',
            'archivo_csv.max' => 'El archivo CSV no debe superar los 10 MB.',

            'archivo_zip.required' => 'Debes seleccionar el archivo ZIP con los documentos PDF/XML.',
            'archivo_zip.file' => 'El archivo ZIP seleccionado no es válido.',
            'archivo_zip.mimes' => 'El archivo de documentos debe tener extensión ZIP.',
            'archivo_zip.max' => 'El archivo ZIP no debe superar los 50 MB.',
        ];
    }
}

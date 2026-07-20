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
                'extensions:csv,txt,xlsx',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
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
            'archivo_csv.required' => 'Debes seleccionar el layout CSV o XLSX para importar.',
            'archivo_csv.file' => 'El layout seleccionado no es válido.',
            'archivo_csv.extensions' => 'El archivo de datos debe tener extensión CSV, TXT o XLSX.',
            'archivo_csv.mimetypes' => 'El contenido del archivo no corresponde a un layout CSV o XLSX válido.',
            'archivo_csv.max' => 'El layout no debe superar los 10 MB.',

            'archivo_zip.required' => 'Debes seleccionar el archivo ZIP con los documentos PDF/XML.',
            'archivo_zip.file' => 'El archivo ZIP seleccionado no es válido.',
            'archivo_zip.mimes' => 'El archivo de documentos debe tener extensión ZIP.',
            'archivo_zip.max' => 'El archivo ZIP no debe superar los 50 MB.',
        ];
    }
}

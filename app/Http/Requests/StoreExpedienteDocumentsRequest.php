<?php

namespace App\Http\Requests;

use App\Services\ExpedienteDocumentCatalogService;
use App\Services\SwafiAuthorizationService;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpedienteDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = (int) (Auth::id() ?: $this->session()->get('swafi_user_id'));

        if ($userId <= 0) {
            return false;
        }

        $context = app(SwafiAuthorizationService::class)->contextForUser($userId);

        return $context['is_admin'] === true
            || in_array('documentos.cargar', $context['permissions'], true);
    }

    protected function prepareForValidation(): void
    {
        $catalog = app(ExpedienteDocumentCatalogService::class);

        $this->merge([
            'tipo_documento' => $catalog->normalizeRequestedType(
                $this->input('tipo_documento')
            ),
        ]);
    }

    public function rules(): array
    {
        $catalog = app(ExpedienteDocumentCatalogService::class);
        $requestedType = $catalog->normalizeRequestedType(
            $this->input('tipo_documento')
        );

        return [
            'tipo_documento' => [
                'required',
                'string',
                'max:30',
                Rule::in($catalog->uploadTypeKeys()),
            ],
            'documentos' => [
                'required',
                'array',
                'min:1',
                'max:' . $catalog->maxFilesPerUpload(),
            ],
            'documentos.*' => [
                'required',
                'file',
                'max:' . $catalog->safeMaxKilobytesFor($requestedType),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('tipo_documento')) {
                return;
            }

            $catalog = app(ExpedienteDocumentCatalogService::class);
            $requestedType = $catalog->normalizeRequestedType(
                $this->input('tipo_documento')
            );
            $seenNames = [];

            foreach ($this->file('documentos', []) as $index => $file) {
                $normalizedName = mb_strtolower(trim(basename(
                    str_replace('\\', '/', $file->getClientOriginalName())
                )));

                if ($normalizedName === '' || mb_strlen($normalizedName) > 255) {
                    $validator->errors()->add(
                        "documentos.{$index}",
                        'El nombre del archivo está vacío o supera 255 caracteres.'
                    );
                    continue;
                }

                if (isset($seenNames[$normalizedName])) {
                    $validator->errors()->add(
                        "documentos.{$index}",
                        'No selecciones dos archivos con el mismo nombre en una sola carga.'
                    );
                    continue;
                }

                $seenNames[$normalizedName] = true;

                try {
                    $catalog->resolveStoredType($requestedType, $file);
                    $catalog->validateContent($file);
                } catch (DomainException $exception) {
                    $validator->errors()->add(
                        "documentos.{$index}",
                        $exception->getMessage()
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'tipo_documento.required' => 'Debes seleccionar el tipo de documento que deseas adjuntar.',
            'tipo_documento.in' => 'El tipo de documento seleccionado no está disponible.',
            'documentos.required' => 'Debes seleccionar al menos un documento.',
            'documentos.array' => 'La carga de documentos no tiene un formato válido.',
            'documentos.min' => 'Debes seleccionar al menos un documento.',
            'documentos.max' => 'La carga contiene más archivos de los permitidos.',
            'documentos.*.required' => 'Uno de los documentos seleccionados está vacío.',
            'documentos.*.file' => 'Uno de los documentos seleccionados no es un archivo válido.',
            'documentos.*.max' => 'Uno de los documentos supera el tamaño máximo permitido para su tipo.',
        ];
    }
}

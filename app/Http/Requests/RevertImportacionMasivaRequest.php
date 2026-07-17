<?php

namespace App\Http\Requests;

use App\Services\SwafiAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class RevertImportacionMasivaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(SwafiAuthorizationService::class)
            ->canCurrentUser('expedientes.revertir_importacion');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_reversion' => trim((string) $this->input('motivo_reversion', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo_reversion' => [
                'required',
                'string',
                'min:20',
                'max:500',
            ],
            'confirmar_reversion' => [
                'required',
                'accepted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_reversion.required' => 'Captura el motivo administrativo de la reversión.',
            'motivo_reversion.min' => 'El motivo de la reversión debe contener al menos 20 caracteres.',
            'motivo_reversion.max' => 'El motivo de la reversión no debe superar 500 caracteres.',
            'confirmar_reversion.accepted' => 'Debes confirmar que revisaste el alcance y las consecuencias de la reversión.',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Tu usuario no tiene permiso para revertir importaciones masivas.');
    }
}

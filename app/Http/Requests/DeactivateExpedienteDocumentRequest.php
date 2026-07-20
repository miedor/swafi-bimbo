<?php

namespace App\Http\Requests;

use App\Services\SwafiAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeactivateExpedienteDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = (int) (Auth::id() ?: $this->session()->get('swafi_user_id'));

        if ($userId <= 0) {
            return false;
        }

        $context = app(SwafiAuthorizationService::class)->contextForUser($userId);

        return $context['is_admin'] === true
            && in_array('documentos.eliminar', $context['permissions'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_baja' => trim((string) $this->input('motivo_baja', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo_baja' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_baja.required' => 'Debes indicar el motivo de la baja lógica del documento.',
            'motivo_baja.string' => 'El motivo de baja no tiene un formato válido.',
            'motivo_baja.min' => 'El motivo de baja debe contener al menos 10 caracteres.',
            'motivo_baja.max' => 'El motivo de baja no debe superar 500 caracteres.',
        ];
    }
}

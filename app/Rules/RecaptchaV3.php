<?php

namespace App\Rules;

use App\Services\SafeExceptionReporter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class RecaptchaV3 implements ValidationRule
{
    private string $expectedAction;

    public function __construct(string $expectedAction = 'login')
    {
        $this->expectedAction = $expectedAction;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secretKey = config('services.recaptcha.secret_key');
        $minScore = (float) config('services.recaptcha.min_score', 0.5);

        if (empty($secretKey)) {
            $fail('La configuración de reCAPTCHA no está disponible.');
            return;
        }

        if (empty($value)) {
            $fail('No se recibió el token de validación reCAPTCHA.');
            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secretKey,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            if (!$response->successful()) {
                $fail('No fue posible validar reCAPTCHA.');
                return;
            }

            $data = $response->json();

            $success = (bool) ($data['success'] ?? false);
            $score = (float) ($data['score'] ?? 0);
            $action = (string) ($data['action'] ?? '');

            if (!$success) {
                $fail('La validación reCAPTCHA no fue exitosa.');
                return;
            }

            if ($action !== $this->expectedAction) {
                $fail('La acción de reCAPTCHA no corresponde al formulario enviado.');
                return;
            }

            if ($score < $minScore) {
                $fail('La solicitud fue clasificada como riesgosa. Intenta nuevamente.');
                return;
            }
        } catch (\Throwable $exception) {
            app(SafeExceptionReporter::class)->warning($exception, 'recaptcha_validation', [
                'expected_action' => $this->expectedAction,
                'route_name' => request()->route()?->getName(),
            ]);

            $fail('Ocurrió un error al validar reCAPTCHA.');
        }
    }
}

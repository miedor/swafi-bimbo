<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

final class SafeExceptionReporter
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'contrasena',
        'contraseña',
        'passwd',
        'token',
        'secret',
        'authorization',
        'cookie',
        'session_id',
        'document',
        'documento',
        'archivo',
        'file_content',
        'contenido',
        'xml',
        'pdf',
        'request_body',
        'payload',
    ];

    /**
     * Registra un fallo técnico sin persistir el mensaje de la excepción,
     * trazas completas ni valores que puedan contener secretos o documentos.
     *
     * @param array<string, mixed> $context
     */
    public function warning(Throwable $exception, string $operation, array $context = []): string
    {
        $safeContext = $this->sanitizeContext($context);
        $safeContext['exception_type'] = $exception::class;
        $safeContext['exception_code'] = (string) $exception->getCode();
        $safeContext['exception_file'] = basename($exception->getFile());
        $safeContext['exception_line'] = $exception->getLine();
        $fingerprint = hash(
            'sha256',
            implode('|', [
                $exception::class,
                basename($exception->getFile()),
                (string) $exception->getLine(),
                (string) $exception->getCode(),
            ])
        );
        $safeContext['exception_fingerprint'] = $fingerprint;

        if (app()->bound('request')) {
            $requestId = request()->attributes->get('swafi_request_id');

            if (is_string($requestId) && trim($requestId) !== '') {
                $safeContext['swafi_request_id'] = trim($requestId);
            }
        }

        try {
            Log::warning(
                'SWAFI detectó un fallo técnico en una operación secundaria.',
                array_merge([
                    'operation' => $this->sanitizeString($operation, 120),
                ], $safeContext)
            );
        } catch (Throwable $loggingException) {
            error_log(sprintf(
                '[SWAFI] No fue posible escribir el aviso operacional. operation=%s logger_exception=%s',
                $this->sanitizeString($operation, 120),
                $loggingException::class
            ));
        }

        return substr($fingerprint, 0, 16);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context, int $depth = 0): array
    {
        if ($depth >= 4) {
            return ['context_truncated' => true];
        }

        $safe = [];

        foreach ($context as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);

            if ($normalizedKey === '' || $this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$normalizedKey] = $this->sanitizeContext($value, $depth + 1);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$normalizedKey] = $value;
                continue;
            }

            if (is_string($value)) {
                $safe[$normalizedKey] = $this->sanitizeString($value, 250);
                continue;
            }

            if ($value instanceof \Stringable) {
                $safe[$normalizedKey] = $this->sanitizeString((string) $value, 250);
                continue;
            }

            $safe[$normalizedKey] = get_debug_type($value);
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_áéíóúñ-]+/u', '_', $key) ?? '';

        return trim($key, '_-');
    }

    private function sanitizeString(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $maxLength);
    }
}

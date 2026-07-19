<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawRecipients = (string) $this->input('destinatarios_texto', '');
        $recipients = preg_split('/[\s,;]+/u', $rawRecipients, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $recipients = array_values(array_unique(array_map(
            static fn (string $email): string => mb_strtolower(trim($email)),
            $recipients
        )));

        $this->merge([
            'destinatarios' => $recipients,
            'activo' => $this->boolean('activo'),
            'dia_semana' => $this->filled('dia_semana') ? (int) $this->input('dia_semana') : null,
            'dia_mes' => $this->filled('dia_mes') ? (int) $this->input('dia_mes') : null,
            'zona_horaria' => $this->input(
                'zona_horaria',
                config('swafi.reportes_programados.zona_horaria', 'America/Mexico_City')
            ),
        ]);
    }

    public function rules(): array
    {
        $userId = (int) ($this->session()->get('swafi_user_id') ?: $this->user()?->id);

        return [
            'reporte_guardado_id' => [
                'required',
                'integer',
                Rule::exists('reportes_guardados', 'id')->where(
                    static fn ($query) => $query
                        ->where('user_id', $userId)
                        ->whereNull('deleted_at')
                ),
            ],
            'frecuencia' => ['required', Rule::in(['diaria', 'semanal', 'mensual'])],
            'dia_semana' => [
                'nullable',
                'integer',
                'between:1,7',
                'required_if:frecuencia,semanal',
                'prohibited_unless:frecuencia,semanal',
            ],
            'dia_mes' => [
                'nullable',
                'integer',
                'between:1,28',
                'required_if:frecuencia,mensual',
                'prohibited_unless:frecuencia,mensual',
            ],
            'hora_local' => ['required', 'date_format:H:i'],
            'zona_horaria' => ['required', 'string', 'max:64', 'timezone'],
            'formato' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'destinatarios_texto' => ['required', 'string', 'max:1500'],
            'destinatarios' => ['required', 'array', 'min:1', 'max:10'],
            'destinatarios.*' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $allowedDomains = config(
                        'swafi.reportes_programados.dominios_destinatarios_permitidos',
                        []
                    );

                    if (!is_array($allowedDomains) || $allowedDomains === []) {
                        return;
                    }

                    $separator = strrpos((string) $value, '@');
                    $domain = $separator === false
                        ? ''
                        : mb_strtolower(substr((string) $value, $separator + 1));

                    if (!in_array($domain, $allowedDomains, true)) {
                        $fail('El correo destinatario utiliza un dominio no autorizado para SWAFI.');
                    }
                },
            ],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reporte_guardado_id.exists' => 'La plantilla de reporte no existe, fue dada de baja o no pertenece a tu usuario.',
            'frecuencia.in' => 'Selecciona una frecuencia diaria, semanal o mensual.',
            'dia_semana.required_if' => 'Selecciona el día de la semana para la programación semanal.',
            'dia_semana.between' => 'El día de la semana seleccionado no es válido.',
            'dia_mes.required_if' => 'Captura el día del mes para la programación mensual.',
            'dia_mes.between' => 'El día del mes debe estar entre 1 y 28 para evitar fechas inexistentes.',
            'hora_local.required' => 'Captura la hora de generación del reporte.',
            'hora_local.date_format' => 'La hora debe utilizar el formato de 24 horas.',
            'zona_horaria.timezone' => 'La zona horaria seleccionada no es válida.',
            'formato.in' => 'Selecciona CSV, Excel o PDF como formato de entrega.',
            'destinatarios_texto.required' => 'Captura al menos un correo destinatario.',
            'destinatarios.min' => 'Captura al menos un correo destinatario.',
            'destinatarios.max' => 'Puedes registrar como máximo 10 destinatarios.',
            'destinatarios.*.email' => 'Uno de los correos destinatarios no tiene un formato válido.',
            'destinatarios.*.distinct' => 'No repitas correos en la lista de destinatarios.',
        ];
    }

    public function safeScheduleData(): array
    {
        return $this->safe()->only([
            'reporte_guardado_id',
            'frecuencia',
            'dia_semana',
            'dia_mes',
            'hora_local',
            'zona_horaria',
            'formato',
            'destinatarios',
            'activo',
        ]);
    }
}

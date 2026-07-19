<?php

namespace App\Jobs;

use App\Http\Controllers\ReportesController;
use App\Mail\SwafiScheduledReportMail;
use App\Models\ReporteGuardado;
use App\Models\ReporteProgramado;
use App\Models\ReporteProgramadoEjecucion;
use App\Models\User;
use App\Services\SafeExceptionReporter;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use App\Services\SwafiAuthorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class GenerateScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $executionId)
    {
    }

    public function handle(
        ReportesController $reports,
        SimpleXlsxExporter $xlsxExporter,
        SimplePdfTableExporter $pdfExporter,
        SwafiAuthorizationService $authorization,
        SafeExceptionReporter $safeExceptions
    ): void {
        $execution = ReporteProgramadoEjecucion::query()->findOrFail($this->executionId);

        if (in_array($execution->estado, ['completado', 'omitido'], true)) {
            return;
        }

        $schedule = ReporteProgramado::withTrashed()->find($execution->reporte_programado_id);

        if (!$schedule || $schedule->trashed() || !$schedule->activo) {
            $this->markSkipped($execution, $schedule, 'programacion_inactiva');
            return;
        }

        $savedReport = ReporteGuardado::withTrashed()->find($schedule->reporte_guardado_id);
        $owner = User::query()->find($schedule->user_id);

        if (!$savedReport || $savedReport->trashed() || !$owner || $owner->estatus !== 'activo') {
            $this->markSkipped($execution, $schedule, 'origen_no_disponible');
            return;
        }

        $context = $authorization->contextForUser((int) $owner->id);

        if (
            !$context['is_admin'] &&
            !in_array('reportes.programar', $context['permissions'], true)
        ) {
            $this->markSkipped($execution, $schedule, 'permiso_programacion_revocado');
            return;
        }

        if (!$this->markProcessing($execution)) {
            return;
        }

        $execution->refresh();

        try {
            $this->assertRealMailTransport();

            $artifact = $reports->generateScheduledExport(
                savedReport: $savedReport,
                owner: $owner,
                format: $schedule->formato,
                xlsxExporter: $xlsxExporter,
                pdfExporter: $pdfExporter
            );

            $storedRecipients = collect($schedule->destinatarios ?? [])
                ->map(static fn ($email): string => mb_strtolower(trim((string) $email)))
                ->filter()
                ->unique()
                ->take(10)
                ->values()
                ->all();
            $recipients = $this->validRecipients($storedRecipients);

            if ($recipients === []) {
                throw new RuntimeException('La programación no contiene destinatarios válidos.');
            }

            if (count($recipients) !== count($storedRecipients)) {
                throw new RuntimeException(
                    'La programación contiene destinatarios que ya no están autorizados.'
                );
            }

            $sentRecipients = collect($execution->fresh()->destinatarios_enviados ?? [])
                ->map(static fn ($email): string => mb_strtolower(trim((string) $email)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            foreach ($recipients as $recipient) {
                if (in_array($recipient, $sentRecipients, true)) {
                    continue;
                }

                Mail::to($recipient)->send(new SwafiScheduledReportMail(
                    reportName: $savedReport->nombre,
                    reportType: $artifact['report_label'],
                    generatedAt: now()
                        ->timezone($schedule->zona_horaria)
                        ->format('d/m/Y H:i'),
                    rowCount: $artifact['row_count'],
                    fileName: $artifact['file_name'],
                    mimeType: $artifact['mime_type'],
                    contents: $artifact['contents']
                ));

                $sentRecipients[] = $recipient;
                $execution->forceFill([
                    'destinatarios_enviados' => array_values($sentRecipients),
                ])->save();
            }

            $checksum = hash('sha256', $artifact['contents']);
            $finishedAt = now();

            DB::transaction(function () use (
                $execution,
                $schedule,
                $artifact,
                $sentRecipients,
                $checksum,
                $finishedAt
            ): void {
                $execution->forceFill([
                    'estado' => 'completado',
                    'finished_at' => $finishedAt,
                    'total_registros' => $artifact['row_count'],
                    'destinatarios_total' => count($sentRecipients),
                    'destinatarios_enviados' => array_values($sentRecipients),
                    'archivo_nombre' => $artifact['file_name'],
                    'archivo_sha256' => $checksum,
                    'error_referencia' => null,
                ])->save();

                $schedule->forceFill([
                    'ultima_ejecucion_at' => $finishedAt,
                    'ultimo_estado' => 'completado',
                    'ultimo_error_referencia' => null,
                ])->save();
            }, 3);

            $this->audit('REPORTE_PROGRAMADO_ENVIADO', $schedule, [
                'ejecucion_id' => $execution->id,
                'tipo_reporte' => $savedReport->tipo_reporte,
                'formato' => $schedule->formato,
                'total_registros' => $artifact['row_count'],
                'destinatarios_total' => count($sentRecipients),
                'archivo_sha256' => $checksum,
            ], $safeExceptions);
        } catch (Throwable $exception) {
            $reference = $this->exceptionReference($exception);
            $failedAt = now();

            DB::transaction(function () use ($execution, $schedule, $reference, $failedAt): void {
                $execution->forceFill([
                    'estado' => 'fallido',
                    'finished_at' => $failedAt,
                    'error_referencia' => $reference,
                ])->save();

                $schedule->forceFill([
                    'ultima_ejecucion_at' => $failedAt,
                    'ultimo_estado' => 'fallido',
                    'ultimo_error_referencia' => $reference,
                ])->save();
            }, 3);

            $safeExceptions->warning(
                $exception,
                'scheduled_report_generation',
                [
                    'execution_id' => $execution->id,
                    'schedule_id' => $schedule->id,
                    'user_id' => $schedule->user_id,
                    'format' => $schedule->formato,
                    'error_reference' => $reference,
                ]
            );

            $this->audit('REPORTE_PROGRAMADO_FALLIDO', $schedule, [
                'ejecucion_id' => $execution->id,
                'error_referencia' => $reference,
            ], $safeExceptions);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $reference = $this->exceptionReference($exception);
        $execution = ReporteProgramadoEjecucion::query()->find($this->executionId);

        if (!$execution || $execution->estado === 'completado') {
            return;
        }

        $execution->forceFill([
            'estado' => 'fallido',
            'finished_at' => now(),
            'error_referencia' => $reference,
        ])->save();

        ReporteProgramado::withTrashed()
            ->whereKey($execution->reporte_programado_id)
            ->update([
                'ultimo_estado' => 'fallido',
                'ultimo_error_referencia' => $reference,
                'ultima_ejecucion_at' => now(),
                'updated_at' => now(),
            ]);

        app(SafeExceptionReporter::class)->warning(
            $exception,
            'scheduled_report_job_exhausted',
            [
                'execution_id' => $execution->id,
                'schedule_id' => $execution->reporte_programado_id,
                'error_reference' => $reference,
            ]
        );
    }

    private function markProcessing(ReporteProgramadoEjecucion $execution): bool
    {
        return DB::transaction(function () use ($execution): bool {
            $locked = ReporteProgramadoEjecucion::query()
                ->whereKey($execution->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->estado, ['completado', 'omitido'], true)) {
                return false;
            }

            if (
                $locked->estado === 'procesando' &&
                $locked->started_at &&
                $locked->started_at->greaterThan(now()->subMinutes(10))
            ) {
                return false;
            }

            $locked->forceFill([
                'estado' => 'procesando',
                'started_at' => $locked->started_at ?: now(),
                'finished_at' => null,
                'error_referencia' => null,
            ])->save();

            return true;
        }, 3);
    }

    private function markSkipped(
        ReporteProgramadoEjecucion $execution,
        ?ReporteProgramado $schedule,
        string $reason
    ): void {
        $reference = hash('sha256', 'scheduled-report-skipped|' . $reason);

        $execution->forceFill([
            'estado' => 'omitido',
            'finished_at' => now(),
            'error_referencia' => $reference,
        ])->save();

        if ($schedule) {
            $schedule->forceFill([
                'ultima_ejecucion_at' => now(),
                'ultimo_estado' => 'omitido',
                'ultimo_error_referencia' => $reference,
            ])->save();
        }
    }

    /**
     * @param array<int, mixed> $recipients
     * @return array<int, string>
     */
    private function validRecipients(array $recipients): array
    {
        $allowedDomains = config(
            'swafi.reportes_programados.dominios_destinatarios_permitidos',
            []
        );

        return collect($recipients)
            ->map(static fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter(static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->filter(function (string $email) use ($allowedDomains): bool {
                if (!is_array($allowedDomains) || $allowedDomains === []) {
                    return true;
                }

                $separator = strrpos($email, '@');
                $domain = $separator === false ? '' : substr($email, $separator + 1);

                return in_array($domain, $allowedDomains, true);
            })
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    private function assertRealMailTransport(): void
    {
        $mailer = strtolower(trim((string) config('mail.default', 'log')));

        if (in_array($mailer, ['log', 'array', 'failover'], true)) {
            throw new RuntimeException(
                'El transporte de correo configurado no entrega mensajes reales.'
            );
        }
    }

    private function exceptionReference(Throwable $exception): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            basename($exception->getFile()),
            (string) $exception->getLine(),
            (string) $exception->getCode(),
        ]));
    }

    /**
     * @param array<string, mixed> $after
     */
    private function audit(
        string $action,
        ReporteProgramado $schedule,
        array $after,
        SafeExceptionReporter $safeExceptions
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $schedule->user_id,
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $action,
                'tabla_afectada' => 'reportes_programados_ejecuciones',
                'registro_clave' => (string) ($after['ejecucion_id'] ?? $schedule->id),
                'antes' => null,
                'despues' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $safeExceptions->warning(
                $exception,
                'scheduled_report_execution_audit',
                [
                    'action' => $action,
                    'schedule_id' => $schedule->id,
                    'user_id' => $schedule->user_id,
                ]
            );
        }
    }
}

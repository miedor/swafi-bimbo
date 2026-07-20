<?php

namespace App\Services;

use App\Mail\SwafiObservacionRecordatorioMail;
use App\Models\ExpedienteObservacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class ObservationReminderService
{
    /**
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'falta_pdf' => 'Falta PDF',
        'falta_xml' => 'Falta XML',
        'falta_valores' => 'Falta valores fiscales/financieros',
        'falta_ubicacion' => 'Falta ubicación física',
        'ubicacion_incorrecta' => 'Ubicación incorrecta',
        'datos_inconsistentes' => 'Datos inconsistentes',
        'documento_incorrecto' => 'Documento incorrecto',
        'otro' => 'Otro seguimiento',
    ];

    /**
     * @var array<string, string>
     */
    private const PRIORITY_LABELS = [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'critica' => 'Crítica',
    ];

    public function __construct(
        private readonly ObservationDeadlineService $deadlines,
        private readonly SafeExceptionReporter $safeExceptions
    ) {
    }

    /**
     * @return array{procesadas:int,enviadas:int,fallidas:int,omitidas:int}
     */
    public function dispatchDue(?int $limit = null): array
    {
        $timezone = (string) config(
            'swafi.observaciones_recordatorios.zona_horaria',
            'America/Mexico_City'
        );
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $dueSoonDays = $this->dueSoonDays();
        $batchLimit = min(
            100,
            max(
                1,
                $limit ?? (int) config('swafi.observaciones_recordatorios.limite_lote', 50)
            )
        );
        $dayBoundaryUtc = $today->utc();

        $ids = ExpedienteObservacion::query()
            ->whereIn('estatus', ObservationDeadlineService::REMINDER_STATUSES)
            ->whereNotNull('fecha_compromiso')
            ->where('fecha_compromiso', '<=', $today->addDays($dueSoonDays)->toDateString())
            ->where(function ($query) use ($dayBoundaryUtc): void {
                $query->whereNull('ultimo_intento_recordatorio_at')
                    ->orWhere('ultimo_intento_recordatorio_at', '<', $dayBoundaryUtc);
            })
            ->orderBy('fecha_compromiso')
            ->orderByRaw("FIELD(prioridad, 'critica', 'alta', 'media', 'baja')")
            ->orderBy('id')
            ->limit($batchLimit)
            ->pluck('id');

        $summary = [
            'procesadas' => 0,
            'enviadas' => 0,
            'fallidas' => 0,
            'omitidas' => 0,
        ];

        foreach ($ids as $id) {
            $result = $this->processOne((int) $id, $today, $dueSoonDays, $dayBoundaryUtc);
            $summary['procesadas']++;
            $summary[$result]++;
        }

        return $summary;
    }

    private function processOne(
        int $observationId,
        CarbonImmutable $today,
        int $dueSoonDays,
        CarbonImmutable $dayBoundaryUtc
    ): string {
        $observation = DB::transaction(function () use (
            $observationId,
            $today,
            $dueSoonDays,
            $dayBoundaryUtc
        ): ?ExpedienteObservacion {
            $locked = ExpedienteObservacion::query()
                ->lockForUpdate()
                ->find($observationId);

            if (!$locked) {
                return null;
            }

            if (!in_array($locked->estatus, ObservationDeadlineService::REMINDER_STATUSES, true)) {
                return null;
            }

            if (!$this->deadlines->isReminderEligible(
                $locked->fecha_compromiso,
                $locked->estatus,
                $today,
                $dueSoonDays
            )) {
                return null;
            }

            if (
                $locked->ultimo_intento_recordatorio_at !== null
                && $locked->ultimo_intento_recordatorio_at->greaterThanOrEqualTo($dayBoundaryUtc)
            ) {
                return null;
            }

            $locked->forceFill([
                'ultimo_intento_recordatorio_at' => now(),
                'recordatorio_error_referencia' => null,
            ])->save();

            return $locked->fresh();
        }, 3);

        if (!$observation) {
            return 'omitidas';
        }

        try {
            $this->assertRealMailTransport();
            $recipient = $this->resolveRecipient($observation);
            $context = $this->mailContext($observation);
            $daysRemaining = $this->deadlines->daysRemaining(
                $observation->fecha_compromiso,
                $today
            );
            $deadlineState = $this->deadlines->state(
                $observation->fecha_compromiso,
                $observation->estatus,
                $today,
                $dueSoonDays
            );
            $deadlineLabel = $this->deadlines->label($deadlineState, $daysRemaining);

            Mail::to($recipient->email)->send(new SwafiObservacionRecordatorioMail(
                assignedName: $recipient->name ?: $recipient->usuario,
                numeroActivo: $observation->numero_activo,
                folioFactura: $context->folio_factura ?: 'Sin folio registrado',
                tipoObservacion: self::TYPE_LABELS[$observation->tipo_observacion]
                    ?? $observation->tipo_observacion,
                prioridad: self::PRIORITY_LABELS[$observation->prioridad]
                    ?? ucfirst((string) $observation->prioridad),
                descripcion: $observation->descripcion,
                fechaCompromiso: CarbonImmutable::parse($observation->fecha_compromiso)
                    ->format('d/m/Y'),
                estadoPlazo: $deadlineLabel,
                urlExpediente: route('expediente', [
                    'expediente' => $observation->expediente_id,
                    'tab' => 'observaciones',
                ])
            ));

            $sentAt = now();

            DB::transaction(function () use ($observation, $sentAt): void {
                $locked = ExpedienteObservacion::query()
                    ->lockForUpdate()
                    ->find($observation->id);

                if (!$locked) {
                    return;
                }

                $locked->forceFill([
                    'fecha_ultimo_recordatorio' => $sentAt,
                    'recordatorios_enviados' => min(
                        65535,
                        ((int) $locked->recordatorios_enviados) + 1
                    ),
                    'recordatorio_error_referencia' => null,
                ])->save();
            }, 3);

            $this->safeAudit(
                observation: $observation,
                action: 'RECORDATORIO_OBS_ENVIADO',
                after: [
                    'assigned_user_id' => $recipient->id,
                    'fecha_compromiso' => (string) $observation->fecha_compromiso,
                    'estado_plazo' => $deadlineState,
                    'recordatorio_numero' => ((int) $observation->recordatorios_enviados) + 1,
                ]
            );

            return 'enviadas';
        } catch (Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'observation_reminder_send',
                [
                    'observation_id' => $observation->id,
                    'assigned_user_id' => $observation->asignado_a,
                    'deadline' => (string) $observation->fecha_compromiso,
                ]
            );

            ExpedienteObservacion::query()
                ->whereKey($observation->id)
                ->update([
                    'recordatorio_error_referencia' => $reference,
                    'updated_at' => now(),
                ]);

            $this->safeAudit(
                observation: $observation,
                action: 'RECORDATORIO_OBS_FALLIDO',
                after: [
                    'assigned_user_id' => $observation->asignado_a,
                    'fecha_compromiso' => (string) $observation->fecha_compromiso,
                    'referencia' => $reference,
                ]
            );

            return 'fallidas';
        }
    }

    private function resolveRecipient(ExpedienteObservacion $observation): object
    {
        $recipient = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('u.id', $observation->asignado_a)
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->where('p.activo', 1)
            ->where('r.nombre', $observation->rol_destino)
            ->where('p.clave', 'observaciones.atender')
            ->select(['u.id', 'u.usuario', 'u.name', 'u.email'])
            ->distinct()
            ->first();

        if (!$recipient || !filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'La observación no tiene un usuario responsable activo, autorizado y con correo válido.'
            );
        }

        return $recipient;
    }

    private function mailContext(ExpedienteObservacion $observation): object
    {
        $context = DB::table('expedientes')
            ->where('id', $observation->expediente_id)
            ->whereNull('deleted_at')
            ->first(['id', 'folio_factura']);

        if (!$context) {
            throw new RuntimeException('El expediente asociado a la observación no está disponible.');
        }

        return $context;
    }

    private function dueSoonDays(): int
    {
        return min(
            30,
            max(
                0,
                (int) config('swafi.observaciones_recordatorios.dias_anticipacion', 2)
            )
        );
    }

    private function assertRealMailTransport(): void
    {
        $mailer = strtolower(trim((string) config('mail.default', 'log')));

        if (in_array($mailer, ['log', 'array'], true)) {
            throw new RuntimeException(
                'El transporte de correo configurado no entrega mensajes reales.'
            );
        }
    }

    /**
     * @param array<string, mixed> $after
     */
    private function safeAudit(
        ExpedienteObservacion $observation,
        string $action,
        array $after
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $observation->numero_activo,
                'user_id' => null,
                'modulo' => 'M01 Gestión de expedientes de activo fijo',
                'accion' => $action,
                'tabla_afectada' => 'expediente_observaciones',
                'registro_clave' => (string) $observation->id,
                'antes' => null,
                'despues' => json_encode(
                    $after,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->safeExceptions->warning(
                $exception,
                'observation_reminder_audit_write',
                [
                    'observation_id' => $observation->id,
                    'action' => $action,
                ]
            );
        }
    }
}

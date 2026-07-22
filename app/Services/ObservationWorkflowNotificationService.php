<?php

namespace App\Services;

use App\Mail\SwafiObservacionAtendidaMail;
use App\Mail\SwafiObservacionResolucionMail;
use App\Models\ExpedienteObservacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ObservationWorkflowNotificationService
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

    /**
     * Notifica a la persona que creó la observación que la corrección está lista
     * para aceptación o rechazo. La atención permanece guardada aunque el correo
     * no pueda entregarse.
     *
     * @return array{sent:bool,message:string,recipient_name:string,recipient_email:string}
     */
    public function notifyCreatorForValidation(
        ExpedienteObservacion $observation,
        ?int $triggeredBy
    ): array {
        $observation = ExpedienteObservacion::query()
            ->whereKey($observation->getKey())
            ->firstOrFail();

        $observation->update([
            'fecha_notificacion_revision' => null,
            'ultimo_intento_notificacion_revision_at' => now(),
            'notificacion_revision_intentos' => ((int) $observation->notificacion_revision_intentos) + 1,
            'notificacion_revision_error_referencia' => null,
        ]);

        try {
            if ($observation->estatus !== 'atendida') {
                throw ValidationException::withMessages([
                    'observacion' => 'Solo una observación atendida puede notificarse para validación.',
                ]);
            }

            $recipient = $this->resolveValidationRecipient($observation);
            $context = $this->mailContext($observation);

            $this->assertRealMailTransport();

            Mail::to($recipient->email)->send(new SwafiObservacionAtendidaMail(
                reviewerName: $recipient->name ?: $recipient->usuario ?: $recipient->email,
                attendedBy: $context['attended_by'],
                numeroActivo: $observation->numero_activo,
                folioFactura: $context['invoice_folio'],
                tipoObservacion: self::TYPE_LABELS[$observation->tipo_observacion]
                    ?? $observation->tipo_observacion,
                prioridad: self::PRIORITY_LABELS[$observation->prioridad]
                    ?? $observation->prioridad,
                descripcion: $observation->descripcion,
                respuestaAtencion: (string) $observation->respuesta_atencion,
                fechaAtencion: $this->formatDateTime($observation->fecha_atencion),
                urlExpediente: $this->observationUrl($observation)
            ));

            $observation->update([
                'fecha_notificacion_revision' => now(),
                'notificacion_revision_error_referencia' => null,
            ]);

            $this->safeAudit(
                observation: $observation,
                userId: $triggeredBy,
                action: 'NOTIF_OBS_REVISION_ENVIADA',
                after: [
                    'destinatario_id' => $recipient->id,
                    'destinatario' => $recipient->email,
                    'intento' => (int) $observation->fresh()->notificacion_revision_intentos,
                    'fecha_notificacion' => now()->toDateTimeString(),
                ]
            );

            return [
                'sent' => true,
                'message' => 'Se notificó a Consulta / Auditoría para que valide la corrección.',
                'recipient_name' => (string) ($recipient->name ?: $recipient->usuario),
                'recipient_email' => (string) $recipient->email,
            ];
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionReporter::class)->warning(
                $exception,
                'observation_validation_notification_send',
                [
                    'observation_id' => $observation->id,
                    'creator_user_id' => $observation->creado_por,
                    'triggered_by' => $triggeredBy,
                ]
            );

            $observation->update([
                'notificacion_revision_error_referencia' => $reference,
            ]);

            $this->safeAudit(
                observation: $observation,
                userId: $triggeredBy,
                action: 'NOTIF_OBS_REVISION_FALLIDA',
                after: [
                    'referencia' => $reference,
                    'intento' => (int) $observation->fresh()->notificacion_revision_intentos,
                ]
            );

            return [
                'sent' => false,
                'message' => "La observación quedó atendida y visible en el Dashboard de validación, pero el correo no pudo enviarse. Referencia: {$reference}.",
                'recipient_name' => '',
                'recipient_email' => '',
            ];
        }
    }

    /**
     * Notifica a la persona asignada si la corrección fue cerrada o rechazada.
     * En caso de rechazo, el vínculo permite retomar la atención de inmediato.
     *
     * @return array{sent:bool,message:string,recipient_name:string,recipient_email:string}
     */
    public function notifyAssigneeOfResolution(
        ExpedienteObservacion $observation,
        ?int $triggeredBy
    ): array {
        $observation = ExpedienteObservacion::query()
            ->whereKey($observation->getKey())
            ->firstOrFail();

        $observation->update([
            'fecha_notificacion_resolucion' => null,
            'ultimo_intento_notificacion_resolucion_at' => now(),
            'notificacion_resolucion_intentos' => ((int) $observation->notificacion_resolucion_intentos) + 1,
            'notificacion_resolucion_error_referencia' => null,
        ]);

        try {
            if (!in_array($observation->estatus, ['cerrada', 'rechazada'], true)) {
                throw ValidationException::withMessages([
                    'observacion' => 'Solo una observación cerrada o rechazada puede notificar su resolución.',
                ]);
            }

            $recipient = $this->resolveAssignedRecipient($observation);
            $context = $this->mailContext($observation);
            $decision = $observation->estatus === 'cerrada' ? 'Cerrada' : 'Rechazada';

            $this->assertRealMailTransport();

            Mail::to($recipient->email)->send(new SwafiObservacionResolucionMail(
                assignedName: $recipient->name ?: $recipient->usuario ?: $recipient->email,
                validatedBy: $context['validated_by'],
                decision: $decision,
                numeroActivo: $observation->numero_activo,
                folioFactura: $context['invoice_folio'],
                tipoObservacion: self::TYPE_LABELS[$observation->tipo_observacion]
                    ?? $observation->tipo_observacion,
                descripcion: $observation->descripcion,
                respuestaAtencion: (string) $observation->respuesta_atencion,
                comentarioValidacion: (string) $observation->comentario_validacion,
                urlExpediente: $this->observationUrl($observation)
            ));

            $observation->update([
                'fecha_notificacion_resolucion' => now(),
                'notificacion_resolucion_error_referencia' => null,
            ]);

            $this->safeAudit(
                observation: $observation,
                userId: $triggeredBy,
                action: 'NOTIF_OBS_RESOLUCION_ENVIADA',
                after: [
                    'destinatario_id' => $recipient->id,
                    'destinatario' => $recipient->email,
                    'decision' => $observation->estatus,
                    'intento' => (int) $observation->fresh()->notificacion_resolucion_intentos,
                    'fecha_notificacion' => now()->toDateTimeString(),
                ]
            );

            return [
                'sent' => true,
                'message' => $observation->estatus === 'cerrada'
                    ? 'Se notificó al usuario asignado que la observación fue cerrada.'
                    : 'Se notificó al usuario asignado que la corrección fue rechazada y debe atenderse nuevamente.',
                'recipient_name' => (string) ($recipient->name ?: $recipient->usuario),
                'recipient_email' => (string) $recipient->email,
            ];
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionReporter::class)->warning(
                $exception,
                'observation_resolution_notification_send',
                [
                    'observation_id' => $observation->id,
                    'assigned_user_id' => $observation->asignado_a,
                    'triggered_by' => $triggeredBy,
                    'status' => $observation->estatus,
                ]
            );

            $observation->update([
                'notificacion_resolucion_error_referencia' => $reference,
            ]);

            $this->safeAudit(
                observation: $observation,
                userId: $triggeredBy,
                action: 'NOTIF_OBS_RESOLUCION_FALLIDA',
                after: [
                    'decision' => $observation->estatus,
                    'referencia' => $reference,
                    'intento' => (int) $observation->fresh()->notificacion_resolucion_intentos,
                ]
            );

            return [
                'sent' => false,
                'message' => "La resolución quedó registrada, pero el correo al usuario asignado no pudo enviarse. Referencia: {$reference}.",
                'recipient_name' => '',
                'recipient_email' => '',
            ];
        }
    }

    private function resolveValidationRecipient(ExpedienteObservacion $observation): User
    {
        $creatorId = (int) ($observation->creado_por ?? 0);

        if ($creatorId > 0 && $this->userCan($creatorId, 'observaciones.validar')) {
            $creator = User::query()->find($creatorId);

            if ($this->hasValidActiveEmail($creator)) {
                return $creator;
            }
        }

        $fallbackId = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->where('p.activo', 1)
            ->where('r.nombre', 'Administrador SWAFI')
            ->where('p.clave', 'observaciones.validar')
            ->orderBy('u.id')
            ->value('u.id');

        $fallback = $fallbackId ? User::query()->find((int) $fallbackId) : null;

        if (!$this->hasValidActiveEmail($fallback)) {
            throw new RuntimeException(
                'La persona creadora no está disponible y no existe un Administrador SWAFI activo con correo válido para supervisar la validación.'
            );
        }

        return $fallback;
    }

    private function resolveAssignedRecipient(ExpedienteObservacion $observation): User
    {
        $assignedId = (int) ($observation->asignado_a ?? 0);
        $assigned = $assignedId > 0 ? User::query()->find($assignedId) : null;

        if (
            $assignedId <= 0
            || !$this->userCan($assignedId, 'observaciones.atender')
            || !$this->hasValidActiveEmail($assigned)
        ) {
            throw new RuntimeException(
                'La observación no tiene un usuario asignado activo, autorizado y con correo válido para recibir la resolución.'
            );
        }

        return $assigned;
    }

    private function userCan(int $userId, string $permission): bool
    {
        return DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('u.id', $userId)
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->where('p.activo', 1)
            ->where('p.clave', $permission)
            ->exists();
    }

    private function hasValidActiveEmail(?User $user): bool
    {
        return $user !== null
            && $user->estatus === 'activo'
            && filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return array{invoice_folio:string,attended_by:string,validated_by:string}
     */
    private function mailContext(ExpedienteObservacion $observation): array
    {
        $row = DB::table('expediente_observaciones as o')
            ->join('expedientes as e', 'e.id', '=', 'o.expediente_id')
            ->leftJoin('users as ua', 'ua.id', '=', 'o.atendido_por')
            ->leftJoin('users as uv', 'uv.id', '=', 'o.validado_por')
            ->where('o.id', $observation->id)
            ->whereNull('e.deleted_at')
            ->select([
                'e.folio_factura',
                'ua.name as attended_name',
                'ua.usuario as attended_user',
                'ua.email as attended_email',
                'uv.name as validated_name',
                'uv.usuario as validated_user',
                'uv.email as validated_email',
            ])
            ->first();

        if (!$row) {
            throw new RuntimeException(
                'No fue posible recuperar el expediente y las personas participantes para generar la notificación.'
            );
        }

        return [
            'invoice_folio' => trim((string) ($row->folio_factura ?: 'Sin folio registrado')),
            'attended_by' => $this->displayName(
                $row->attended_name,
                $row->attended_user,
                $row->attended_email,
                'Usuario responsable'
            ),
            'validated_by' => $this->displayName(
                $row->validated_name,
                $row->validated_user,
                $row->validated_email,
                'Consulta / Auditoría'
            ),
        ];
    }

    private function displayName(
        ?string $name,
        ?string $username,
        ?string $email,
        string $fallback
    ): string {
        foreach ([$name, $username, $email] as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function observationUrl(ExpedienteObservacion $observation): string
    {
        return route('expediente', [
            'expediente' => $observation->expediente_id,
            'tab' => 'observaciones',
        ]).'#observacion-'.$observation->id;
    }

    private function formatDateTime(mixed $value): string
    {
        if (!$value) {
            return now()->format('d/m/Y H:i');
        }

        return Carbon::parse($value)->format('d/m/Y H:i');
    }

    private function assertRealMailTransport(): void
    {
        $mailer = strtolower(trim((string) config('mail.default', 'log')));

        if (in_array($mailer, ['log', 'array', 'failover'], true)) {
            throw new RuntimeException(
                'MAIL_MAILER='.$mailer.' no entrega correos reales. Configura SMTP, SES, Postmark o Resend en Laravel Cloud.'
            );
        }
    }

    /**
     * @param array<string, mixed> $after
     */
    private function safeAudit(
        ExpedienteObservacion $observation,
        ?int $userId,
        string $action,
        array $after
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $observation->numero_activo,
                'user_id' => $userId,
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $action,
                'tabla_afectada' => 'expediente_observaciones',
                'registro_clave' => (string) $observation->id,
                'antes' => null,
                'despues' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => app()->bound('request') ? request()->ip() : null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'observation_workflow_notification_audit_write',
                [
                    'observation_id' => $observation->id,
                    'user_id' => $userId,
                    'action' => $action,
                ]
            );
        }
    }
}

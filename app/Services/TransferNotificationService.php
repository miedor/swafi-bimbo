<?php

namespace App\Services;

use App\Mail\SwafiResolucionTrasladoMail;
use App\Mail\SwafiSolicitudTrasladoMail;
use App\Models\SolicitudTraslado;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TransferNotificationService
{
    /**
     * @return array{sent:bool,message:string,recipient_name:string,recipient_email:string}
     */
    public function sendAssignment(SolicitudTraslado $transferRequest, ?int $triggeredBy): array
    {
        $request = SolicitudTraslado::query()
            ->whereKey($transferRequest->getKey())
            ->firstOrFail();

        if ($request->estatus !== 'pendiente') {
            throw ValidationException::withMessages([
                'notificacion' => 'Solo se pueden notificar solicitudes de traslado pendientes.',
            ]);
        }

        $recipient = $this->resolveAssignedApprover($request);
        $context = $this->mailContext($request);

        $request->update([
            'ultimo_intento_notificacion_at' => now(),
            'notificacion_aprobador_intentos' => ((int) $request->notificacion_aprobador_intentos) + 1,
        ]);

        try {
            $this->assertRealMailTransport();

            Mail::to($recipient->email)->send(new SwafiSolicitudTrasladoMail(
                approverName: $recipient->name ?: $recipient->email,
                requestedBy: $context['requested_by'],
                requestUuid: $request->uuid,
                numeroActivo: $request->numero_activo,
                descripcionActivo: $context['asset_description'],
                originLocation: $context['origin_location'],
                destinationLocation: $context['destination_location'],
                movementDate: Carbon::parse($request->fecha_movimiento)->format('d/m/Y H:i'),
                reason: $request->motivo,
                destinationResponsible: $context['destination_responsible'],
                reviewUrl: $this->reviewUrl($request)
            ));

            $sentAt = now();

            $request->update([
                'notificacion_aprobador_at' => $sentAt,
                'notificacion_aprobador_error' => null,
            ]);

            $this->safeAudit(
                request: $request,
                userId: $triggeredBy,
                action: 'NOTIF_TRASLADO_ENVIADA',
                after: [
                    'tipo_notificacion' => 'solicitud_pendiente',
                    'aprobador_asignado_id' => $recipient->id,
                    'destinatario' => $recipient->email,
                    'intento' => (int) $request->fresh()->notificacion_aprobador_intentos,
                    'fecha_notificacion' => $sentAt->toDateTimeString(),
                ]
            );

            return [
                'sent' => true,
                'message' => 'Se envió el correo de solicitud pendiente a '.$recipient->name.' ('.$recipient->email.').',
                'recipient_name' => (string) $recipient->name,
                'recipient_email' => (string) $recipient->email,
            ];
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionReporter::class)->warning(
                $exception,
                'transfer_approver_notification_send',
                [
                    'transfer_request_id' => $request->id,
                    'approver_user_id' => $recipient->id,
                    'triggered_by' => $triggeredBy,
                ]
            );
            $safeError = "No fue posible enviar la notificación. Referencia: {$reference}.";

            $request->update([
                'notificacion_aprobador_error' => $safeError,
            ]);

            $this->safeAudit(
                request: $request,
                userId: $triggeredBy,
                action: 'NOTIF_TRASLADO_FALLIDA',
                after: [
                    'tipo_notificacion' => 'solicitud_pendiente',
                    'aprobador_asignado_id' => $recipient->id,
                    'referencia' => $reference,
                    'intento' => (int) $request->fresh()->notificacion_aprobador_intentos,
                ]
            );

            return [
                'sent' => false,
                'message' => 'La solicitud se guardó y quedó asignada a '.$recipient->name
                    .", pero el correo no pudo enviarse. Referencia: {$reference}.",
                'recipient_name' => (string) $recipient->name,
                'recipient_email' => (string) $recipient->email,
            ];
        }
    }

    /**
     * Envía al Usuario Planta / Inventarios solicitante el resultado de la
     * aprobación o rechazo. El traslado permanece resuelto aunque el proveedor
     * de correo falle; la incidencia queda registrada y puede reenviarse.
     *
     * @return array{sent:bool,message:string,recipient_name:string,recipient_email:string}
     */
    public function sendResolution(SolicitudTraslado $transferRequest, ?int $triggeredBy): array
    {
        $request = SolicitudTraslado::query()
            ->whereKey($transferRequest->getKey())
            ->firstOrFail();

        if (!in_array($request->estatus, ['aprobado', 'rechazado'], true)) {
            throw ValidationException::withMessages([
                'notificacion' => 'El resultado solo puede notificarse después de aprobar o rechazar la solicitud.',
            ]);
        }

        $request->update([
            'ultimo_intento_notificacion_solicitante_at' => now(),
            'notificacion_solicitante_intentos' => ((int) $request->notificacion_solicitante_intentos) + 1,
        ]);

        $recipientName = 'Usuario solicitante';
        $recipientEmail = '';

        try {
            $recipient = $this->resolveRequester($request);
            $recipientName = (string) ($recipient->name ?: $recipient->email);
            $recipientEmail = (string) $recipient->email;
            $context = $this->mailContext($request);
            $statusLabel = $request->estatus === 'aprobado' ? 'Aprobado' : 'Rechazado';

            $this->assertRealMailTransport();

            Mail::to($recipient->email)->send(new SwafiResolucionTrasladoMail(
                requesterName: $recipientName,
                requestUuid: $request->uuid,
                numeroActivo: $request->numero_activo,
                descripcionActivo: $context['asset_description'],
                status: $request->estatus,
                statusLabel: $statusLabel,
                originLocation: $context['origin_location'],
                destinationLocation: $context['destination_location'],
                resolvedBy: $context['resolved_by'],
                resolvedAt: Carbon::parse($request->resuelto_at ?: now())->format('d/m/Y H:i'),
                resolutionComment: trim((string) ($request->comentario_resolucion ?: 'Sin comentario adicional.')),
                movementApplied: $request->estatus === 'aprobado',
                reviewUrl: $this->reviewUrl($request)
            ));

            $sentAt = now();

            $request->update([
                'notificacion_solicitante_at' => $sentAt,
                'notificacion_solicitante_error' => null,
            ]);

            $this->safeAudit(
                request: $request,
                userId: $triggeredBy,
                action: 'NOTIF_RESOL_TRASLADO_ENVIADA',
                after: [
                    'tipo_notificacion' => 'resultado_'.$request->estatus,
                    'solicitado_por' => $recipient->id,
                    'destinatario' => $recipient->email,
                    'intento' => (int) $request->fresh()->notificacion_solicitante_intentos,
                    'fecha_notificacion' => $sentAt->toDateTimeString(),
                ]
            );

            return [
                'sent' => true,
                'message' => 'Se notificó por correo el resultado a '.$recipientName.' ('.$recipientEmail.').',
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
            ];
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionReporter::class)->warning(
                $exception,
                'transfer_requester_resolution_notification_send',
                [
                    'transfer_request_id' => $request->id,
                    'requester_user_id' => $request->solicitado_por,
                    'triggered_by' => $triggeredBy,
                    'status' => $request->estatus,
                ]
            );
            $safeError = "No fue posible enviar la resolución al solicitante. Referencia: {$reference}.";

            $request->update([
                'notificacion_solicitante_error' => $safeError,
            ]);

            $this->safeAudit(
                request: $request,
                userId: $triggeredBy,
                action: 'NOTIF_RESOL_TRASLADO_FALLIDA',
                after: [
                    'tipo_notificacion' => 'resultado_'.$request->estatus,
                    'solicitado_por' => $request->solicitado_por,
                    'referencia' => $reference,
                    'intento' => (int) $request->fresh()->notificacion_solicitante_intentos,
                ]
            );

            return [
                'sent' => false,
                'message' => 'La solicitud quedó '.$request->estatus
                    .", pero el correo de resultado no pudo enviarse. Referencia: {$reference}. Puede reenviarse desde la bandeja de traslados.",
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
            ];
        }
    }

    private function resolveAssignedApprover(SolicitudTraslado $request): User
    {
        $approverId = (int) ($request->aprobador_asignado_id ?? 0);

        if ($approverId <= 0) {
            throw ValidationException::withMessages([
                'aprobador_asignado_id' => 'La solicitud no tiene un Usuario Captura asignado para su aprobación.',
            ]);
        }

        $isValidCaptureUser = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('u.id', $approverId)
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->where('p.activo', 1)
            ->where('r.nombre', 'Usuario Captura')
            ->where('p.clave', 'ubicaciones.aprobar_traslados')
            ->exists();

        $approver = User::query()->find($approverId);

        if (!$isValidCaptureUser || !$approver || !filter_var($approver->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'aprobador_asignado_id' => 'El aprobador asignado debe ser un Usuario Captura activo, con permiso para aprobar traslados y correo válido.',
            ]);
        }

        return $approver;
    }

    private function resolveRequester(SolicitudTraslado $request): User
    {
        $requesterId = (int) ($request->solicitado_por ?? 0);
        $requester = $requesterId > 0
            ? User::query()->find($requesterId)
            : null;

        if (!$requester || !filter_var($requester->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'La solicitud no conserva un Usuario Planta / Inventarios con correo válido para recibir el resultado.'
            );
        }

        return $requester;
    }

    /**
     * @return array{
     *   requested_by:string,
     *   asset_description:string,
     *   origin_location:string,
     *   destination_location:string,
     *   destination_responsible:string,
     *   resolved_by:string
     * }
     */
    private function mailContext(SolicitudTraslado $request): array
    {
        $row = DB::table('solicitudes_traslado as st')
            ->join('activos as a', 'a.numero_activo', '=', 'st.numero_activo')
            ->leftJoin('users as us', 'us.id', '=', 'st.solicitado_por')
            ->leftJoin('users as ur', 'ur.id', '=', 'st.resuelto_por')
            ->leftJoin('ubicaciones as uo', 'uo.id', '=', 'st.ubicacion_origen_id')
            ->leftJoin('plantas as po', 'po.id', '=', 'uo.planta_id')
            ->join('ubicaciones as ud', 'ud.id', '=', 'st.ubicacion_destino_id')
            ->join('plantas as pd', 'pd.id', '=', 'ud.planta_id')
            ->leftJoin('responsables as rd', 'rd.id', '=', 'st.responsable_destino_id')
            ->where('st.id', $request->id)
            ->select([
                'a.descripcion as asset_description',
                'us.name as requested_by',
                'us.email as requested_by_email',
                'ur.name as resolved_by',
                'ur.email as resolved_by_email',
                'uo.codigo_interno as origin_code',
                'uo.descripcion as origin_description',
                'po.nombre as origin_plant',
                'ud.codigo_interno as destination_code',
                'ud.descripcion as destination_description',
                'pd.nombre as destination_plant',
                'rd.nombre as destination_responsible',
            ])
            ->first();

        if (!$row) {
            throw new RuntimeException('No fue posible recuperar el detalle de la solicitud para generar el correo.');
        }

        return [
            'requested_by' => trim((string) ($row->requested_by ?: $row->requested_by_email ?: 'Usuario Planta / Inventarios')),
            'asset_description' => trim((string) ($row->asset_description ?: 'Activo sin descripción')),
            'origin_location' => $this->formatLocation(
                $row->origin_plant,
                $row->origin_code,
                $row->origin_description,
                'Sin ubicación de origen registrada'
            ),
            'destination_location' => $this->formatLocation(
                $row->destination_plant,
                $row->destination_code,
                $row->destination_description,
                'Ubicación destino no disponible'
            ),
            'destination_responsible' => trim((string) ($row->destination_responsible ?: 'Sin cambio de responsable')),
            'resolved_by' => trim((string) ($row->resolved_by ?: $row->resolved_by_email ?: 'Usuario Captura')),
        ];
    }

    private function formatLocation(
        ?string $plant,
        ?string $code,
        ?string $description,
        string $fallback
    ): string {
        $parts = array_values(array_filter([
            trim((string) $plant),
            trim((string) $code),
            trim((string) $description),
        ], static fn (string $part): bool => $part !== ''));

        return $parts === [] ? $fallback : implode(' / ', $parts);
    }

    private function reviewUrl(SolicitudTraslado $request): string
    {
        return route('ubicacion', [
            'panel' => 'traslados',
            'solicitud' => $request->uuid,
        ]).'#traslado-'.$request->uuid;
    }

    private function assertRealMailTransport(): void
    {
        $mailer = strtolower(trim((string) config('mail.default', 'log')));

        if ($mailer === '' || in_array($mailer, ['log', 'array'], true)) {
            throw new RuntimeException(
                'MAIL_MAILER='.$mailer.' no entrega correos reales. Configura SMTP, SES, Postmark o Resend en Laravel Cloud.'
            );
        }

        if (in_array($mailer, ['failover', 'roundrobin'], true)) {
            $configuredMailers = (array) config('mail.mailers.'.$mailer.'.mailers', []);
            $hasDeliverableTransport = false;

            foreach ($configuredMailers as $configuredMailer) {
                $transport = strtolower((string) config('mail.mailers.'.$configuredMailer.'.transport', ''));

                if ($transport !== '' && !in_array($transport, ['log', 'array'], true)) {
                    $hasDeliverableTransport = true;
                    break;
                }
            }

            if (!$hasDeliverableTransport) {
                throw new RuntimeException(
                    'El mailer '.$mailer.' no contiene un transporte capaz de entregar correos reales.'
                );
            }
        }
    }

    private function safeAudit(
        SolicitudTraslado $request,
        ?int $userId,
        string $action,
        array $after
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $request->numero_activo,
                'user_id' => $userId,
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => $action,
                'tabla_afectada' => 'solicitudes_traslado',
                'registro_clave' => (string) $request->id,
                'antes' => null,
                'despues' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'transfer_notification_audit_write',
                [
                    'transfer_request_id' => $request->id,
                    'user_id' => $userId,
                    'action' => $action,
                ]
            );
        }
    }
}

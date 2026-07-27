<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveTransferRequest;
use App\Models\SolicitudTraslado;
use App\Services\SwafiAuthorizationService;
use App\Services\TransferNotificationService;
use App\Services\TransferWorkflowService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TransferApprovalController extends Controller
{
    public function __construct(
        private readonly TransferWorkflowService $workflow,
        private readonly TransferNotificationService $notifications,
        private readonly SwafiAuthorizationService $authorization
    ) {
    }

    public function approve(ResolveTransferRequest $request, SolicitudTraslado $solicitud)
    {
        $userId = $this->userId();
        $resolvedTransfer = $this->workflow->approve(
            transferRequest: $solicitud,
            approverId: $userId,
            comment: $request->validated('comentario_resolucion')
        );

        $notification = $this->notifications->sendResolution(
            transferRequest: $resolvedTransfer,
            triggeredBy: $userId
        );

        $redirect = redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with(
                'success',
                'El traslado fue aprobado y la ubicación del activo se actualizó con trazabilidad completa.'
                .($notification['sent'] ? ' '.$notification['message'] : '')
            );

        if (!$notification['sent']) {
            $redirect->with('warning', $notification['message']);
        }

        return $redirect;
    }

    public function reject(ResolveTransferRequest $request, SolicitudTraslado $solicitud)
    {
        $userId = $this->userId();
        $resolvedTransfer = $this->workflow->reject(
            transferRequest: $solicitud,
            approverId: $userId,
            comment: (string) $request->validated('comentario_resolucion')
        );

        $notification = $this->notifications->sendResolution(
            transferRequest: $resolvedTransfer,
            triggeredBy: $userId
        );

        $redirect = redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with(
                'warning',
                'La solicitud de traslado fue rechazada. La ubicación actual del activo no cambió. '
                .$notification['message']
            );

        return $redirect;
    }

    public function resendNotification(SolicitudTraslado $solicitud)
    {
        $userId = $this->userId();
        $this->assertCanRetryAssignmentNotification($solicitud, $userId);

        $result = $this->notifications->sendAssignment(
            transferRequest: $solicitud,
            triggeredBy: $userId
        );

        return redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with($result['sent'] ? 'success' : 'warning', $result['message']);
    }

    public function resendResolutionNotification(SolicitudTraslado $solicitud)
    {
        $userId = $this->userId();
        $this->assertCanRetryResolutionNotification($solicitud, $userId);

        $result = $this->notifications->sendResolution(
            transferRequest: $solicitud,
            triggeredBy: $userId
        );

        return redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with($result['sent'] ? 'success' : 'warning', $result['message']);
    }


    private function assertCanRetryAssignmentNotification(SolicitudTraslado $solicitud, ?int $userId): void
    {
        $context = $this->authorization->contextForUser((int) ($userId ?? 0));
        $isRequester = (int) ($solicitud->solicitado_por ?? 0) === (int) ($userId ?? 0);
        $isAssignedApprover = (int) ($solicitud->aprobador_asignado_id ?? 0) === (int) ($userId ?? 0);

        if (!$context['is_admin'] && !$isRequester && !$isAssignedApprover) {
            throw new AccessDeniedHttpException(
                'Solo la persona que creó la solicitud, el Usuario Captura asignado o el Administrador SWAFI pueden reenviar la notificación pendiente.'
            );
        }
    }

    private function assertCanRetryResolutionNotification(SolicitudTraslado $solicitud, ?int $userId): void
    {
        $context = $this->authorization->contextForUser((int) ($userId ?? 0));
        $isRelatedUser = in_array((int) ($userId ?? 0), [
            (int) ($solicitud->solicitado_por ?? 0),
            (int) ($solicitud->aprobador_asignado_id ?? 0),
            (int) ($solicitud->resuelto_por ?? 0),
        ], true);

        if (!$context['is_admin'] && !$isRelatedUser) {
            throw new AccessDeniedHttpException(
                'Solo el solicitante, el Usuario Captura relacionado o el Administrador SWAFI pueden reenviar el resultado del traslado.'
            );
        }
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}

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
        $this->workflow->approve(
            transferRequest: $solicitud,
            approverId: $this->userId(),
            comment: $request->validated('comentario_resolucion')
        );

        return redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with('success', 'El traslado fue aprobado y la ubicación del activo se actualizó con trazabilidad completa.');
    }

    public function reject(ResolveTransferRequest $request, SolicitudTraslado $solicitud)
    {
        $this->workflow->reject(
            transferRequest: $solicitud,
            approverId: $this->userId(),
            comment: (string) $request->validated('comentario_resolucion')
        );

        return redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with('warning', 'La solicitud de traslado fue rechazada. La ubicación actual del activo no cambió.');
    }

    public function resendNotification(SolicitudTraslado $solicitud)
    {
        $userId = $this->userId();
        $context = $this->authorization->contextForUser((int) ($userId ?? 0));

        if (
            !$context['is_admin']
            && (int) ($solicitud->solicitado_por ?? 0) !== (int) ($userId ?? 0)
        ) {
            throw new AccessDeniedHttpException(
                'Solo la persona que creó la solicitud o el Administrador SWAFI pueden reenviar la notificación.'
            );
        }

        $result = $this->notifications->sendAssignment(
            transferRequest: $solicitud,
            triggeredBy: $userId
        );

        return redirect()
            ->route('ubicacion', ['panel' => 'traslados'])
            ->with($result['sent'] ? 'success' : 'warning', $result['message']);
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}

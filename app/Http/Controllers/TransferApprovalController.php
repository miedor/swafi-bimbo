<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveTransferRequest;
use App\Models\SolicitudTraslado;
use App\Services\TransferWorkflowService;

class TransferApprovalController extends Controller
{
    public function __construct(
        private readonly TransferWorkflowService $workflow
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

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}

<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\MovimientoUbicacion;
use App\Models\SolicitudTraslado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferWorkflowService
{
    public function __construct(
        private readonly InventoryPeriodService $periods,
        private readonly SwafiAuthorizationService $authorization
    ) {
    }

    /**
     * @return array{type:string,message:string,movement?:MovimientoUbicacion,request?:SolicitudTraslado}
     */
    public function registerMovementOrTransfer(array $data, ?int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $asset = Activo::query()
                ->where('numero_activo', $data['numero_activo'])
                ->lockForUpdate()
                ->firstOrFail();

            $movementDate = Carbon::parse($data['fecha_movimiento']);
            $destination = $this->periods->assertMovementAllowed(
                $asset,
                (int) $data['ubicacion_destino_id'],
                $movementDate
            );

            $responsibleId = !empty($data['responsable_id'])
                ? (int) $data['responsable_id']
                : null;
            $this->assertResponsibleActive($responsibleId);

            $isCrossPlant = (int) $destination->planta_id !== (int) $asset->planta_id;

            if ($isCrossPlant) {
                $assignedApproverId = !empty($data['aprobador_asignado_id'])
                    ? (int) $data['aprobador_asignado_id']
                    : 0;

                $assignedApprover = $this->resolveActiveCaptureApprover($assignedApproverId);

                if ($userId !== null && (int) $assignedApprover->id === $userId) {
                    throw ValidationException::withMessages([
                        'aprobador_asignado_id' => 'Por separación de funciones, la persona solicitante no puede asignarse a sí misma la aprobación del traslado.',
                    ]);
                }

                $pendingRequest = SolicitudTraslado::query()
                    ->where('numero_activo', $asset->numero_activo)
                    ->where('estatus', 'pendiente')
                    ->lockForUpdate()
                    ->first(['id']);

                if ($pendingRequest) {
                    throw ValidationException::withMessages([
                        'numero_activo' => 'El activo ya cuenta con una solicitud de traslado pendiente de resolución.',
                    ]);
                }

                $request = SolicitudTraslado::create([
                    'uuid' => (string) Str::uuid(),
                    'numero_activo' => $asset->numero_activo,
                    'ubicacion_origen_id' => $asset->ubicacion_id,
                    'ubicacion_destino_id' => (int) $destination->id,
                    'responsable_destino_id' => $responsibleId,
                    'aprobador_asignado_id' => (int) $assignedApprover->id,
                    'fecha_movimiento' => $movementDate,
                    'motivo' => trim((string) $data['motivo']),
                    'evidencia' => $data['evidencia'] ?? null,
                    'estatus' => 'pendiente',
                    'solicitado_por' => $userId,
                    'solicitado_at' => now(),
                    'notificacion_aprobador_at' => null,
                    'ultimo_intento_notificacion_at' => null,
                    'notificacion_aprobador_intentos' => 0,
                    'notificacion_aprobador_error' => null,
                ]);

                $this->audit(
                    assetNumber: $asset->numero_activo,
                    userId: $userId,
                    action: 'SOLICITUD_TRASLADO_CREADA',
                    table: 'solicitudes_traslado',
                    recordKey: (string) $request->id,
                    before: ['activo' => $asset->toArray()],
                    after: [
                        'solicitud' => $request->toArray(),
                        'aprobador_asignado' => [
                            'id' => $assignedApprover->id,
                            'nombre' => $assignedApprover->name,
                            'email' => $assignedApprover->email,
                        ],
                    ]
                );

                return [
                    'type' => 'transfer_request',
                    'request' => $request,
                    'message' => 'El traslado entre plantas quedó asignado a '.$assignedApprover->name.' para su aprobación. La ubicación actual no fue modificada.',
                ];
            }

            $movement = $this->applyMovement(
                asset: $asset,
                destinationLocationId: (int) $destination->id,
                destinationPlantId: (int) $destination->planta_id,
                responsibleId: $responsibleId,
                movementDate: $movementDate,
                reason: trim((string) $data['motivo']),
                evidence: $data['evidencia'] ?? null,
                userId: $userId
            );

            return [
                'type' => 'movement',
                'movement' => $movement,
                'message' => 'La reubicación dentro de la planta se aplicó y quedó registrada con trazabilidad.',
            ];
        });
    }

    public function approve(
        SolicitudTraslado $transferRequest,
        ?int $approverId,
        ?string $comment
    ): SolicitudTraslado {
        return DB::transaction(function () use ($transferRequest, $approverId, $comment) {
            $request = SolicitudTraslado::query()
                ->whereKey($transferRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPending($request);
            $this->assertAssignedApproverCanResolve($request, $approverId);

            $asset = Activo::query()
                ->where('numero_activo', $request->numero_activo)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) ($asset->ubicacion_id ?? 0) !== (int) ($request->ubicacion_origen_id ?? 0)) {
                throw ValidationException::withMessages([
                    'comentario_resolucion' => 'La ubicación actual del activo cambió después de crear la solicitud. Rechaza esta solicitud y registra una nueva con la información vigente.',
                ]);
            }

            $destination = $this->periods->assertMovementAllowed(
                $asset,
                (int) $request->ubicacion_destino_id,
                $request->fecha_movimiento
            );
            $this->assertResponsibleActive(
                $request->responsable_destino_id ? (int) $request->responsable_destino_id : null
            );

            if ((int) $destination->planta_id === (int) $asset->planta_id) {
                throw ValidationException::withMessages([
                    'comentario_resolucion' => 'La solicitud dejó de representar un traslado entre plantas. Recházala y registra una reubicación interna nueva.',
                ]);
            }

            $beforeRequest = $request->toArray();
            $beforeAsset = $asset->toArray();

            $movement = $this->applyMovement(
                asset: $asset,
                destinationLocationId: (int) $destination->id,
                destinationPlantId: (int) $destination->planta_id,
                responsibleId: $request->responsable_destino_id,
                movementDate: Carbon::parse($request->fecha_movimiento),
                reason: $request->motivo,
                evidence: $request->evidencia,
                userId: $approverId,
                auditAction: 'TRASLADO_APROBADO_Y_APLICADO'
            );

            $request->update([
                'estatus' => 'aprobado',
                'resuelto_por' => $approverId,
                'resuelto_at' => now(),
                'comentario_resolucion' => $this->nullableTrim($comment),
                'movimiento_id' => $movement->id,
            ]);

            $request->refresh();

            $this->audit(
                assetNumber: $request->numero_activo,
                userId: $approverId,
                action: 'SOLICITUD_TRASLADO_APROBADA',
                table: 'solicitudes_traslado',
                recordKey: (string) $request->id,
                before: [
                    'solicitud' => $beforeRequest,
                    'activo' => $beforeAsset,
                ],
                after: [
                    'solicitud' => $request->toArray(),
                    'activo' => $asset->fresh()->toArray(),
                    'movimiento' => $movement->toArray(),
                ]
            );

            return $request;
        });
    }

    public function reject(
        SolicitudTraslado $transferRequest,
        ?int $approverId,
        string $comment
    ): SolicitudTraslado {
        return DB::transaction(function () use ($transferRequest, $approverId, $comment) {
            $request = SolicitudTraslado::query()
                ->whereKey($transferRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPending($request);
            $this->assertAssignedApproverCanResolve($request, $approverId);
            $before = $request->toArray();

            $request->update([
                'estatus' => 'rechazado',
                'resuelto_por' => $approverId,
                'resuelto_at' => now(),
                'comentario_resolucion' => trim($comment),
                'movimiento_id' => null,
            ]);

            $request->refresh();

            $this->audit(
                assetNumber: $request->numero_activo,
                userId: $approverId,
                action: 'SOLICITUD_TRASLADO_RECHAZADA',
                table: 'solicitudes_traslado',
                recordKey: (string) $request->id,
                before: ['solicitud' => $before],
                after: ['solicitud' => $request->toArray()]
            );

            return $request;
        });
    }

    private function applyMovement(
        Activo $asset,
        int $destinationLocationId,
        int $destinationPlantId,
        ?int $responsibleId,
        Carbon $movementDate,
        string $reason,
        ?string $evidence,
        ?int $userId,
        string $auditAction = 'CAMBIO_UBICACION'
    ): MovimientoUbicacion {
        $before = $asset->toArray();

        $movement = MovimientoUbicacion::create([
            'numero_activo' => $asset->numero_activo,
            'ubicacion_origen_id' => $asset->ubicacion_id,
            'ubicacion_destino_id' => $destinationLocationId,
            'motivo' => $reason,
            'evidencia' => $evidence,
            'fecha_movimiento' => $movementDate,
            'responsable_id' => $responsibleId,
            'registrado_por' => $userId,
        ]);

        $asset->update([
            'planta_id' => $destinationPlantId,
            'ubicacion_id' => $destinationLocationId,
            'responsable_id' => $responsibleId ?: $asset->responsable_id,
            'actualizado_por' => $userId,
        ]);

        $this->audit(
            assetNumber: $asset->numero_activo,
            userId: $userId,
            action: $auditAction,
            table: 'movimientos_ubicacion',
            recordKey: (string) $movement->id,
            before: ['activo' => $before],
            after: [
                'activo' => $asset->fresh()->toArray(),
                'movimiento' => $movement->toArray(),
            ]
        );

        return $movement;
    }

    private function assertPending(SolicitudTraslado $request): void
    {
        if ($request->estatus !== 'pendiente') {
            throw ValidationException::withMessages([
                'comentario_resolucion' => 'La solicitud ya fue resuelta y no puede procesarse nuevamente.',
            ]);
        }
    }

    private function resolveActiveCaptureApprover(int $approverId): object
    {
        if ($approverId <= 0) {
            throw ValidationException::withMessages([
                'aprobador_asignado_id' => 'Selecciona el Usuario Captura responsable de aprobar o rechazar el traslado entre plantas.',
            ]);
        }

        $approver = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('u.id', $approverId)
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->where('r.nombre', 'Usuario Captura')
            ->where('p.clave', 'ubicaciones.aprobar_traslados')
            ->select([
                'u.id',
                'u.usuario',
                'u.name',
                'u.email',
            ])
            ->distinct()
            ->first();

        if (!$approver || !filter_var($approver->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'aprobador_asignado_id' => 'La persona seleccionada debe ser un Usuario Captura activo, con permiso para aprobar traslados y correo válido.',
            ]);
        }

        return $approver;
    }

    private function assertAssignedApproverCanResolve(
        SolicitudTraslado $request,
        ?int $approverId
    ): void {
        $userId = (int) ($approverId ?? 0);

        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'comentario_resolucion' => 'No fue posible identificar al usuario que intenta resolver la solicitud.',
            ]);
        }

        $context = $this->authorization->contextForUser($userId);

        if ($context['is_admin']) {
            return;
        }

        if ((int) ($request->aprobador_asignado_id ?? 0) !== $userId) {
            throw ValidationException::withMessages([
                'comentario_resolucion' => 'La solicitud está asignada a otro Usuario Captura. Solo la persona responsable designada o el Administrador SWAFI pueden resolverla.',
            ]);
        }

        if (!in_array('ubicaciones.aprobar_traslados', $context['permissions'], true)) {
            throw ValidationException::withMessages([
                'comentario_resolucion' => 'Tu usuario ya no cuenta con el permiso requerido para resolver traslados.',
            ]);
        }

        $this->resolveActiveCaptureApprover($userId);
    }

    private function assertResponsibleActive(?int $responsibleId): void
    {
        if ($responsibleId === null) {
            return;
        }

        $isActive = DB::table('responsables')
            ->where('id', $responsibleId)
            ->where('estatus', 'activo')
            ->exists();

        if (!$isActive) {
            throw ValidationException::withMessages([
                'responsable_id' => 'El responsable seleccionado ya no existe o se encuentra inactivo.',
            ]);
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function audit(
        ?string $assetNumber,
        ?int $userId,
        string $action,
        ?string $table,
        ?string $recordKey,
        ?array $before,
        ?array $after
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $assetNumber,
            'user_id' => $userId,
            'modulo' => 'M02 Control fiscal, financiero y ubicación física',
            'accion' => $action,
            'tabla_afectada' => $table,
            'registro_clave' => $recordKey,
            'antes' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

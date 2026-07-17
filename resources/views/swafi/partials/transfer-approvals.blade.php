<details class="card inventory-workflow-card workflow-panel" {{ $pendingTransfersCount > 0 || request('panel') === 'traslados' ? 'open' : '' }}>
  <summary class="workflow-panel-summary">
    <span>
      <strong>{{ $canApproveTransfers ? 'Bandeja de aprobación de traslados' : 'Mis solicitudes de traslado' }}</strong>
      <small>
        {{ $canApproveTransfers
            ? 'Solo se muestran las solicitudes asignadas a tu usuario. El Administrador SWAFI puede consultar todas.'
            : 'Los cambios entre plantas no modifican el activo hasta que el Usuario Captura asignado los aprueba.' }}
      </small>
    </span>
    <span class="pill {{ $pendingTransfersCount > 0 ? 'warn' : 'ok' }}">
      {{ $pendingTransfersCount }} pendiente(s)
    </span>
  </summary>

  <div class="workflow-panel-body">
  <div class="workflow-table-wrap">
    <table class="workflow-table">
      <thead>
        <tr>
          <th>Activo</th>
          <th>Origen → destino</th>
          <th>Fecha / solicitante</th>
          <th>Aprobador / notificación</th>
          <th>Motivo</th>
          <th>Estatus</th>
          @if($canApproveTransfers)
            <th>Resolución</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @forelse($solicitudesTraslado as $solicitud)
          @php
            $statusClass = match($solicitud->estatus) {
                'aprobado' => 'ok',
                'rechazado' => 'danger',
                default => 'warn',
            };
            $statusLabel = match($solicitud->estatus) {
                'aprobado' => 'Aprobado',
                'rechazado' => 'Rechazado',
                default => 'Pendiente',
            };
            $canResolveThisRequest = $canApproveTransfers
                && ($isAdministrator || (int) $solicitud->aprobador_asignado_id === (int) $currentUserId);
            $canResendNotification = $solicitud->estatus === 'pendiente'
                && !empty($solicitud->aprobador_asignado_id)
                && ($isAdministrator || (int) $solicitud->solicitado_por === (int) $currentUserId);
          @endphp
          <tr id="traslado-{{ $solicitud->uuid }}">
            <td>
              <strong>{{ $solicitud->numero_activo }}</strong><br>
              <small>{{ $solicitud->activo_descripcion }}</small><br>
              <small class="workflow-code">{{ $solicitud->uuid }}</small>
            </td>
            <td>
              <strong>{{ $solicitud->origen_planta ?? 'Sin planta registrada' }}</strong><br>
              <small>{{ $solicitud->origen_codigo ?? 'Sin ubicación' }} {{ $solicitud->origen_descripcion ? '· '.$solicitud->origen_descripcion : '' }}</small>
              <div class="workflow-arrow" aria-hidden="true">↓</div>
              <strong>{{ $solicitud->destino_planta }}</strong><br>
              <small>{{ $solicitud->destino_codigo ?? 'Sin código' }} {{ $solicitud->destino_descripcion ? '· '.$solicitud->destino_descripcion : '' }}</small>
            </td>
            <td>
              {{ \Carbon\Carbon::parse($solicitud->fecha_movimiento)->format('d/m/Y H:i') }}<br>
              <small>{{ $solicitud->solicitado_por_nombre ?? 'Usuario no disponible' }}</small><br>
              <small>{{ \Carbon\Carbon::parse($solicitud->solicitado_at)->format('d/m/Y H:i') }}</small>
            </td>
            <td>
              @if($solicitud->aprobador_asignado_id)
                <strong>{{ $solicitud->aprobador_asignado_nombre ?? 'Usuario Captura no disponible' }}</strong><br>
                <small>{{ $solicitud->aprobador_asignado_email ?? 'Sin correo disponible' }}</small><br>

                @if($solicitud->notificacion_aprobador_at)
                  <span class="ui-notification-state ok">Correo enviado</span><br>
                  <small>{{ \Carbon\Carbon::parse($solicitud->notificacion_aprobador_at)->format('d/m/Y H:i') }}</small>
                @elseif($solicitud->notificacion_aprobador_error)
                  <span class="ui-notification-state warn">Correo pendiente de reenvío</span><br>
                  <small>Intentos: {{ (int) $solicitud->notificacion_aprobador_intentos }}</small>
                @else
                  <span class="ui-notification-state warn">Correo pendiente</span><br>
                  <small>Intentos: {{ (int) $solicitud->notificacion_aprobador_intentos }}</small>
                @endif

                @if($canResendNotification)
                  <form method="POST" action="{{ route('ubicacion.traslados.notificar', $solicitud->id) }}" style="margin-top:8px;">
                    @csrf
                    <button class="tab" type="submit">Reenviar correo</button>
                  </form>
                @endif
              @else
                <span class="ui-notification-state warn">Solicitud histórica sin aprobador</span><br>
                <small>Puede resolverla únicamente el Administrador SWAFI.</small>
              @endif
            </td>
            <td>
              {{ $solicitud->motivo }}
              @if($solicitud->evidencia)
                <br><small>{{ \Illuminate\Support\Str::limit($solicitud->evidencia, 160) }}</small>
              @endif
              @if($solicitud->responsable_destino)
                <br><small>Responsable propuesto: {{ $solicitud->responsable_destino }}</small>
              @endif
            </td>
            <td>
              <span class="pill {{ $statusClass }}">{{ $statusLabel }}</span>
              @if($solicitud->resuelto_at)
                <br><small>{{ \Carbon\Carbon::parse($solicitud->resuelto_at)->format('d/m/Y H:i') }}</small>
              @endif
              @if($solicitud->comentario_resolucion)
                <br><small>{{ $solicitud->comentario_resolucion }}</small>
              @endif
            </td>
            @if($canApproveTransfers)
              <td>
                @if($solicitud->estatus === 'pendiente' && $canResolveThisRequest)
                  <div class="workflow-resolution-stack">
                    <form method="POST" action="{{ route('ubicacion.traslados.aprobar', $solicitud->id) }}" class="workflow-inline-form">
                      @csrf
                      @method('PATCH')
                      <input
                        name="comentario_resolucion"
                        maxlength="1000"
                        placeholder="Comentario de aprobación (opcional)"
                        aria-label="Comentario de aprobación para {{ $solicitud->numero_activo }}"
                      >
                      <button class="tab workflow-btn-approve" type="submit">Aprobar y aplicar</button>
                    </form>

                    <form method="POST" action="{{ route('ubicacion.traslados.rechazar', $solicitud->id) }}" class="workflow-inline-form">
                      @csrf
                      @method('PATCH')
                      <input
                        name="comentario_resolucion"
                        minlength="10"
                        maxlength="1000"
                        required
                        placeholder="Motivo de rechazo (mín. 10 caracteres)"
                        aria-label="Motivo de rechazo para {{ $solicitud->numero_activo }}"
                      >
                      <button class="tab workflow-btn-reject" type="submit">Rechazar</button>
                    </form>
                  </div>
                @elseif($solicitud->estatus === 'pendiente')
                  <small>La solicitud está asignada a {{ $solicitud->aprobador_asignado_nombre ?? 'otro Usuario Captura' }}.</small>
                @else
                  <small>Resuelto por {{ $solicitud->resuelto_por_nombre ?? 'usuario no disponible' }}</small>
                @endif
              </td>
            @endif
          </tr>
        @empty
          <tr>
            <td colspan="{{ $canApproveTransfers ? 7 : 6 }}">
              No existen solicitudes de traslado para mostrar.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($solicitudesTraslado->hasPages())
    <nav class="workflow-pagination" aria-label="Paginación de solicitudes de traslado">
      @if($solicitudesTraslado->onFirstPage())
        <span class="is-disabled">Anterior</span>
      @else
        <a href="{{ $solicitudesTraslado->previousPageUrl() }}">Anterior</a>
      @endif
      <strong>Página {{ $solicitudesTraslado->currentPage() }} de {{ $solicitudesTraslado->lastPage() }}</strong>
      @if($solicitudesTraslado->hasMorePages())
        <a href="{{ $solicitudesTraslado->nextPageUrl() }}">Siguiente</a>
      @else
        <span class="is-disabled">Siguiente</span>
      @endif
    </nav>
  @endif
  </div>
</details>

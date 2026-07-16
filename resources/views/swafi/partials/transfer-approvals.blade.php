<details class="card inventory-workflow-card workflow-panel" {{ $pendingTransfersCount > 0 || request('panel') === 'traslados' ? 'open' : '' }}>
  <summary class="workflow-panel-summary">
    <span>
      <strong>{{ $canApproveTransfers ? 'Bandeja de aprobación de traslados' : 'Mis solicitudes de traslado' }}</strong>
      <small>Los cambios entre plantas no modifican el activo hasta que una persona autorizada los aprueba.</small>
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
          @endphp
          <tr>
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
                @if($solicitud->estatus === 'pendiente')
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
                @else
                  <small>Resuelto por {{ $solicitud->resuelto_por_nombre ?? 'usuario no disponible' }}</small>
                @endif
              </td>
            @endif
          </tr>
        @empty
          <tr>
            <td colspan="{{ $canApproveTransfers ? 6 : 5 }}">
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

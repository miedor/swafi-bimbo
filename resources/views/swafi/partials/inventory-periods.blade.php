<details class="card inventory-workflow-card workflow-panel" {{ request('panel') === 'periodos' || $errors->has('planta_id') || $errors->has('fecha_inicio') || $errors->has('fecha_fin') || $errors->has('motivo_estado') ? 'open' : '' }}>
  <summary class="workflow-panel-summary">
    <span>
      <strong>Control de periodos de inventario</strong>
      <small>Un periodo bloqueado impide registrar movimientos e inventarios fechados dentro del rango de conciliación.</small>
    </span>
    <span class="pill {{ $blockedPeriodsCount > 0 ? 'warn' : 'ok' }}">
      {{ $blockedPeriodsCount }} bloqueado(s)
    </span>
  </summary>

  <div class="workflow-panel-body">
  @if($canManageInventoryPeriods)
    <details class="workflow-details" {{ $errors->has('planta_id') || $errors->has('fecha_inicio') || $errors->has('fecha_fin') ? 'open' : '' }}>
      <summary>Crear nuevo periodo de inventario</summary>
      <form method="POST" action="{{ route('ubicacion.periodos.store') }}" class="workflow-period-form">
        @csrf

        <label>
          <span>Planta</span>
          <select name="planta_id" required>
            <option value="">Seleccione...</option>
            @foreach($catalogos['plantas'] as $planta)
              <option value="{{ $planta->id }}" {{ (string) old('planta_id') === (string) $planta->id ? 'selected' : '' }}>
                {{ $planta->nombre }}
              </option>
            @endforeach
          </select>
        </label>

        <label>
          <span>Nombre del periodo</span>
          <input name="nombre" minlength="5" maxlength="120" value="{{ old('nombre') }}" placeholder="Ej. Cierre inventario julio 2026" required>
        </label>

        <label>
          <span>Fecha inicial</span>
          <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required>
        </label>

        <label>
          <span>Fecha final</span>
          <input type="date" name="fecha_fin" value="{{ old('fecha_fin') }}" required>
        </label>

        <label class="workflow-field-wide">
          <span>Observaciones</span>
          <textarea name="observaciones" maxlength="1000" placeholder="Alcance, responsables o notas de conciliación">{{ old('observaciones') }}</textarea>
        </label>

        <div class="workflow-field-wide action-group">
          <button class="tab" type="submit">Crear periodo abierto</button>
        </div>
      </form>
    </details>
  @endif

  <div class="workflow-period-list">
    @forelse($periodosInventario as $periodo)
      <article class="workflow-period-item {{ $periodo->estatus === 'bloqueado' ? 'is-blocked' : '' }}">
        <div class="workflow-period-main">
          <div>
            <strong>{{ $periodo->nombre }}</strong>
            <span class="pill {{ $periodo->estatus === 'bloqueado' ? 'warn' : 'ok' }}">
              {{ $periodo->estatus === 'bloqueado' ? 'Bloqueado' : 'Abierto' }}
            </span>
          </div>
          <div class="workflow-period-meta">
            {{ $periodo->planta_nombre }} ·
            {{ \Carbon\Carbon::parse($periodo->fecha_inicio)->format('d/m/Y') }} al
            {{ \Carbon\Carbon::parse($periodo->fecha_fin)->format('d/m/Y') }}
          </div>
          @if($periodo->observaciones)
            <small>{{ $periodo->observaciones }}</small>
          @endif
          @if($periodo->estatus === 'bloqueado')
            <small>
              Bloqueado por {{ $periodo->bloqueado_por_nombre ?? 'usuario no disponible' }}
              {{ $periodo->bloqueado_at ? 'el '.\Carbon\Carbon::parse($periodo->bloqueado_at)->format('d/m/Y H:i') : '' }}.
              {{ $periodo->motivo_bloqueo }}
            </small>
          @elseif($periodo->desbloqueado_at)
            <small>
              Último desbloqueo: {{ \Carbon\Carbon::parse($periodo->desbloqueado_at)->format('d/m/Y H:i') }}
              por {{ $periodo->desbloqueado_por_nombre ?? 'usuario no disponible' }}.
            </small>
          @endif
        </div>

        @if($canManageInventoryPeriods)
          <form
            method="POST"
            action="{{ $periodo->estatus === 'bloqueado' ? route('ubicacion.periodos.desbloquear', $periodo->id) : route('ubicacion.periodos.bloquear', $periodo->id) }}"
            class="workflow-period-action"
          >
            @csrf
            @method('PATCH')
            <input
              name="motivo_estado"
              minlength="10"
              maxlength="1000"
              required
              placeholder="{{ $periodo->estatus === 'bloqueado' ? 'Motivo del desbloqueo' : 'Motivo del cierre o conciliación' }}"
              aria-label="Motivo para cambiar el estado del periodo {{ $periodo->nombre }}"
            >
            <button class="tab {{ $periodo->estatus === 'bloqueado' ? 'workflow-btn-approve' : 'workflow-btn-lock' }}" type="submit">
              {{ $periodo->estatus === 'bloqueado' ? 'Desbloquear periodo' : 'Bloquear periodo' }}
            </button>
          </form>
        @endif
      </article>
    @empty
      <div class="workflow-empty">No existen periodos de inventario configurados.</div>
    @endforelse
  </div>

  @if($periodosInventario->hasPages())
    <nav class="workflow-pagination" aria-label="Paginación de periodos de inventario">
      @if($periodosInventario->onFirstPage())
        <span class="is-disabled">Anterior</span>
      @else
        <a href="{{ $periodosInventario->previousPageUrl() }}">Anterior</a>
      @endif
      <strong>Página {{ $periodosInventario->currentPage() }} de {{ $periodosInventario->lastPage() }}</strong>
      @if($periodosInventario->hasMorePages())
        <a href="{{ $periodosInventario->nextPageUrl() }}">Siguiente</a>
      @else
        <span class="is-disabled">Siguiente</span>
      @endif
    </nav>
  @endif
  </div>
</details>

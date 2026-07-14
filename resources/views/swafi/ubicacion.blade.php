@extends('layouts.app')

@section('title', 'Ubicación física e inventario | SWAFI')
@section('page_title', 'Ubicación física e inventario')
@section('page_subtitle', 'Control de localización, evidencia y seguimiento de inventarios')
@section('breadcrumb', 'Ubicación física e inventario')

@section('page_styles')
<style>
  .ui-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: start;
  }

  .ui-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 11px;
  }

  .ui-field-wide {
    grid-column: 1 / -1;
  }

  .ui-form-grid label,
  .ui-filter label {
    display: block;
  }

  .ui-form-grid span,
  .ui-filter span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .ui-form-grid input,
  .ui-form-grid select,
  .ui-form-grid textarea,
  .ui-filter input,
  .ui-filter select {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
  }

  .ui-form-grid textarea {
    min-height: 74px;
    resize: vertical;
  }

  .ui-message {
    margin-bottom: 12px;
    padding: 11px 13px;
    border-radius: 13px;
    font-size: 13px;
    font-weight: 750;
  }

  .ui-message-success {
    background: #e8f7ea;
    color: #1f6b2a;
    border: 1px solid #b9e5bf;
  }

  .ui-message-warning {
    background: #fff7db;
    color: #8a4b00;
    border: 1px solid #f9d36a;
  }

  .ui-message-error {
    background: #fdeaea;
    color: #8a1f1f;
    border: 1px solid #f2baba;
  }

  .ui-message ul {
    margin: 6px 0 0 18px;
  }

  .ui-filter {
    margin-top: 16px;
    padding: 14px;
    border: 1px solid #e1eaf6;
    border-radius: 18px;
    background: #f8fbff;
  }

  .ui-checkbox {
    display: flex !important;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
  }

  .ui-checkbox input {
    width: auto !important;
    min-height: auto !important;
  }

  .ui-checkbox span {
    margin: 0;
    font-weight: 800;
  }

  .ui-help {
    margin-top: 6px;
    color: #64748b;
    font-size: 11px;
    font-weight: 750;
    line-height: 1.35;
  }

  .ui-discrepancy-box {
    display: none;
    grid-column: 1 / -1;
    padding: 11px;
    border: 1px solid #f9d36a;
    border-radius: 14px;
    background: #fff9e8;
  }

  .ui-discrepancy-box.is-visible {
    display: block;
  }

  .ui-file-note {
    padding: 9px 11px;
    border: 1px dashed #b8cbe4;
    border-radius: 12px;
    background: #f8fbff;
    color: #324b6d;
    font-size: 11px;
    font-weight: 750;
    line-height: 1.4;
  }

  .ui-notification-state {
    display: inline-flex;
    align-items: center;
    padding: 4px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 900;
  }

  .ui-notification-state.ok {
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .ui-notification-state.warn {
    background: #fff7db;
    color: #8a4b00;
  }

  @media (max-width: 1100px) {
    .ui-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 760px) {
    .ui-form-grid,
    .query-grid-four {
      grid-template-columns: 1fr !important;
    }

    .ui-field-wide,
    .ui-discrepancy-box {
      grid-column: auto;
    }
  }
</style>
@endsection

@section('content')

@if(session('success'))
  <div class="ui-message ui-message-success">{{ session('success') }}</div>
@endif

@if(session('warning'))
  <div class="ui-message ui-message-warning">{{ session('warning') }}</div>
@endif

@if($errors->any())
  <div class="ui-message ui-message-error">
    <strong>Se encontraron errores:</strong>
    <ul>
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@php
  $activoSeleccionado = old('numero_activo', $filtros['numero_activo'] ?? '');
  $estatusInventario = old('estatus_localizacion', 'localizado');
@endphp

<section class="ui-grid">
  <div class="card">
    <div class="section-title">
      <h2>Cambio de ubicación física</h2>
      <span class="pill ok">Movimiento trazable</span>
    </div>

    <form method="POST" action="{{ route('ubicacion.movimiento') }}">
      @csrf

      <div class="ui-form-grid">
        <label class="ui-field-wide">
          <span>Activo fijo</span>
          <select name="numero_activo" required>
            <option value="">Seleccione...</option>
            @foreach($catalogos['activos'] as $activo)
              <option value="{{ $activo->numero_activo }}" {{ $activoSeleccionado === $activo->numero_activo ? 'selected' : '' }}>
                {{ $activo->numero_activo }} - {{ $activo->descripcion }}
              </option>
            @endforeach
          </select>
        </label>

        <label class="ui-field-wide">
          <span>Nueva ubicación física</span>
          <select name="ubicacion_destino_id" required>
            <option value="">Seleccione...</option>
            @foreach($catalogos['ubicaciones'] as $ubicacion)
              @php
                $ubicacionLabel = trim(
                    ($ubicacion->codigo_interno ? $ubicacion->codigo_interno . ' - ' : '') .
                    ($ubicacion->planta_nombre ?? '') .
                    ($ubicacion->area_nombre ? ' / ' . $ubicacion->area_nombre : '') .
                    ($ubicacion->descripcion ? ' / ' . $ubicacion->descripcion : '')
                );
              @endphp
              <option value="{{ $ubicacion->id }}" {{ (string) old('ubicacion_destino_id') === (string) $ubicacion->id ? 'selected' : '' }}>
                {{ $ubicacionLabel ?: 'Ubicación ' . $ubicacion->id }}
              </option>
            @endforeach
          </select>
        </label>

        <label>
          <span>Responsable</span>
          <select name="responsable_id">
            <option value="">Sin cambio</option>
            @foreach($catalogos['responsables'] as $responsable)
              <option value="{{ $responsable->id }}" {{ (string) old('responsable_id') === (string) $responsable->id ? 'selected' : '' }}>{{ $responsable->nombre }}</option>
            @endforeach
          </select>
        </label>

        <label>
          <span>Fecha del movimiento</span>
          <input type="datetime-local" name="fecha_movimiento" value="{{ old('fecha_movimiento', now()->format('Y-m-d\TH:i')) }}" required>
        </label>

        <label class="ui-field-wide">
          <span>Motivo</span>
          <input name="motivo" value="{{ old('motivo') }}" placeholder="Ej. Reubicación por inventario, traslado operativo o ajuste de planta">
        </label>

        <label class="ui-field-wide">
          <span>Evidencia / comentario del movimiento</span>
          <textarea name="evidencia" placeholder="Describe la razón del movimiento o la referencia de soporte">{{ old('evidencia') }}</textarea>
        </label>
      </div>

      <div class="action-group" style="margin-top:12px;">
        <button class="tab" type="submit">Guardar movimiento</button>
        <a class="tab" href="{{ route('ubicacion') }}">Limpiar</a>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="section-title">
      <h2>Toma de inventario</h2>
      <span class="pill ok">Evidencia y notificación</span>
    </div>

    <form method="POST" action="{{ route('ubicacion.inventario') }}" enctype="multipart/form-data" id="inventory-form">
      @csrf

      <div class="ui-form-grid">
        <label class="ui-field-wide">
          <span>Activo fijo</span>
          <select name="numero_activo" required>
            <option value="">Seleccione...</option>
            @foreach($catalogos['activos'] as $activo)
              <option value="{{ $activo->numero_activo }}" {{ $activoSeleccionado === $activo->numero_activo ? 'selected' : '' }}>
                {{ $activo->numero_activo }} - {{ $activo->descripcion }}
              </option>
            @endforeach
          </select>
        </label>

        <label>
          <span>Fecha de inventario</span>
          <input type="date" name="fecha_inventario" value="{{ old('fecha_inventario', now()->format('Y-m-d')) }}" required>
        </label>

        <label>
          <span>Estatus de localización</span>
          <select name="estatus_localizacion" id="inventory-status" required>
            <option value="localizado" {{ $estatusInventario === 'localizado' ? 'selected' : '' }}>Localizado</option>
            <option value="no_encontrado" {{ $estatusInventario === 'no_encontrado' ? 'selected' : '' }}>No encontrado</option>
            <option value="diferencia" {{ $estatusInventario === 'diferencia' ? 'selected' : '' }}>Diferencia de ubicación</option>
            <option value="pendiente" {{ $estatusInventario === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
          </select>
        </label>

        <label class="ui-field-wide">
          <span>Ubicación verificada</span>
          <select name="ubicacion_verificada_id">
            <option value="">Seleccione...</option>
            @foreach($catalogos['ubicaciones'] as $ubicacion)
              @php
                $ubicacionLabel = trim(
                    ($ubicacion->codigo_interno ? $ubicacion->codigo_interno . ' - ' : '') .
                    ($ubicacion->planta_nombre ?? '') .
                    ($ubicacion->area_nombre ? ' / ' . $ubicacion->area_nombre : '') .
                    ($ubicacion->descripcion ? ' / ' . $ubicacion->descripcion : '')
                );
              @endphp
              <option value="{{ $ubicacion->id }}" {{ (string) old('ubicacion_verificada_id') === (string) $ubicacion->id ? 'selected' : '' }}>
                {{ $ubicacionLabel ?: 'Ubicación ' . $ubicacion->id }}
              </option>
            @endforeach
          </select>
        </label>

        <label class="ui-checkbox ui-field-wide">
          <input type="checkbox" name="actualizar_ubicacion" value="1" {{ old('actualizar_ubicacion') ? 'checked' : '' }}>
          <span>Actualizar la ubicación actual del activo con la ubicación verificada</span>
        </label>

        <label class="ui-field-wide">
          <span>Observaciones</span>
          <textarea name="observaciones" placeholder="Describe el hallazgo, diferencia o condición observada">{{ old('observaciones') }}</textarea>
        </label>

        <label class="ui-field-wide">
          <span>Evidencias de inventario</span>
          <input type="file" name="evidencias[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
          <div class="ui-file-note" style="margin-top:6px;">
            Hasta 5 archivos JPG, PNG, WEBP o PDF de máximo 6 MB cada uno. Para “No encontrado” o “Diferencia de ubicación” se exige al menos una evidencia.
          </div>
        </label>

        <div class="ui-discrepancy-box {{ in_array($estatusInventario, ['no_encontrado', 'diferencia', 'pendiente'], true) ? 'is-visible' : '' }}" id="discrepancy-box">
          <label>
            <span>Notificar a Consulta / Auditoría</span>
            <select name="notificar_a" id="inventory-notify-user">
              <option value="">Seleccione...</option>
              @foreach($catalogos['usuariosContabilidad'] as $usuarioContabilidad)
                <option value="{{ $usuarioContabilidad->id }}" {{ (string) old('notificar_a') === (string) $usuarioContabilidad->id ? 'selected' : '' }}>
                  {{ $usuarioContabilidad->name }} · {{ $usuarioContabilidad->email }}
                </option>
              @endforeach
            </select>
            <div class="ui-help">SWAFI enviará un correo con activo, hallazgo, ubicación, evidencia y enlace al expediente. El resultado del envío quedará en bitácora.</div>
          </label>
        </div>
      </div>

      <div class="action-group" style="margin-top:12px;">
        <button class="tab" type="submit">Registrar inventario</button>
        <a class="tab" href="{{ route('ubicacion') }}">Limpiar</a>
      </div>
    </form>
  </div>
</section>

<section class="card ui-filter">
  <div class="section-title">
    <h2>Filtros de ubicación e inventario</h2>
    <span class="pill ok">Consulta paginada</span>
  </div>

  <form method="GET" action="{{ route('ubicacion') }}">
    <div class="query-grid query-grid-four">
      <label>
        <span>Número de activo</span>
        <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-537028">
      </label>

      <label>
        <span>Planta</span>
        <select name="planta_id">
          <option value="">Todas</option>
          @foreach($catalogos['plantas'] as $planta)
            <option value="{{ $planta->id }}" {{ (string) ($filtros['planta_id'] ?? '') === (string) $planta->id ? 'selected' : '' }}>{{ $planta->nombre }}</option>
          @endforeach
        </select>
      </label>

      <label>
        <span>Área</span>
        <select name="area_id">
          <option value="">Todas</option>
          @foreach($catalogos['areas'] as $area)
            <option value="{{ $area->id }}" {{ (string) ($filtros['area_id'] ?? '') === (string) $area->id ? 'selected' : '' }}>{{ $area->nombre }}</option>
          @endforeach
        </select>
      </label>

      <label>
        <span>Ubicación</span>
        <select name="ubicacion_id">
          <option value="">Todas</option>
          @foreach($catalogos['ubicaciones'] as $ubicacion)
            @php
              $filterLocationLabel = trim(($ubicacion->codigo_interno ? $ubicacion->codigo_interno . ' - ' : '') . ($ubicacion->descripcion ?? ''));
            @endphp
            <option value="{{ $ubicacion->id }}" {{ (string) ($filtros['ubicacion_id'] ?? '') === (string) $ubicacion->id ? 'selected' : '' }}>{{ $filterLocationLabel ?: 'Ubicación ' . $ubicacion->id }}</option>
          @endforeach
        </select>
      </label>
    </div>

    <div class="query-grid query-grid-four" style="margin-top:9px;">
      <label>
        <span>Responsable</span>
        <select name="responsable_id">
          <option value="">Todos</option>
          @foreach($catalogos['responsables'] as $responsable)
            <option value="{{ $responsable->id }}" {{ (string) ($filtros['responsable_id'] ?? '') === (string) $responsable->id ? 'selected' : '' }}>{{ $responsable->nombre }}</option>
          @endforeach
        </select>
      </label>

      <label>
        <span>Estatus operativo</span>
        <select name="estatus_operativo">
          <option value="">Todos</option>
          <option value="en_operacion" {{ ($filtros['estatus_operativo'] ?? '') === 'en_operacion' ? 'selected' : '' }}>En operación</option>
          <option value="traslado" {{ ($filtros['estatus_operativo'] ?? '') === 'traslado' ? 'selected' : '' }}>Traslado</option>
          <option value="baja" {{ ($filtros['estatus_operativo'] ?? '') === 'baja' ? 'selected' : '' }}>Baja</option>
        </select>
      </label>

      <label>
        <span>Estatus inventario</span>
        <select name="estatus_localizacion">
          <option value="">Todos</option>
          <option value="localizado" {{ ($filtros['estatus_localizacion'] ?? '') === 'localizado' ? 'selected' : '' }}>Localizado</option>
          <option value="no_encontrado" {{ ($filtros['estatus_localizacion'] ?? '') === 'no_encontrado' ? 'selected' : '' }}>No encontrado</option>
          <option value="diferencia" {{ ($filtros['estatus_localizacion'] ?? '') === 'diferencia' ? 'selected' : '' }}>Diferencia</option>
          <option value="pendiente" {{ ($filtros['estatus_localizacion'] ?? '') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
          <option value="sin_inventario" {{ ($filtros['estatus_localizacion'] ?? '') === 'sin_inventario' ? 'selected' : '' }}>Sin inventario</option>
        </select>
      </label>

      <label>
        <span>Registros por página</span>
        <select name="per_page">
          @foreach([10, 25, 50] as $size)
            <option value="{{ $size }}" {{ (string) ($filtros['per_page'] ?? 10) === (string) $size ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
      </label>
    </div>

    <div class="query-grid query-grid-four" style="margin-top:9px;">
      <label><span>Fecha desde</span><input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}"></label>
      <label><span>Fecha hasta</span><input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}"></label>
      <label>
        <span>Acciones</span>
        <div class="action-group">
          <button class="tab" type="submit">Consultar</button>
          <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
        </div>
      </label>
      <label>
        <span>Limpiar</span>
        <div class="action-group"><a class="tab" href="{{ route('ubicacion') }}">Limpiar filtros</a></div>
      </label>
    </div>
  </form>
</section>

<section class="card table-card" style="margin-top:16px;">
  <div class="section-title">
    <h2>Consulta de ubicación e inventario</h2>
    <span class="pill ok">Conectado a MySQL</span>
  </div>

  <table>
    <thead>
      <tr>
        <th>Activo</th>
        <th>Ubicación actual</th>
        <th>Responsable</th>
        <th>Último inventario</th>
        <th>Evidencia / notificación</th>
        <th>Último movimiento</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($resultados as $row)
        @php
          $ubicacionActual = array_filter([$row->ubicacion_codigo, $row->ubicacion_descripcion, $row->edificio, $row->piso, $row->pasillo]);
          $estatusFila = $row->estatus_localizacion ?? 'sin_inventario';
          $pillClass = 'danger';
          if ($estatusFila === 'localizado') $pillClass = 'ok';
          elseif ($estatusFila === 'pendiente' || $estatusFila === 'diferencia') $pillClass = 'warn';
          $estatusTexto = [
              'localizado' => 'Localizado',
              'no_encontrado' => 'No encontrado',
              'diferencia' => 'Diferencia',
              'pendiente' => 'Pendiente',
              'sin_inventario' => 'Sin inventario',
          ][$estatusFila] ?? ucfirst($estatusFila);
        @endphp
        <tr>
          <td><strong>{{ $row->numero_activo }}</strong><br><small>{{ $row->activo_descripcion }}</small></td>
          <td>{{ $ubicacionActual ? implode(' / ', $ubicacionActual) : 'Sin ubicación' }}<br><small>{{ $row->planta_nombre ?? 'Sin planta' }} · {{ $row->area_nombre ?? 'Sin área' }}</small></td>
          <td>{{ $row->responsable_nombre ?? 'Sin responsable' }}<br><small>{{ $row->responsable_correo ?? '' }}</small></td>
          <td>
            {{ $row->fecha_inventario ?? 'Sin inventario' }}<br>
            <span class="pill {{ $pillClass }}">{{ $estatusTexto }}</span><br>
            <small>{{ $row->inventario_observaciones ?? '' }}</small>
          </td>
          <td>
            <strong>{{ (int) ($row->total_evidencias ?? 0) }} evidencia(s)</strong><br>
            @if($row->notificado_at)
              <span class="ui-notification-state ok">Correo enviado</span><br><small>{{ $row->notificado_a_email }}</small>
            @elseif($row->notificacion_error)
              <span class="ui-notification-state warn">Correo fallido</span><br><small>{{ $row->notificado_a_email }}</small>
            @else
              <small>Sin notificación</small>
            @endif
          </td>
          <td>{{ $row->fecha_movimiento ?? 'Sin movimiento' }}<br><small>{{ $row->movimiento_motivo ?? '' }}</small></td>
          <td>
            <div class="table-actions">
              <a href="{{ route('busqueda', ['numero_activo' => $row->numero_activo]) }}">Buscar</a>
              <a href="{{ route('ubicacion', ['numero_activo' => $row->numero_activo]) }}">Filtrar</a>
              <a href="{{ route('activos.etiqueta', $row->numero_activo) }}" target="_blank" rel="noopener">Etiqueta QR</a>
              @if($row->expediente_id)
                <a href="{{ route('expediente', ['expediente' => $row->expediente_id, 'tab' => 'ubicacion']) }}">Detalle</a>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7">No existen activos con los criterios seleccionados.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="table-footer">
    <div class="table-summary">Mostrando {{ $resultados->firstItem() ?? 0 }}–{{ $resultados->lastItem() ?? 0 }} de {{ $resultados->total() }} resultados</div>
    <div class="table-pagination">
      @if($resultados->onFirstPage())
        <span class="page-link disabled">Anterior</span>
      @else
        <a class="page-link" href="{{ $resultados->previousPageUrl() }}">Anterior</a>
      @endif
      <span class="page-link active">{{ $resultados->currentPage() }}</span>
      @if($resultados->hasMorePages())
        <a class="page-link" href="{{ $resultados->nextPageUrl() }}">Siguiente</a>
      @else
        <span class="page-link disabled">Siguiente</span>
      @endif
    </div>
    <div class="table-page-size"><span>M02 inventario con evidencia y notificación</span></div>
  </div>
</section>

@endsection

@section('page_scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const status = document.getElementById('inventory-status');
    const box = document.getElementById('discrepancy-box');
    const recipient = document.getElementById('inventory-notify-user');

    if (!status || !box || !recipient) return;

    function syncDiscrepancyFields() {
      const discrepancy = ['no_encontrado', 'diferencia', 'pendiente'].includes(status.value);
      box.classList.toggle('is-visible', discrepancy);
      recipient.required = discrepancy;

      if (!discrepancy) recipient.value = '';
    }

    status.addEventListener('change', syncDiscrepancyFields);
    syncDiscrepancyFields();
  });
</script>
@endsection

@extends('layouts.app')

@section('title', 'Histórico de valores | SWAFI')
@section('page_title', 'Histórico fiscal y financiero')
@section('page_subtitle', 'Trazabilidad de altas, cambios, conciliaciones, bajas lógicas y restauraciones')
@section('breadcrumb', 'Valores fiscales y financieros / Histórico')

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
  .vh-shell,
  .vh-card,
  .vh-table-scroll {
    width: 100%;
    max-width: 100%;
    min-width: 0;
  }

  .vh-shell {
    display: grid;
    gap: 14px;
  }

  .vh-card {
    padding: 16px;
    border: 1px solid #dbe7f6;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
  }

  .vh-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    align-items: start;
  }

  .vh-hero h2 {
    margin: 0;
    color: #153761;
    font-size: 22px;
    font-weight: 950;
  }

  .vh-hero p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.55;
  }

  .vh-hero-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
  }

  .vh-pill {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #eef5ff;
    color: #174f9a;
    font-size: 12px;
    font-weight: 850;
  }

  .vh-pill.warn {
    background: #fff4df;
    color: #8a5100;
  }

  .vh-pill.danger {
    background: #feecec;
    color: #a52020;
  }

  .vh-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .vh-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 39px;
    padding: 9px 14px;
    border: 1px solid #d5e3f4;
    border-radius: 12px;
    background: #ffffff;
    color: #174f9a;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
  }

  .vh-button:hover,
  .vh-button:focus-visible {
    background: #edf5ff;
    outline: 3px solid rgba(23, 79, 154, .18);
    outline-offset: 2px;
  }

  .vh-button.primary {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
  }

  .vh-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .vh-kpi {
    padding: 13px;
    border: 1px solid #e0eaf6;
    border-radius: 16px;
    background: #f9fbff;
  }

  .vh-kpi span {
    display: block;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .vh-kpi strong {
    display: block;
    margin-top: 5px;
    color: #173b68;
    font-size: 18px;
    font-weight: 950;
  }

  .vh-current {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .vh-current-item {
    padding: 11px;
    border: 1px solid #e3ebf5;
    border-radius: 14px;
    background: #ffffff;
  }

  .vh-current-item span {
    display: block;
    margin-bottom: 4px;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
  }

  .vh-current-item strong {
    color: #183d69;
    font-size: 13px;
    font-weight: 900;
    overflow-wrap: anywhere;
  }

  .vh-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .vh-title h2 {
    margin: 0;
    color: #153761;
    font-size: 18px;
    font-weight: 950;
  }

  .vh-title p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
  }

  .vh-filters {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
  }

  .vh-field {
    min-width: 0;
  }

  .vh-field span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .vh-field input,
  .vh-field select {
    width: 100%;
    min-height: 40px;
    padding: 9px 10px;
    border: 1px solid #cfdced;
    border-radius: 11px;
    background: #ffffff;
    color: #1f2937;
    font: inherit;
    font-size: 13px;
  }

  .vh-field input:focus,
  .vh-field select:focus {
    border-color: #3b75bd;
    outline: 3px solid rgba(59, 117, 189, .14);
  }

  .vh-filter-actions {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    grid-column: 1 / -1;
  }

  .vh-errors {
    margin: 0 0 12px;
    padding: 12px 14px;
    border: 1px solid #f0b8b8;
    border-radius: 14px;
    background: #fff3f3;
    color: #8f1f1f;
    font-size: 13px;
  }

  .vh-errors ul {
    margin: 6px 0 0 18px;
    padding: 0;
  }

  .vh-table-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
  }

  .vh-table-head strong {
    color: #153761;
    font-size: 16px;
    font-weight: 950;
  }

  .vh-table-head span {
    color: #64748b;
    font-size: 12px;
  }

  .vh-table-scroll {
    overflow-x: auto;
    border: 1px solid #dae5f3;
    border-radius: 15px;
  }

  .vh-table {
    width: 100%;
    min-width: 1040px;
    border-collapse: collapse;
  }

  .vh-table th,
  .vh-table td {
    padding: 11px 12px;
    border-bottom: 1px solid #e4ebf4;
    text-align: left;
    vertical-align: top;
    font-size: 12px;
  }

  .vh-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #eef4fb;
    color: #21466f;
    font-weight: 950;
  }

  .vh-table tr:last-child td {
    border-bottom: 0;
  }

  .vh-action-badge {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
  }

  .vh-action-badge.ok {
    background: #e8f7ea;
    color: #1e6d2a;
  }

  .vh-action-badge.warn {
    background: #fff4dc;
    color: #8a5200;
  }

  .vh-action-badge.danger {
    background: #feecec;
    color: #a52020;
  }

  .vh-action-badge.info {
    background: #eaf4ff;
    color: #17528f;
  }

  .vh-user {
    color: #193f6c;
    font-weight: 850;
  }

  .vh-user small,
  .vh-date small {
    display: block;
    margin-top: 3px;
    color: #718096;
    font-weight: 500;
  }

  .vh-changes {
    display: grid;
    gap: 7px;
    min-width: 380px;
  }

  .vh-change {
    padding: 8px 9px;
    border: 1px solid #e0e8f2;
    border-radius: 11px;
    background: #fbfdff;
  }

  .vh-change strong {
    display: block;
    margin-bottom: 5px;
    color: #1a426f;
    font-size: 11px;
  }

  .vh-change-values {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px;
  }

  .vh-change-values span {
    display: block;
    padding: 6px 7px;
    border-radius: 8px;
    color: #374151;
    font-size: 11px;
    line-height: 1.4;
    overflow-wrap: anywhere;
  }

  .vh-before {
    background: #fff0f0;
  }

  .vh-after {
    background: #ecf8ee;
  }

  .vh-no-change {
    color: #64748b;
    font-style: italic;
  }

  .vh-details summary {
    color: #174f9a;
    font-weight: 900;
    cursor: pointer;
  }

  .vh-details-content {
    display: grid;
    gap: 7px;
    margin-top: 8px;
    color: #475569;
    font-size: 11px;
  }

  .vh-details-content code {
    display: block;
    max-width: 260px;
    padding: 7px;
    border-radius: 9px;
    background: #f3f6fa;
    color: #374151;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    white-space: normal;
    overflow-wrap: anywhere;
  }

  .vh-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
    color: #64748b;
    font-size: 12px;
  }

  .vh-pagination {
    display: flex;
    gap: 7px;
    align-items: center;
  }

  .vh-page-link {
    display: inline-flex;
    min-height: 34px;
    align-items: center;
    justify-content: center;
    padding: 7px 10px;
    border: 1px solid #d4e1f1;
    border-radius: 10px;
    background: #ffffff;
    color: #174f9a;
    font-weight: 850;
    text-decoration: none;
  }

  .vh-page-link.disabled {
    color: #94a3b8;
    background: #f4f6f9;
  }

  .vh-empty {
    padding: 28px 16px;
    color: #64748b;
    text-align: center;
  }

  @media (max-width: 1100px) {
    .vh-kpis,
    .vh-current {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vh-filters {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 720px) {
    .vh-card {
      padding: 13px;
      border-radius: 16px;
    }

    .vh-hero {
      grid-template-columns: 1fr;
    }

    .vh-actions {
      justify-content: flex-start;
    }

    .vh-kpis,
    .vh-current,
    .vh-filters {
      grid-template-columns: 1fr;
    }

    .vh-filter-actions,
    .vh-filter-actions .vh-button {
      width: 100%;
    }

    .vh-table-head,
    .vh-footer {
      align-items: flex-start;
      flex-direction: column;
    }

    .vh-change-values {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')
<div class="vh-shell">
  <section class="vh-card vh-hero" aria-labelledby="vh-asset-title">
    <div>
      <h2 id="vh-asset-title">{{ $activo->numero_activo }} · {{ $activo->descripcion }}</h2>
      <p>
        Consulta quién modificó los valores, cuándo ocurrió, qué información cambió y cuál fue
        el motivo registrado. Los documentos y datos históricos permanecen protegidos por los
        permisos del módulo M02.
      </p>

      <div class="vh-hero-meta">
        <span class="vh-pill">{{ $activo->planta_nombre ?: 'Sin planta' }}</span>
        <span class="vh-pill">{{ $activo->centro_costo_clave ?: 'Sin centro de costo' }}</span>
        <span class="vh-pill">{{ $activo->tipo_activo ?: 'Sin tipo de activo' }}</span>
        <span class="vh-pill {{ $activo->activo ? '' : 'danger' }}">
          {{ $activo->activo ? 'Activo vigente' : 'Activo inactivo' }}
        </span>
      </div>
    </div>

    <div class="vh-actions">
      <a class="vh-button" href="{{ route('valores', ['numero_activo' => $activo->numero_activo, 'panel' => 'consulta', 'swafi_focus' => 'swafi-valores-resultados']) }}">
        Volver a valores
      </a>
      <a class="vh-button primary" href="{{ route('expediente', ['expediente' => null, 'numero_activo' => $activo->numero_activo]) }}">
        Consultar expediente
      </a>
    </div>
  </section>

  <section class="vh-kpis" aria-label="Resumen del histórico">
    <article class="vh-kpi">
      <span>Eventos registrados</span>
      <strong>{{ number_format((int) ($resumen['total_eventos'] ?? 0)) }}</strong>
    </article>
    <article class="vh-kpi">
      <span>Personas participantes</span>
      <strong>{{ number_format((int) ($resumen['usuarios'] ?? 0)) }}</strong>
    </article>
    <article class="vh-kpi">
      <span>Primer evento</span>
      <strong>{{ !empty($resumen['primer_evento']) ? \Illuminate\Support\Carbon::parse($resumen['primer_evento'])->format('d/m/Y') : 'Sin eventos' }}</strong>
    </article>
    <article class="vh-kpi">
      <span>Último evento</span>
      <strong>{{ !empty($resumen['ultimo_evento']) ? \Illuminate\Support\Carbon::parse($resumen['ultimo_evento'])->format('d/m/Y H:i') : 'Sin eventos' }}</strong>
    </article>
  </section>

  <section class="vh-card" aria-labelledby="vh-current-title">
    <div class="vh-title">
      <div>
        <h2 id="vh-current-title">Situación actual</h2>
        <p>Resumen del registro vigente o de su última baja lógica.</p>
      </div>
    </div>

    @if($valorActual)
      <div class="vh-current">
        <div class="vh-current-item">
          <span>Valor fiscal</span>
          <strong>$ {{ number_format((float) $valorActual->valor_fiscal, 2) }} {{ $valorActual->moneda ?: 'MXN' }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Valor financiero</span>
          <strong>$ {{ number_format((float) $valorActual->valor_financiero, 2) }} {{ $valorActual->moneda ?: 'MXN' }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Depreciación / valor en libros</span>
          <strong>$ {{ number_format((float) $valorActual->depreciacion_acumulada, 2) }} / $ {{ number_format((float) $valorActual->valor_en_libros, 2) }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Estatus contable</span>
          <strong>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $valorActual->estatus_contable)) }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Conciliación CFDI</span>
          <strong>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($valorActual->conciliacion_cfdi ?: 'sin_xml'))) }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Fecha de corte</span>
          <strong>{{ $valorActual->fecha_corte ? \Illuminate\Support\Carbon::parse($valorActual->fecha_corte)->format('d/m/Y') : 'Sin fecha' }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Vida útil</span>
          <strong>{{ $valorActual->vida_util_meses ? ((int) $valorActual->vida_util_meses).' meses' : 'Sin definir' }}</strong>
        </div>
        <div class="vh-current-item">
          <span>Estado del registro</span>
          <strong>{{ $valorActual->deleted_at ? 'Baja lógica' : 'Vigente' }}</strong>
        </div>
      </div>
    @else
      <div class="vh-empty">El activo todavía no cuenta con valores fiscales o financieros registrados.</div>
    @endif
  </section>

  <section class="vh-card" data-swafi-query-panel>
    <div class="vh-title">
      <div>
        <h2>Filtros del histórico</h2>
        <p>Acota por acción, persona o periodo. Las validaciones también se ejecutan en el servidor.</p>
      </div>
    </div>

    @if($errors->any())
      <div class="vh-errors" role="alert">
        <strong>Revisa los criterios capturados:</strong>
        <ul>
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="GET" action="{{ route('valores.historial', $activo->numero_activo) }}" class="vh-filters">
      <input type="hidden" name="swafi_focus" value="swafi-valor-history-results">

      <label class="vh-field">
        <span>Acción</span>
        <select name="accion">
          <option value="">Todas las acciones</option>
          @foreach($accionesDisponibles as $accion)
            <option value="{{ $accion['value'] }}" {{ ($filtros['accion'] ?? '') === $accion['value'] ? 'selected' : '' }}>
              {{ $accion['label'] }}
            </option>
          @endforeach
        </select>
      </label>

      <label class="vh-field">
        <span>Usuario</span>
        <select name="usuario_id">
          <option value="">Todos los usuarios</option>
          @foreach($usuariosDisponibles as $usuario)
            <option value="{{ $usuario['id'] }}" {{ (string) ($filtros['usuario_id'] ?? '') === (string) $usuario['id'] ? 'selected' : '' }}>
              {{ $usuario['name'] }}
            </option>
          @endforeach
        </select>
      </label>

      <label class="vh-field">
        <span>Fecha inicial</span>
        <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
      </label>

      <label class="vh-field">
        <span>Fecha final</span>
        <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
      </label>

      <label class="vh-field">
        <span>Registros por página</span>
        <select name="per_page">
          @foreach([10, 25, 50] as $size)
            <option value="{{ $size }}" {{ (int) ($filtros['per_page'] ?? 10) === $size ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
      </label>

      <div class="vh-filter-actions">
        <button class="vh-button primary" type="submit">Consultar histórico</button>
        <a class="vh-button" href="{{ route('valores.historial', $activo->numero_activo) }}">Limpiar filtros</a>
      </div>
    </form>
  </section>

  <section class="vh-card" data-swafi-query-results id="swafi-valor-history-results" aria-labelledby="vh-results-title">
    <div class="vh-table-head">
      <strong id="vh-results-title">Eventos de valores fiscales y financieros</strong>
      <span>Mostrando {{ $historial->firstItem() ?? 0 }}–{{ $historial->lastItem() ?? 0 }} de {{ $historial->total() }}</span>
    </div>

    <div class="vh-table-scroll" tabindex="0" aria-label="Histórico de cambios de valores fiscales y financieros">
      <table class="vh-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Acción</th>
            <th>Usuario</th>
            <th>Cambios identificados</th>
            <th>Trazabilidad</th>
          </tr>
        </thead>
        <tbody>
          @forelse($historial as $evento)
            <tr>
              <td class="vh-date">
                <strong>{{ \Illuminate\Support\Carbon::parse($evento->fecha_evento)->format('d/m/Y') }}</strong>
                <small>{{ \Illuminate\Support\Carbon::parse($evento->fecha_evento)->format('H:i:s') }}</small>
              </td>
              <td>
                <span class="vh-action-badge {{ $evento->accion_class }}">{{ $evento->accion_label }}</span>
                <div><small>{{ $evento->accion }}</small></div>
              </td>
              <td class="vh-user">
                {{ $evento->usuario_visible }}
                <small>ID: {{ $evento->user_id ?: 'No disponible' }}</small>
              </td>
              <td>
                @if(count($evento->changes) > 0)
                  <div class="vh-changes">
                    @foreach($evento->changes as $change)
                      <div class="vh-change">
                        <strong>{{ $change['label'] }}</strong>
                        <div class="vh-change-values">
                          <span class="vh-before">Antes: {{ $change['before'] }}</span>
                          <span class="vh-after">Después: {{ $change['after'] }}</span>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @else
                  <span class="vh-no-change">El evento no contiene diferencias de campos de negocio o corresponde a una validación técnica.</span>
                @endif
              </td>
              <td>
                <details class="vh-details">
                  <summary>Ver referencia</summary>
                  <div class="vh-details-content">
                    <span>Registro: {{ $evento->registro_clave ?: 'Sin referencia' }}</span>
                    <span>Módulo: {{ $evento->modulo }}</span>
                    <span>IP: {{ $evento->ip ?: 'No disponible' }}</span>
                    <code>Evento SWAFI #{{ $evento->id }}</code>
                  </div>
                </details>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="vh-empty">No se encontraron eventos con los filtros seleccionados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="vh-footer">
      <div>Página {{ $historial->currentPage() }} de {{ max($historial->lastPage(), 1) }}</div>

      <div class="vh-pagination" aria-label="Paginación del histórico">
        @if($historial->onFirstPage())
          <span class="vh-page-link disabled" aria-disabled="true">Anterior</span>
        @else
          <a class="vh-page-link" href="{{ $historial->previousPageUrl() }}">Anterior</a>
        @endif

        @if($historial->hasMorePages())
          <a class="vh-page-link" href="{{ $historial->nextPageUrl() }}">Siguiente</a>
        @else
          <span class="vh-page-link disabled" aria-disabled="true">Siguiente</span>
        @endif
      </div>
    </div>
  </section>
</div>
@endsection

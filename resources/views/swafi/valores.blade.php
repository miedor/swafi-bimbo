@extends('layouts.app')

@section('title', 'Valores fiscales y financieros | SWAFI')
@section('page_title', 'Valores fiscales y financieros')
@section('page_subtitle', 'Control contable, moneda, tipo de cambio y conciliación contra CFDI')
@section('breadcrumb', 'Valores fiscales y financieros')

@php
  $panelSolicitado = (string) request('panel', '');
  $errorImportacion = $errors->has('archivo_csv');
  $errorCaptura = $errors->any() && !$errorImportacion;

  if (!$canAdministrarValores) {
    $panelActivo = 'consulta';
  } elseif ($valorEdit || $errorCaptura || $panelSolicitado === 'captura') {
    $panelActivo = 'captura';
  } elseif ($errorImportacion || $panelSolicitado === 'importar') {
    $panelActivo = 'importar';
  } else {
    $panelActivo = 'consulta';
  }

  $selectedAsset = old('numero_activo', $valorEdit->numero_activo ?? request('numero_activo', ''));
  $selectedCurrency = strtoupper((string) old('moneda', $valorEdit->moneda ?? 'MXN'));
  $selectedStatus = old('estatus_contable', $valorEdit->estatus_contable ?? 'vigente');
@endphp

@section('page_styles')
<style>
  .vf-shell,
  .vf-card,
  .vf-panel,
  .vf-table-scroll {
    width: 100%;
    max-width: 100%;
    min-width: 0;
  }

  .vf-shell {
    display: grid;
    gap: 14px;
    overflow-x: hidden;
  }

  .vf-card {
    padding: 16px;
    border: 1px solid #dbe7f6;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
    overflow: hidden;
  }

  .vf-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .vf-tab-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 9px 14px;
    border: 1px solid #d6e4f5;
    border-radius: 999px;
    background: #f7fbff;
    color: #174f9a;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, transform .15s ease;
  }

  .vf-tab-button:hover {
    transform: translateY(-1px);
    background: #edf5ff;
  }

  .vf-tab-button.is-active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(23, 79, 154, .18);
  }

  .vf-access-summary {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    border-radius: 999px;
    background: {{ $canAdministrarValores ? '#e8f7ea' : '#eef6ff' }};
    color: {{ $canAdministrarValores ? '#1f6b2a' : '#174f9a' }};
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
  }

  .vf-access-summary::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: currentColor;
  }

  .vf-panel {
    display: none;
  }

  .vf-panel.is-active {
    display: block;
  }

  .vf-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .vf-title h2 {
    margin: 0;
    color: #152f52;
    font-size: 18px;
    font-weight: 950;
  }

  .vf-title p {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.4;
  }

  .vf-form {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .vf-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .vf-field {
    min-width: 0;
  }

  .vf-field.full {
    grid-column: 1 / -1;
  }

  .vf-field.span-2 {
    grid-column: span 2;
  }

  .vf-field span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .vf-field input,
  .vf-field select,
  .vf-field textarea {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    min-height: 39px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
    font-weight: 750;
  }

  .vf-field textarea {
    min-height: 72px;
    resize: vertical;
  }

  .vf-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
  }

  .vf-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 9px 14px;
    border: 1px solid #174f9a;
    border-radius: 999px;
    background: #ffffff;
    color: #174f9a;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
  }

  .vf-button.primary {
    background: #174f9a;
    color: #ffffff;
  }

  .vf-button.soft {
    border-color: #d6e4f5;
    background: #eef5ff;
  }

  .vf-message {
    padding: 11px 13px;
    border-radius: 13px;
    font-weight: 800;
    line-height: 1.45;
  }

  .vf-message ul {
    margin: 7px 0 0;
    padding-left: 20px;
  }

  .vf-success {
    border: 1px solid #b9e5bf;
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .vf-error {
    border: 1px solid #facc15;
    background: #fff4d6;
    color: #7a4b00;
  }

  .vf-info,
  .vf-readonly {
    border: 1px solid #c8dcf7;
    background: #eef6ff;
    color: #174f9a;
  }

  .vf-readonly {
    padding: 11px 13px;
    border-radius: 13px;
    font-size: 12px;
    font-weight: 850;
    line-height: 1.45;
  }

  .vf-import-box {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(290px, .55fr);
    gap: 16px;
    align-items: start;
  }

  .vf-import-guide {
    padding: 14px;
    border: 1px dashed #b8cbe5;
    border-radius: 15px;
    background: #f8fbff;
  }

  .vf-import-guide h3 {
    margin: 0 0 7px;
    color: #152f52;
    font-size: 15px;
  }

  .vf-import-guide p,
  .vf-import-guide li {
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
  }

  .vf-import-guide ul {
    margin: 8px 0 0;
    padding-left: 18px;
  }

  .vf-table-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 14px 0 9px;
  }

  .vf-table-head strong {
    color: #152f52;
    font-size: 15px;
  }

  .vf-scroll-hint {
    color: #64748b;
    font-size: 11px;
    font-weight: 750;
  }

  .vf-table-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid #e2ebf6;
    border-radius: 16px;
    scrollbar-gutter: stable;
    overscroll-behavior-inline: contain;
    -webkit-overflow-scrolling: touch;
  }

  .vf-table-scroll table {
    width: 100%;
    min-width: 1110px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12px;
  }

  .vf-table-scroll th,
  .vf-table-scroll td {
    padding: 11px 10px;
    border-bottom: 1px solid #e7eef8;
    text-align: left;
    vertical-align: top;
    overflow-wrap: anywhere;
  }

  .vf-table-scroll th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f6faff;
    color: #48617f;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .vf-table-scroll th:nth-child(1) { width: 155px; }
  .vf-table-scroll th:nth-child(2) { width: 190px; }
  .vf-table-scroll th:nth-child(3) { width: 175px; }
  .vf-table-scroll th:nth-child(4) { width: 95px; }
  .vf-table-scroll th:nth-child(5) { width: 105px; }
  .vf-table-scroll th:nth-child(6) { width: 190px; }
  .vf-table-scroll th:nth-child(7) { width: 120px; }
  .vf-table-scroll th:nth-child(8) { width: 120px; }

  .vf-table-scroll tbody tr:hover {
    background: #fbfdff;
  }

  .vf-status {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 900;
  }

  .vf-status.ok {
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .vf-status.warn {
    background: #fff4d6;
    color: #8a4b00;
  }

  .vf-status.danger {
    background: #fff0ee;
    color: #b42318;
  }

  .vf-details {
    margin-top: 5px;
    color: #64748b;
    font-size: 10px;
    line-height: 1.35;
  }

  .vf-row-actions {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
  }

  .vf-row-actions a,
  .vf-row-actions button {
    border: 0;
    background: none;
    color: #174f9a;
    font: inherit;
    font-size: 11px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
    padding: 0;
  }

  .vf-row-actions button.danger {
    color: #b42318;
  }

  .vf-footer {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    color: #64748b;
    font-size: 11px;
  }

  .vf-footer > :last-child {
    text-align: right;
  }

  .vf-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }

  .vf-page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 7px 10px;
    border: 1px solid #d6e4f5;
    border-radius: 10px;
    background: #ffffff;
    color: #174f9a;
    font-weight: 900;
    text-decoration: none;
  }

  .vf-page-link.active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
  }

  .vf-page-link.disabled {
    color: #94a3b8;
    cursor: not-allowed;
  }

  @media (max-width: 1250px) {
    .vf-form,
    .vf-filters {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 980px) {
    .vf-form,
    .vf-filters {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .vf-import-box {
      grid-template-columns: minmax(0, 1fr);
    }

    .vf-footer {
      grid-template-columns: minmax(0, 1fr);
      justify-items: start;
    }

    .vf-footer > :last-child {
      text-align: left;
    }
  }

  @media (max-width: 680px) {
    .vf-card {
      padding: 13px;
      border-radius: 16px;
    }

    .vf-tabs,
    .vf-actions {
      align-items: stretch;
    }

    .vf-tab-button,
    .vf-button {
      flex: 1 1 100%;
    }

    .vf-access-summary {
      width: 100%;
      margin-left: 0;
      justify-content: center;
    }

    .vf-form,
    .vf-filters {
      grid-template-columns: minmax(0, 1fr);
    }

    .vf-field.span-2,
    .vf-field.full {
      grid-column: auto;
    }
  }
</style>
@endsection

@section('content')
<div class="vf-shell" data-values-page data-active-panel="{{ $panelActivo }}">
  @if(session('success'))
    <div class="vf-message vf-success">{{ session('success') }}</div>
  @endif

  @if(session('import_summary'))
    @php
      $summary = session('import_summary');
    @endphp
    <div class="vf-message vf-info">
      <strong>Carga masiva:</strong>
      {{ $summary['procesados'] ?? 0 }} procesados,
      {{ $summary['insertados'] ?? 0 }} insertados,
      {{ $summary['actualizados'] ?? 0 }} actualizados y
      {{ $summary['rechazados'] ?? 0 }} rechazados.

      @if(!empty($summary['errores']))
        <ul>
          @foreach(array_slice($summary['errores'], 0, 15) as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      @endif
    </div>
  @endif

  @if($errors->any())
    <div class="vf-message vf-error">
      <strong>Corrige los siguientes datos:</strong>
      <ul>
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @unless($canAdministrarValores)
    <div class="vf-readonly">
      @if($canViewSensitiveValues)
        Tu perfil cuenta con consulta fiscal y financiera completa en modo de solo lectura. La creación, edición, carga masiva y eliminación permanece reservada para Administrador SWAFI y Usuario Captura.
      @else
        Tu perfil cuenta con consulta operativa básica. Por seguridad, SWAFI oculta montos, proveedor, factura, moneda, tipo de cambio, historial y exportaciones fiscales o financieras.
      @endif
    </div>
  @endunless

  <section class="vf-card">
    <div class="vf-tabs" role="tablist" aria-label="Opciones de valores fiscales y financieros">
      <button
        type="button"
        class="vf-tab-button {{ $panelActivo === 'consulta' ? 'is-active' : '' }}"
        data-vf-tab="consulta"
        role="tab"
        aria-selected="{{ $panelActivo === 'consulta' ? 'true' : 'false' }}"
      >
        Consulta y resultados
      </button>

      @if($canAdministrarValores)
        <button
          type="button"
          class="vf-tab-button {{ $panelActivo === 'captura' ? 'is-active' : '' }}"
          data-vf-tab="captura"
          role="tab"
          aria-selected="{{ $panelActivo === 'captura' ? 'true' : 'false' }}"
        >
          {{ $valorEdit ? 'Editar valores' : 'Captura contable' }}
        </button>

        <button
          type="button"
          class="vf-tab-button {{ $panelActivo === 'importar' ? 'is-active' : '' }}"
          data-vf-tab="importar"
          role="tab"
          aria-selected="{{ $panelActivo === 'importar' ? 'true' : 'false' }}"
        >
          Carga masiva
        </button>
      @endif

      <span class="vf-access-summary">
        {{ $canAdministrarValores ? 'Administración autorizada' : 'Consulta autorizada' }}
      </span>
    </div>
  </section>

  <section
    class="vf-card vf-panel {{ $panelActivo === 'consulta' ? 'is-active' : '' }}"
    data-vf-panel="consulta"
    data-swafi-query-workspace
    data-swafi-query-key="valores"
    role="tabpanel"
  >
    <div data-swafi-query-panel>
    <div class="vf-title">
      <div>
        <h2>Filtros de consulta</h2>
        <p>
          @if($canViewSensitiveValues)
            Localiza activos por estructura organizacional, estatus, conciliación, moneda, fecha o valor.
          @else
            Localiza activos por estructura organizacional y estatus, sin exponer información fiscal o financiera sensible.
          @endif
        </p>
      </div>
      <span class="pill ok">{{ $resultados->total() }} resultado(s)</span>
    </div>

    <form method="GET" action="{{ route('valores') }}" data-swafi-query-form>
      <input type="hidden" name="panel" value="consulta">

      <div class="vf-filters">
        <label class="vf-field">
          <span>Número de activo</span>
          <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Planta</span>
          <select name="planta_id">
            <option value="">Todas</option>
            @foreach($catalogos['plantas'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['planta_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
            @endforeach
          </select>
        </label>

        @if($canViewSensitiveValues)
        <label class="vf-field">
          <span>Proveedor</span>
          <select name="proveedor_id">
            <option value="">Todos</option>
            @foreach($catalogos['proveedores'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['proveedor_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
            @endforeach
          </select>
        </label>

        @endif

        <label class="vf-field">
          <span>Centro de costo</span>
          <select name="centro_costo_id">
            <option value="">Todos</option>
            @foreach($catalogos['centrosCosto'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['centro_costo_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->clave }}</option>
            @endforeach
          </select>
        </label>

        <label class="vf-field">
          <span>Tipo de activo</span>
          <select name="tipo_activo_id">
            <option value="">Todos</option>
            @foreach($catalogos['tiposActivo'] as $item)
              <option value="{{ $item->id }}" {{ (string)($filtros['tipo_activo_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->descripcion }}</option>
            @endforeach
          </select>
        </label>

        <label class="vf-field">
          <span>Estatus contable</span>
          <select name="estatus_contable">
            <option value="">Todos</option>
            <option value="vigente" {{ ($filtros['estatus_contable'] ?? '') === 'vigente' ? 'selected' : '' }}>Vigente</option>
            <option value="en_revision" {{ ($filtros['estatus_contable'] ?? '') === 'en_revision' ? 'selected' : '' }}>En revisión</option>
            <option value="baja" {{ ($filtros['estatus_contable'] ?? '') === 'baja' ? 'selected' : '' }}>Baja</option>
          </select>
        </label>

        <label class="vf-field">
          <span>Conciliación CFDI</span>
          <select name="conciliacion_cfdi">
            <option value="">Todas</option>
            <option value="validado" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'validado' ? 'selected' : '' }}>Validado</option>
            <option value="observado" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'observado' ? 'selected' : '' }}>Observado</option>
            <option value="sin_xml" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'sin_xml' ? 'selected' : '' }}>Sin XML validado</option>
          </select>
        </label>

        @if($canViewSensitiveValues)
        <label class="vf-field">
          <span>Moneda</span>
          <input name="moneda" maxlength="3" value="{{ $filtros['moneda'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Fecha desde</span>
          <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Fecha hasta</span>
          <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Valor desde</span>
          <input type="number" step="0.01" name="valor_desde" value="{{ $filtros['valor_desde'] ?? '' }}">
        </label>

        <label class="vf-field">
          <span>Valor hasta</span>
          <input type="number" step="0.01" name="valor_hasta" value="{{ $filtros['valor_hasta'] ?? '' }}">
        </label>

        @endif

        <label class="vf-field">
          <span>Registros por página</span>
          <select name="per_page">
            @foreach([10, 25, 50, 100] as $size)
              <option value="{{ $size }}" {{ (string)($filtros['per_page'] ?? 10) === (string)$size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
          </select>
        </label>
      </div>

      <div class="vf-actions">
        <button class="vf-button primary" type="submit">Consultar</button>

        @if($canExportarValores)
          <button class="vf-button" type="submit" name="export" value="csv">Exportar CSV</button>
        @endif

        <a class="vf-button soft" href="{{ route('valores', ['panel' => 'consulta']) }}">Limpiar filtros</a>
      </div>
    </form>
    </div>

    <div class="vf-table-head" data-swafi-query-results id="swafi-valores-resultados">
      <strong>Valores registrados</strong>
      <span class="vf-scroll-hint">La tabla se desplaza dentro de este panel sin mover la página completa.</span>
    </div>

    <div class="vf-table-scroll" tabindex="0" aria-label="Tabla de valores fiscales y financieros">
      <table>
        <thead>
          @if($canViewSensitiveValues)
            <tr>
              <th>Activo / factura</th>
              <th>Proveedor / planta</th>
              <th>Valores</th>
              <th>Moneda</th>
              <th>Contable</th>
              <th>Conciliación CFDI</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          @else
            <tr>
              <th>Activo</th>
              <th>Ubicación / clasificación</th>
              <th>Contable</th>
              <th>Conciliación documental</th>
              <th>Fecha de corte</th>
              <th>Acciones</th>
            </tr>
          @endif
        </thead>
        <tbody>
          @forelse($resultados as $row)
            @php
              $conciliation = $row->conciliacion_cfdi ?: 'sin_xml';
              $conciliationClass = $conciliation === 'validado' ? 'ok' : ($conciliation === 'observado' ? 'warn' : 'danger');
              $accountClass = $row->estatus_contable === 'vigente' ? 'ok' : ($row->estatus_contable === 'en_revision' ? 'warn' : 'danger');
              $detail = [];

              if ($canViewSensitiveValues) {
                $detail = is_string($row->conciliacion_detalle) ? json_decode($row->conciliacion_detalle, true) : $row->conciliacion_detalle;
                $detail = is_array($detail) ? $detail : [];
              }
            @endphp

            @if($canViewSensitiveValues)
              <tr>
                <td>
                  <strong>{{ $row->numero_activo }}</strong><br>
                  <small>{{ $row->activo_descripcion }}</small><br>
                  <small>{{ $row->folio_factura ?: 'Sin folio' }}</small>
                </td>
                <td>
                  {{ $row->proveedor_nombre ?: 'Sin proveedor' }}<br>
                  <small>{{ $row->planta_nombre ?: 'Sin planta' }} · {{ $row->centro_costo_clave ?: 'Sin CC' }}</small>
                </td>
                <td>
                  Fiscal: ${{ number_format((float)$row->valor_fiscal, 2) }}<br>
                  Financiero: ${{ number_format((float)$row->valor_financiero, 2) }}<br>
                  <small>Libros: ${{ number_format((float)$row->valor_en_libros, 2) }}</small>
                </td>
                <td>
                  {{ $row->moneda ?: 'MXN' }}<br>
                  <small>TC: {{ $row->tipo_cambio ? number_format((float)$row->tipo_cambio, 6) : 'N/A' }}</small>
                </td>
                <td>
                  <span class="vf-status {{ $accountClass }}">{{ ucfirst(str_replace('_', ' ', $row->estatus_contable)) }}</span>
                </td>
                <td>
                  <span class="vf-status {{ $conciliationClass }}">{{ ucfirst(str_replace('_', ' ', $conciliation)) }}</span>
                  <div class="vf-details">{{ implode(' ', array_slice($detail, 0, 2)) }}</div>
                </td>
                <td>
                  {{ $row->fecha_corte }}<br>
                  <small>{{ $row->updated_at }}</small>
                </td>
                <td>
                  <div class="vf-row-actions">
                    @if($row->expediente_id)
                      <a href="{{ route('expediente', $row->expediente_id) }}">Consultar</a>
                    @endif

                    <a href="{{ route('valores.historial', $row->numero_activo) }}">Historial</a>

                    @if($canExportarExcel)
                      <a
                        href="{{ route('valores.exportar-ficha', ['numeroActivo' => $row->numero_activo, 'formato' => 'xlsx']) }}"
                        aria-label="Exportar ficha fiscal y financiera del activo {{ $row->numero_activo }} a Excel"
                      >Ficha Excel</a>
                    @endif

                    @if($canExportarPdf)
                      <a
                        href="{{ route('valores.exportar-ficha', ['numeroActivo' => $row->numero_activo, 'formato' => 'pdf']) }}"
                        aria-label="Exportar ficha fiscal y financiera del activo {{ $row->numero_activo }} a PDF"
                      >Ficha PDF</a>
                    @endif

                    @if($canAdministrarValores)
                      <a href="{{ route('valores', array_merge(request()->query(), ['panel' => 'captura', 'editar_valor' => $row->valor_id])) }}">Editar</a>

                      <form
                        method="POST"
                        action="{{ route('valores.destroy', $row->valor_id) }}"
                        onsubmit="return confirm('¿Dar de baja lógicamente los valores del activo? El registro se conservará para auditoría y el Dashboard lo marcará como pendiente.');"
                      >
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="motivo_baja" value="Baja lógica solicitada desde el módulo de valores.">
                        <button class="danger" type="submit">Dar de baja</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @else
              <tr>
                <td>
                  <strong>{{ $row->numero_activo }}</strong><br>
                  <small>{{ $row->activo_descripcion }}</small>
                </td>
                <td>
                  {{ $row->planta_nombre ?: 'Sin planta' }} · {{ $row->centro_costo_clave ?: 'Sin CC' }}<br>
                  <small>{{ $row->tipo_activo ?: 'Sin clasificación' }}</small>
                </td>
                <td>
                  <span class="vf-status {{ $accountClass }}">{{ ucfirst(str_replace('_', ' ', $row->estatus_contable)) }}</span>
                </td>
                <td>
                  <span class="vf-status {{ $conciliationClass }}">{{ ucfirst(str_replace('_', ' ', $conciliation)) }}</span>
                </td>
                <td>
                  {{ $row->fecha_corte ?: 'Sin fecha' }}<br>
                  <small>{{ $row->updated_at }}</small>
                </td>
                <td>
                  <div class="vf-row-actions">
                    @if($row->expediente_id)
                      <a href="{{ route('expediente', $row->expediente_id) }}">Consultar expediente</a>
                    @else
                      <span>Sin expediente relacionado</span>
                    @endif
                  </div>
                </td>
              </tr>
            @endif
          @empty
            <tr>
              <td colspan="{{ $canViewSensitiveValues ? 8 : 6 }}">No existen valores con los filtros seleccionados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="vf-footer">
      <div>Mostrando {{ $resultados->firstItem() ?? 0 }}–{{ $resultados->lastItem() ?? 0 }} de {{ $resultados->total() }}</div>

      <div class="vf-pagination">
        @if($resultados->onFirstPage())
          <span class="vf-page-link disabled">Anterior</span>
        @else
          <a class="vf-page-link" href="{{ $resultados->appends(['panel' => 'consulta'])->previousPageUrl() }}">Anterior</a>
        @endif

        <span class="vf-page-link active">{{ $resultados->currentPage() }}</span>

        @if($resultados->hasMorePages())
          <a class="vf-page-link" href="{{ $resultados->appends(['panel' => 'consulta'])->nextPageUrl() }}">Siguiente</a>
        @else
          <span class="vf-page-link disabled">Siguiente</span>
        @endif
      </div>

      <div>Consulta controlada por rol</div>
    </div>
  </section>

  @if($canAdministrarValores)
    <section
      class="vf-card vf-panel {{ $panelActivo === 'captura' ? 'is-active' : '' }}"
      data-vf-panel="captura"
      role="tabpanel"
    >
      <div class="vf-title">
        <div>
          <h2>{{ $valorEdit ? 'Editar valores del activo' : 'Captura contable' }}</h2>
          <p>Los valores se validan y concilian contra el XML CFDI vigente del expediente.</p>
        </div>
        <span class="pill ok">Edición autorizada</span>
      </div>

      <form method="POST" action="{{ route('valores.store') }}">
        @csrf

        @if($valorEdit)
          <input type="hidden" name="valor_id" value="{{ $valorEdit->valor_id }}">
        @endif

        <div class="vf-form">
          <label class="vf-field span-2">
            <span>Activo fijo</span>
            <select name="numero_activo" required>
              <option value="">Seleccione...</option>
              @foreach($catalogos['activos'] as $activo)
                <option value="{{ $activo->numero_activo }}" {{ $selectedAsset === $activo->numero_activo ? 'selected' : '' }}>{{ $activo->numero_activo }} — {{ $activo->descripcion }}</option>
              @endforeach
            </select>
          </label>

          <label class="vf-field">
            <span>Valor fiscal</span>
            <input type="number" step="0.01" min="0" name="valor_fiscal" value="{{ old('valor_fiscal', $valorEdit->valor_fiscal ?? '') }}" required>
          </label>

          <label class="vf-field">
            <span>Valor financiero</span>
            <input type="number" step="0.01" min="0" name="valor_financiero" value="{{ old('valor_financiero', $valorEdit->valor_financiero ?? '') }}" required>
          </label>

          <label class="vf-field">
            <span>Moneda</span>
            <input name="moneda" maxlength="3" value="{{ $selectedCurrency }}" placeholder="MXN" required>
          </label>

          <label class="vf-field">
            <span>Tipo de cambio</span>
            <input type="number" step="0.000001" min="0" name="tipo_cambio" value="{{ old('tipo_cambio', $valorEdit->tipo_cambio ?? ($selectedCurrency === 'MXN' ? '1' : '')) }}">
          </label>

          <label class="vf-field">
            <span>Fecha tipo de cambio</span>
            <input type="date" name="fecha_tipo_cambio" value="{{ old('fecha_tipo_cambio', !empty($valorEdit->fecha_tipo_cambio) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_tipo_cambio)->format('Y-m-d') : '') }}">
          </label>

          <label class="vf-field">
            <span>Origen tipo de cambio</span>
            <input name="origen_tipo_cambio" value="{{ old('origen_tipo_cambio', $valorEdit->origen_tipo_cambio ?? '') }}" placeholder="Ej. CFDI / fuente corporativa">
          </label>

          <label class="vf-field">
            <span>Depreciación acumulada</span>
            <input type="number" step="0.01" min="0" name="depreciacion_acumulada" value="{{ old('depreciacion_acumulada', $valorEdit->depreciacion_acumulada ?? '0.00') }}" required>
          </label>

          <label class="vf-field">
            <span>Valor en libros</span>
            <input type="number" step="0.01" min="0" name="valor_en_libros" value="{{ old('valor_en_libros', $valorEdit->valor_en_libros ?? '') }}" placeholder="Se calcula si queda vacío">
          </label>

          <label class="vf-field">
            <span>Vida útil (meses)</span>
            <input type="number" min="1" max="1200" name="vida_util_meses" value="{{ old('vida_util_meses', $valorEdit->vida_util_meses ?? '') }}">
          </label>

          <label class="vf-field">
            <span>Fecha de corte</span>
            <input type="date" name="fecha_corte" value="{{ old('fecha_corte', !empty($valorEdit->fecha_corte) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_corte)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
          </label>

          <label class="vf-field">
            <span>Estatus contable</span>
            <select name="estatus_contable" required>
              <option value="vigente" {{ $selectedStatus === 'vigente' ? 'selected' : '' }}>Vigente</option>
              <option value="en_revision" {{ $selectedStatus === 'en_revision' ? 'selected' : '' }}>En revisión</option>
              <option value="baja" {{ $selectedStatus === 'baja' ? 'selected' : '' }}>Baja</option>
            </select>
          </label>

          <label class="vf-field full">
            <span>Motivo del cambio {{ $valorEdit ? '(obligatorio)' : '' }}</span>
            <textarea name="motivo_cambio" placeholder="Describe el origen, ajuste o conciliación realizada.">{{ old('motivo_cambio', '') }}</textarea>
          </label>
        </div>

        <div class="vf-actions">
          <button class="vf-button primary" type="submit">{{ $valorEdit ? 'Actualizar y conciliar' : 'Guardar y conciliar' }}</button>
          <a class="vf-button soft" href="{{ route('valores', ['panel' => 'captura']) }}">Limpiar captura</a>
          <button class="vf-button" type="button" data-vf-open="consulta">Regresar a consulta</button>
        </div>
      </form>
    </section>

    <section
      class="vf-card vf-panel {{ $panelActivo === 'importar' ? 'is-active' : '' }}"
      data-vf-panel="importar"
      role="tabpanel"
    >
      <div class="vf-title">
        <div>
          <h2>Carga masiva con conciliación CFDI</h2>
          <p>Procesa valores en volumen sin generar duplicados por número de activo.</p>
        </div>
        <span class="pill ok">Importación autorizada</span>
      </div>

      <div class="vf-import-box">
        <div class="vf-import-guide">
          <h3>Reglas de importación</h3>
          <p>SWAFI valida moneda, tipo de cambio, montos, fechas, estatus, duplicidad y consistencia contra el XML vigente.</p>
          <ul>
            <li>Las filas rechazadas no alteran registros correctos.</li>
            <li>Un activo existente se actualiza; no se duplica.</li>
            <li>Los valores vigentes deben ser mayores a cero.</li>
            <li>La plantilla oficial conserva los encabezados esperados.</li>
          </ul>
        </div>

        <form method="POST" action="{{ route('valores.importar') }}" enctype="multipart/form-data">
          @csrf

          <label class="vf-field">
            <span>Archivo CSV</span>
            <input type="file" name="archivo_csv" accept=".csv,.txt" required>
          </label>

          <div class="vf-actions">
            <button class="vf-button primary" type="submit">Importar CSV</button>
            <a class="vf-button soft" href="{{ route('valores.plantilla') }}">Descargar plantilla</a>
          </div>
        </form>
      </div>
    </section>
  @endif
</div>
@endsection

@section('page_scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('[data-values-page]');

    if (!page) {
      return;
    }

    const tabs = Array.from(page.querySelectorAll('[data-vf-tab]'));
    const panels = Array.from(page.querySelectorAll('[data-vf-panel]'));
    const openButtons = Array.from(page.querySelectorAll('[data-vf-open]'));

    function activatePanel(panelName, updateUrl) {
      const targetExists = panels.some(function (panel) {
        return panel.dataset.vfPanel === panelName;
      });

      if (!targetExists) {
        panelName = 'consulta';
      }

      tabs.forEach(function (tab) {
        const active = tab.dataset.vfTab === panelName;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.dataset.vfPanel === panelName);
      });

      page.dataset.activePanel = panelName;

      if (updateUrl && window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.set('panel', panelName);
        window.history.replaceState({}, '', url.toString());
      }
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activatePanel(tab.dataset.vfTab, true);
      });
    });

    openButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        activatePanel(button.dataset.vfOpen, true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });

    activatePanel(page.dataset.activePanel || 'consulta', false);
  });
</script>
@endsection

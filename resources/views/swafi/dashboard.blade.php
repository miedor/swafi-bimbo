@extends('layouts.app')

@section('title', 'Dashboard principal | SWAFI')
@section('page_title', 'Dashboard principal')
@section('page_subtitle', 'Vista ejecutiva del control documental, financiero y operativo')
@section('breadcrumb', 'Dashboard')

@section('page_styles')
<style>
  .dash-exec-shell {
    display: grid;
    gap: 12px;
  }

  .dash-top-row {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 12px;
    align-items: stretch;
  }

  .dash-health-mini {
    display: grid;
    gap: 10px;
    padding: 14px;
    border: 1px solid #dbe7f6;
    border-radius: 22px;
    background:
      radial-gradient(circle at 0% 0%, rgba(34, 197, 94, .13), transparent 32%),
      linear-gradient(135deg, #ffffff 0%, #f5f9ff 100%);
    box-shadow: 0 12px 26px rgba(15, 23, 42, .06);
  }

  .dash-health-title {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
  }

  .dash-health-title span {
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
    line-height: 1.15;
  }

  .dash-health-title strong {
    color: #12345c;
    font-size: 30px;
    font-weight: 950;
    letter-spacing: -1px;
    line-height: 1;
  }

  .dash-health-bar {
    height: 12px;
    overflow: hidden;
    border-radius: 999px;
    background: #e5edf7;
  }

  .dash-health-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #1f7a3d, #22c55e);
  }

  .dash-health-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
  }

  .dash-health-meta div {
    padding: 8px;
    border: 1px solid #dfe9f7;
    border-radius: 14px;
    background: #ffffff;
  }

  .dash-health-meta span {
    display: block;
    color: #64748b;
    font-size: 10.5px;
    font-weight: 850;
  }

  .dash-health-meta strong {
    display: block;
    margin-top: 2px;
    color: #12345c;
    font-size: 17px;
    font-weight: 950;
  }

  .dash-filter-compact {
    padding: 14px;
    border: 1px solid #dbe7f6;
    border-radius: 22px;
    background: #ffffff;
    box-shadow: 0 12px 26px rgba(15, 23, 42, .06);
  }

  .dash-filter-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    margin-bottom: 10px;
  }

  .dash-filter-head h2 {
    margin: 0;
    color: #152f52;
    font-size: 17px;
    font-weight: 950;
  }

  .dash-filter-form {
    display: grid;
    grid-template-columns: minmax(200px, 1.1fr) minmax(140px, .8fr) minmax(140px, .8fr) auto;
    gap: 10px;
    align-items: end;
  }

  .dash-field span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 11.5px;
    font-weight: 900;
  }

  .dash-field input,
  .dash-field select {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 12px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
    font-weight: 750;
  }

  .dash-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
  }

  .dash-actions .tab {
    min-height: 38px;
    padding: 9px 13px;
  }

  .dash-kpi-row {
    display: grid;
    grid-template-columns: repeat(6, minmax(130px, 1fr));
    gap: 10px;
  }

  .dash-kpi {
    position: relative;
    overflow: hidden;
    min-height: 86px;
    padding: 12px;
    border: 1px solid #dbe7f6;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 10px 22px rgba(15, 23, 42, .05);
  }

  .dash-kpi::after {
    content: "";
    position: absolute;
    right: -22px;
    top: -28px;
    width: 70px;
    height: 70px;
    border-radius: 999px;
    background: #f0f6ff;
  }

  .dash-kpi span {
    position: relative;
    z-index: 1;
    display: block;
    color: #64748b;
    font-size: 11.5px;
    font-weight: 850;
    line-height: 1.18;
  }

  .dash-kpi strong {
    position: relative;
    z-index: 1;
    display: block;
    margin-top: 7px;
    color: #12345c;
    font-size: 23px;
    font-weight: 950;
    letter-spacing: -.6px;
    line-height: 1;
  }

  .dash-kpi small {
    position: relative;
    z-index: 1;
    display: block;
    margin-top: 6px;
    color: #174f9a;
    font-size: 11px;
    font-weight: 850;
    line-height: 1.15;
  }

  .dash-kpi.ok small {
    color: #1f7a3d;
  }

  .dash-kpi.warn small {
    color: #9a5b00;
  }

  .dash-kpi.danger small {
    color: #b42318;
  }

  .dash-tabs-card {
    padding: 12px;
  }

  .dash-tabbar {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    border-bottom: 1px solid #e3edf8;
  }

  .dash-tab-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 13px;
    border: 1px solid #d7e4f4;
    border-radius: 999px;
    background: #ffffff;
    color: #174f9a;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
    transition: all .16s ease;
  }

  .dash-tab-button:hover {
    background: #eef5ff;
    transform: translateY(-1px);
  }

  .dash-tab-button.is-active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
    box-shadow: 0 10px 22px rgba(23, 79, 154, .18);
  }

  .dash-tab-panel {
    display: none;
    padding-top: 12px;
  }

  .dash-tab-panel.is-active {
    display: block;
  }

  .dash-panel-grid {
    display: grid;
    grid-template-columns: .86fr 1.14fr;
    gap: 12px;
    align-items: start;
  }

  .dash-panel {
    min-height: 274px;
    padding: 14px;
    border: 1px solid #dbe7f6;
    border-radius: 20px;
    background: #ffffff;
  }

  .dash-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 11px;
  }

  .dash-panel-header h3 {
    margin: 0;
    color: #152f52;
    font-size: 16px;
    font-weight: 950;
  }

  .dash-scroll-panel {
    max-height: 228px;
    overflow-y: auto;
    padding-right: 4px;
  }

  .dash-scroll-panel::-webkit-scrollbar,
  .dash-table-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .dash-scroll-panel::-webkit-scrollbar-thumb,
  .dash-table-wrap::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #c8d7ea;
  }

  .dash-progress-list {
    display: grid;
    gap: 10px;
  }

  .dash-progress-item {
    display: grid;
    gap: 6px;
  }

  .dash-progress-title {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #183458;
    font-size: 12px;
    font-weight: 900;
  }

  .dash-progress-title span:first-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .dash-progress-track {
    height: 11px;
    overflow: hidden;
    border-radius: 999px;
    background: #e8eef7;
  }

  .dash-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #1f5aa6, #4f8fe8);
  }

  .dash-progress-bar.ok {
    background: linear-gradient(90deg, #1f7a3d, #22c55e);
  }

  .dash-progress-bar.warn {
    background: linear-gradient(90deg, #d97706, #fbbf24);
  }

  .dash-progress-bar.danger {
    background: linear-gradient(90deg, #b42318, #ef4444);
  }

  .dash-table-wrap {
    width: 100%;
    max-height: 292px;
    overflow: auto;
    border: 1px solid #e5edf8;
    border-radius: 18px;
  }

  .dash-table-wrap table {
    min-width: 1040px;
  }

  .dash-table-wrap th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f7fbff;
  }

  .dash-asset-cell strong {
    display: block;
    color: #14355f;
    font-weight: 950;
  }

  .dash-mini {
    color: #64748b;
    font-size: 12px;
    line-height: 1.28;
  }

  .dash-issues {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
  }

  .dash-issue {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 8px;
    border-radius: 999px;
    background: #fff4d6;
    color: #8a4b00;
    border: 1px solid #f9d36a;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
  }

  .dash-issue.danger {
    background: #fff0ee;
    color: #b42318;
    border-color: #fecaca;
  }

  .dash-issue.ok {
    background: #e8f7ea;
    color: #1f6b2a;
    border-color: #b9e5bf;
  }

  .dash-list-compact {
    display: grid;
    gap: 8px;
  }

  .dash-list-row {
    display: grid;
    gap: 3px;
    padding: 10px 11px;
    border: 1px solid #e3edf8;
    border-radius: 15px;
    background: #f9fbff;
  }

  .dash-list-row strong {
    color: #14355f;
    font-size: 13px;
    font-weight: 950;
    line-height: 1.22;
  }

  .dash-list-row span {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.25;
  }

  .dash-quick-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(150px, 1fr));
    gap: 10px;
  }

  .dash-quick-link {
    display: grid;
    gap: 4px;
    padding: 13px;
    border: 1px solid #dbe7f6;
    border-radius: 18px;
    background: #ffffff;
    color: #174f9a;
    text-decoration: none;
    transition: all .16s ease;
  }

  .dash-quick-link:hover {
    background: #f2f7ff;
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(15, 23, 42, .06);
  }

  .dash-quick-link strong {
    color: #14355f;
    font-size: 13px;
    font-weight: 950;
  }

  .dash-quick-link span {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.25;
  }

  .dash-empty {
    padding: 15px;
    border: 1px dashed #cbd8ea;
    border-radius: 14px;
    background: #f8fbff;
    color: #64748b;
    font-size: 13px;
    font-weight: 850;
  }

  @media (max-width: 1360px) {
    .dash-top-row {
      grid-template-columns: 1fr;
    }

    .dash-kpi-row {
      grid-template-columns: repeat(3, minmax(140px, 1fr));
    }
  }

  @media (max-width: 1100px) {
    .dash-filter-form {
      grid-template-columns: 1fr 1fr;
    }

    .dash-panel-grid {
      grid-template-columns: 1fr;
    }

    .dash-quick-grid {
      grid-template-columns: repeat(2, minmax(150px, 1fr));
    }
  }

  @media (max-width: 760px) {
    .dash-filter-form,
    .dash-kpi-row,
    .dash-health-meta,
    .dash-quick-grid {
      grid-template-columns: 1fr;
    }

    .dash-actions {
      width: 100%;
    }

    .dash-actions .tab {
      width: 100%;
      justify-content: center;
    }
  }
</style>
@endsection

@section('content')

@php
    $roles = session('swafi_roles', []);
    $permissions = session('swafi_permissions', []);

    $can = function (string $permission) use ($roles, $permissions): bool {
        if (in_array('Administrador SWAFI', $roles, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    };

    $estatusClass = function (?string $estatus): string {
        $estatus = (string) $estatus;

        if ($estatus === 'completo') {
            return 'ok';
        }

        if ($estatus === 'observado') {
            return 'warn';
        }

        if ($estatus === 'incompleto') {
            return 'danger';
        }

        return 'warn';
    };

    $statusText = function (?string $estatus): string {
        $estatus = (string) $estatus;

        return $estatus !== ''
            ? ucfirst($estatus)
            : 'Sin estatus';
    };

    $formatMoneyCompact = function ($amount): string {
        $amount = (float) $amount;

        if ($amount >= 1000000) {
            return '$ ' . number_format($amount / 1000000, 2) . ' M';
        }

        if ($amount >= 1000) {
            return '$ ' . number_format($amount / 1000, 1) . ' K';
        }

        return '$ ' . number_format($amount, 2);
    };

    $motivosAtencion = function ($item): array {
        $motivos = [];

        if (($item->estatus ?? '') === 'incompleto') {
            $motivos[] = ['texto' => 'Expediente incompleto', 'clase' => 'danger'];
        }

        if (($item->estatus ?? '') === 'observado') {
            $motivos[] = ['texto' => 'Expediente observado', 'clase' => 'danger'];
        }

        if ((int) ($item->total_pdf ?? 0) === 0) {
            $motivos[] = ['texto' => 'Falta PDF', 'clase' => 'danger'];
        }

        if ((int) ($item->total_xml ?? 0) === 0) {
            $motivos[] = ['texto' => 'Falta XML', 'clase' => 'danger'];
        } elseif ((int) ($item->total_xml_validados ?? 0) < (int) ($item->total_xml_cfdi ?? 0)) {
            $motivos[] = ['texto' => 'XML CFDI sin validar', 'clase' => 'danger'];
        }

        if ((int) ($item->total_cfdi_inconsistentes ?? 0) > 0) {
            $motivos[] = ['texto' => 'CFDI con inconsistencias', 'clase' => 'danger'];
        }

        if (empty($item->ubicacion_id)) {
            $motivos[] = ['texto' => 'Falta ubicación', 'clase' => ''];
        }

        if ((int) ($item->total_valores ?? 0) === 0) {
            $motivos[] = ['texto' => 'Falta valores fiscales/financieros', 'clase' => ''];
        } elseif ((int) ($item->total_valores_conciliados ?? 0) === 0) {
            $motivos[] = ['texto' => 'Valores sin conciliar con CFDI', 'clase' => ''];
        }

        if (in_array(($item->estatus_localizacion ?? ''), ['no_encontrado', 'diferencia', 'pendiente'], true)) {
            $inventarioTexto = [
                'no_encontrado' => 'Activo no encontrado en inventario',
                'diferencia' => 'Diferencia de ubicación en inventario',
                'pendiente' => 'Inventario pendiente de revisión',
            ][$item->estatus_localizacion] ?? 'Discrepancia de inventario';

            $motivos[] = ['texto' => $inventarioTexto, 'clase' => 'danger'];
        }

        if (empty($motivos)) {
            $motivos[] = ['texto' => 'Sin corrección pendiente', 'clase' => 'ok'];
        }

        return $motivos;
    };

    $filtros = $filtros ?? [];
    $totalEstatus = max((int) ($kpis['total_expedientes'] ?? 0), 1);
    $porcentajeCompletos = (float) ($kpis['porcentaje_completos'] ?? 0);
    $porcentajePendientes = (float) ($kpis['porcentaje_incompletos'] ?? 0);
    $hasFilters = filled($filtros['planta_id'] ?? null) || filled($filtros['fecha_desde'] ?? null) || filled($filtros['fecha_hasta'] ?? null);
@endphp

@if ($errors->any())
    <div data-swafi-query-validation-errors style="margin-bottom:14px;padding:12px 14px;border-radius:14px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="dash-exec-shell" data-swafi-query-workspace data-swafi-query-key="dashboard">

  <div class="dash-top-row" data-swafi-query-panel>
    <section class="dash-health-mini">
      <div class="dash-health-title">
        <span>Salud documental</span>
        <strong>{{ number_format($porcentajeCompletos, 1) }}%</strong>
      </div>

      <div class="dash-health-bar">
        <div class="dash-health-fill" style="width: {{ min(max($porcentajeCompletos, 0), 100) }}%"></div>
      </div>

      <div class="dash-health-meta">
        <div>
          <span>Completos</span>
          <strong>{{ number_format((int) $kpis['expedientes_completos']) }}</strong>
        </div>

        <div>
          <span>Atención</span>
          <strong>{{ number_format((int) $kpis['total_atencion']) }}</strong>
        </div>
      </div>
    </section>

    <section class="dash-filter-compact">
      <div class="dash-filter-head">
        <h2>Filtros ejecutivos</h2>
        <span class="pill {{ $hasFilters ? 'warn' : 'ok' }}">
          {{ $hasFilters ? 'Filtro aplicado' : 'Conectado a MySQL' }}
        </span>
      </div>

      <form method="GET" action="{{ route('dashboard') }}" class="dash-filter-form" data-swafi-query-form>
        <label class="dash-field">
          <span>Planta</span>
          <select name="planta_id">
            <option value="">Todas las plantas</option>
            @foreach($catalogos['plantas'] as $planta)
              @php
                $plantaSeleccionada = (string) ($filtros['planta_id'] ?? '');
              @endphp
              <option value="{{ $planta->id }}" {{ $plantaSeleccionada === (string) $planta->id ? 'selected' : '' }}>
                {{ $planta->nombre }}
              </option>
            @endforeach
          </select>
        </label>

        <label class="dash-field">
          <span>Fecha desde</span>
          <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
        </label>

        <label class="dash-field">
          <span>Fecha hasta</span>
          <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </label>

        <label class="dash-field">
          <span>Acciones</span>
          <div class="dash-actions">
            <button class="tab" type="submit">Actualizar</button>
            <a class="tab" href="{{ route('dashboard') }}">Limpiar</a>
          </div>
        </label>
      </form>
    </section>
  </div>

  <div class="dash-kpi-row" data-swafi-query-results id="swafi-dashboard-resultados">
    <div class="dash-kpi">
      <span>Activos registrados</span>
      <strong>{{ number_format((int) $kpis['total_activos']) }}</strong>
      <small>Vigentes en SWAFI</small>
    </div>

    <div class="dash-kpi ok">
      <span>Expedientes completos</span>
      <strong>{{ number_format((int) $kpis['expedientes_completos']) }}</strong>
      <small>{{ number_format($porcentajeCompletos, 1) }}% del total</small>
    </div>

    <div class="dash-kpi {{ $kpis['total_atencion'] > 0 ? 'warn' : 'ok' }}">
      <span>Requieren atención</span>
      <strong>{{ number_format((int) $kpis['total_atencion']) }}</strong>
      <small>Documentos, valores o ubicación</small>
    </div>

    <div class="dash-kpi">
      <span>Monto registrado</span>
      <strong>{{ $formatMoneyCompact($kpis['monto_total']) }}</strong>
      <small>Suma de facturas</small>
    </div>

    <div class="dash-kpi {{ (($kpis['xml_sin_validar'] ?? 0) + ($kpis['cfdi_con_inconsistencias'] ?? 0)) > 0 ? 'warn' : 'ok' }}">
      <span>PDF/XML vigentes</span>
      <strong>{{ number_format((int) $kpis['documentos_pdf']) }}/{{ number_format((int) $kpis['documentos_xml']) }}</strong>
      <small>Sin validar: {{ number_format((int) ($kpis['xml_sin_validar'] ?? 0)) }} · Con inconsistencias: {{ number_format((int) ($kpis['cfdi_con_inconsistencias'] ?? 0)) }}</small>
    </div>

    <div class="dash-kpi">
      <span>Eventos auditoría</span>
      <strong>{{ number_format((int) $kpis['eventos_auditoria']) }}</strong>
      <small>Trazabilidad registrada</small>
    </div>
  </div>

  <section class="card dash-tabs-card">
    <div class="dash-tabbar" role="tablist" aria-label="Secciones del dashboard">
      <button type="button" class="dash-tab-button is-active" data-dashboard-tab="seguimiento">
        Seguimiento prioritario
      </button>
      <button type="button" class="dash-tab-button" data-dashboard-tab="resumen">
        Resumen operativo
      </button>
      <button type="button" class="dash-tab-button" data-dashboard-tab="documentos">
        Documentos y auditoría
      </button>
      <button type="button" class="dash-tab-button" data-dashboard-tab="accesos">
        Accesos rápidos
      </button>
    </div>

    <div class="dash-tab-panel is-active" data-dashboard-panel="seguimiento">
      <div class="dash-panel">
        <div class="dash-panel-header">
          <h3>Expedientes que requieren atención</h3>
          <span class="pill warn">Corrección requerida visible</span>
        </div>

        <div class="dash-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Activo</th>
                <th>Factura</th>
                <th>Proveedor / Planta</th>
                <th>Estatus</th>
                <th>Corrección requerida</th>
                <th>Documentos</th>
                <th>Acciones</th>
              </tr>
            </thead>

            <tbody>
              @forelse($expedientesAtencion as $item)
                <tr>
                  <td class="dash-asset-cell">
                    <strong>{{ $item->numero_activo }}</strong>
                    <span class="dash-mini">{{ $item->activo_descripcion }}</span>
                  </td>

                  <td>
                    {{ $item->folio_factura }}<br>
                    <span class="dash-mini">{{ $item->fecha_factura ?: 'Sin fecha' }}</span>
                  </td>

                  <td>
                    {{ $item->proveedor_nombre ?: 'Sin proveedor' }}<br>
                    <span class="dash-mini">{{ $item->planta_nombre ?: 'Sin planta' }}</span>
                  </td>

                  <td>
                    <span class="pill {{ $estatusClass($item->estatus) }}">
                      {{ $statusText($item->estatus) }}
                    </span>
                  </td>

                  <td>
                    <div class="dash-issues">
                      @foreach($motivosAtencion($item) as $motivo)
                        <span class="dash-issue {{ $motivo['clase'] }}">
                          {{ $motivo['texto'] }}
                        </span>
                      @endforeach
                    </div>
                  </td>

                  <td>
                    PDF: {{ ((int) $item->total_pdf) > 0 ? 'Sí' : 'No' }}<br>
                    XML: {{ ((int) $item->total_xml) > 0 ? 'Sí' : 'No' }}<br>
                    <span class="dash-mini">
                      Valores: {{ ((int) $item->total_valores) > 0 ? 'Sí' : 'No' }}<br>
                      CFDI validado: {{ ((int) ($item->total_cfdi_validos ?? 0)) > 0 ? 'Sí' : 'No' }}<br>
                      Inventario: {{ $item->estatus_localizacion ? ucfirst(str_replace('_', ' ', $item->estatus_localizacion)) : 'Sin registro' }}
                    </span>
                  </td>

                  <td>
                    <div class="table-actions">
                      @if($can('expedientes.ver'))
                        <a href="{{ route('expediente', $item->expediente_id) }}">Consultar</a>
                      @endif

                      @if($can('expedientes.editar'))
                        <a href="{{ route('expedientes.editar', $item->expediente_id) }}">Editar</a>
                      @endif

                      @if($can('valores.administrar') && ((int) $item->total_valores) === 0)
                        <a href="{{ route('valores', ['numero_activo' => $item->numero_activo]) }}">Valores</a>
                      @endif

                      @if($can('ubicaciones.administrar') && (empty($item->ubicacion_id) || in_array(($item->estatus_localizacion ?? ''), ['no_encontrado', 'diferencia', 'pendiente'], true)))
                        <a href="{{ route('ubicacion', ['numero_activo' => $item->numero_activo]) }}">Ubicación / Inventario</a>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7">
                    No hay expedientes pendientes con los filtros seleccionados.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="dash-tab-panel" data-dashboard-panel="resumen">
      <div class="dash-panel-grid">
        <div class="dash-panel">
          <div class="dash-panel-header">
            <h3>Estado documental</h3>

            @if ($can('reportes.exportar'))
              <a class="pill ok" href="{{ route('reportes', array_filter([
                'tipo_reporte' => 'expedientes_documentales',
                'planta_id' => $filtros['planta_id'] ?? null,
                'fecha_desde' => $filtros['fecha_desde'] ?? null,
                'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
              ])) }}">Ver reportes</a>
            @endif
          </div>

          <div class="dash-scroll-panel">
            <div class="dash-progress-list">
              @forelse($estatusDocumental as $estatus)
                @php
                  $porcentaje = round(((int) $estatus->total / $totalEstatus) * 100, 1);
                  $barClass = $estatusClass($estatus->estatus);
                @endphp

                <div class="dash-progress-item">
                  <div class="dash-progress-title">
                    <span>{{ $statusText($estatus->estatus) }}</span>
                    <span>{{ number_format((int) $estatus->total) }} · {{ number_format($porcentaje, 1) }}%</span>
                  </div>
                  <div class="dash-progress-track">
                    <div class="dash-progress-bar {{ $barClass }}" style="width: {{ max($porcentaje, 2) }}%"></div>
                  </div>
                </div>
              @empty
                <div class="dash-empty">
                  No existen expedientes para graficar con los filtros seleccionados.
                </div>
              @endforelse
            </div>
          </div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-header">
            <h3>Activos por planta</h3>
            <span class="pill ok">Distribución operativa</span>
          </div>

          <div class="dash-scroll-panel">
            <div class="dash-progress-list">
              @php
                $maxPlanta = max((int) ($activosPorPlanta->max('total') ?? 0), 1);
              @endphp

              @forelse($activosPorPlanta as $planta)
                @php
                  $porcentajePlanta = round(((int) $planta->total / $maxPlanta) * 100, 1);
                @endphp

                <div class="dash-progress-item">
                  <div class="dash-progress-title">
                    <span>{{ $planta->planta_nombre }}</span>
                    <span>{{ number_format((int) $planta->total) }} activo(s)</span>
                  </div>
                  <div class="dash-progress-track">
                    <div class="dash-progress-bar ok" style="width: {{ max($porcentajePlanta, 2) }}%"></div>
                  </div>
                </div>
              @empty
                <div class="dash-empty">
                  No hay activos registrados por planta.
                </div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="dash-tab-panel" data-dashboard-panel="documentos">
      <div class="dash-panel-grid">
        <div class="dash-panel">
          <div class="dash-panel-header">
            <h3>Últimos documentos cargados</h3>
            <span class="pill ok">PDF/XML vigentes</span>
          </div>

          <div class="dash-scroll-panel">
            <div class="dash-list-compact">
              @forelse($ultimosDocumentos as $documento)
                <div class="dash-list-row">
                  <strong>{{ $documento->tipo_documento }} · {{ $documento->nombre_archivo }}</strong>
                  <span>{{ $documento->numero_activo }} · {{ $documento->folio_factura }} · v{{ $documento->version }}</span>
                </div>
              @empty
                <div class="dash-empty">
                  Aún no existen documentos vigentes cargados.
                </div>
              @endforelse
            </div>
          </div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-header">
            <h3>Actividad reciente</h3>

            @if ($can('bitacora.ver'))
              <a class="pill ok" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">Ver bitácora</a>
            @else
              <span class="pill ok">Trazabilidad</span>
            @endif
          </div>

          <div class="dash-scroll-panel">
            <div class="dash-list-compact">
              @forelse($actividadReciente as $evento)
                <div class="dash-list-row">
                  <strong>{{ $evento->accion }} · {{ $evento->modulo }}</strong>
                  <span>
                    {{ $evento->fecha_evento }}
                    · {{ $evento->usuario_nombre ?: ($evento->usuario_email ?: 'Usuario no identificado') }}
                    @if($evento->numero_activo)
                      · {{ $evento->numero_activo }}
                    @endif
                  </span>
                </div>
              @empty
                <div class="dash-empty">
                  Aún no hay actividad registrada en bitácora.
                </div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="dash-tab-panel" data-dashboard-panel="accesos">
      <div class="dash-panel">
        <div class="dash-panel-header">
          <h3>Accesos rápidos permitidos</h3>
          <span class="pill warn">Filtrado por rol</span>
        </div>

        <div class="dash-quick-grid">
          @if ($can('expedientes.crear'))
            <a class="dash-quick-link" href="{{ route('registro-individual') }}">
              <strong>Registro individual</strong>
              <span>Alta directa de expediente PDF/XML.</span>
            </a>

            <a class="dash-quick-link" href="{{ route('registro-masivo') }}">
              <strong>Registro masivo</strong>
              <span>Layout CSV y ZIP documental.</span>
            </a>
          @endif

          @if ($can('valores.ver') || $can('valores.administrar'))
            <a class="dash-quick-link" href="{{ route('valores') }}">
              <strong>Valores fiscales</strong>
              <span>Control financiero y fiscal del activo.</span>
            </a>
          @endif

          @if ($can('ubicaciones.administrar'))
            <a class="dash-quick-link" href="{{ route('ubicacion') }}">
              <strong>Ubicación e inventario</strong>
              <span>Seguimiento físico por planta o área.</span>
            </a>
          @endif

          @if ($can('expedientes.ver'))
            <a class="dash-quick-link" href="{{ route('busqueda') }}">
              <strong>Búsqueda avanzada</strong>
              <span>Consulta por activo, proveedor, fecha o estatus.</span>
            </a>

            <a class="dash-quick-link" href="{{ route('expediente') }}">
              <strong>Detalle expediente</strong>
              <span>Vista integral documental y patrimonial.</span>
            </a>
          @endif

          @if ($can('reportes.exportar'))
            <a class="dash-quick-link" href="{{ route('reportes') }}">
              <strong>Reportes ad hoc</strong>
              <span>Exportación para seguimiento y análisis.</span>
            </a>
          @endif

          @if ($can('catalogos.ver') || $can('catalogos.administrar'))
            <a class="dash-quick-link" href="{{ route('catalogos') }}">
              <strong>Catálogos base</strong>
              <span>Consulta de proveedores, plantas y datos maestros.</span>
            </a>
          @endif

          @if ($can('seguridad.administrar'))
            <a class="dash-quick-link" href="{{ route('seguridad', ['tab' => 'usuarios']) }}">
              <strong>Seguridad y acceso</strong>
              <span>Usuarios, roles y permisos del sistema.</span>
            </a>
          @endif

          @if ($can('bitacora.ver'))
            <a class="dash-quick-link" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">
              <strong>Bitácora</strong>
              <span>Trazabilidad de acciones relevantes.</span>
            </a>
          @endif
        </div>
      </div>
    </div>
  </section>
</div>

@endsection

@section('page_scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('[data-dashboard-tab]');
    const panels = document.querySelectorAll('[data-dashboard-panel]');

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        const target = button.getAttribute('data-dashboard-tab');

        buttons.forEach(function (item) {
          item.classList.remove('is-active');
        });

        panels.forEach(function (panel) {
          panel.classList.remove('is-active');
        });

        button.classList.add('is-active');

        const activePanel = document.querySelector('[data-dashboard-panel="' + target + '"]');

        if (activePanel) {
          activePanel.classList.add('is-active');
        }
      });
    });
  });
</script>
@endsection

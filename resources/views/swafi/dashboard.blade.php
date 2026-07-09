@extends('layouts.app')

@section('title', 'Dashboard principal | SWAFI')
@section('page_title', 'Dashboard principal')
@section('page_subtitle', 'Resumen ejecutivo del control documental, financiero y operativo')
@section('breadcrumb', 'Dashboard')

@section('page_styles')
<style>
  .dash-compact-shell {
    display: grid;
    gap: 14px;
  }

  .dash-hero {
    display: grid;
    grid-template-columns: minmax(270px, 360px) 1fr;
    gap: 14px;
    align-items: stretch;
  }

  .dash-hero-card {
    position: relative;
    overflow: hidden;
    min-height: 188px;
    border: 1px solid #dbe7f6;
    border-radius: 24px;
    background:
      radial-gradient(circle at 12% 18%, rgba(47, 116, 214, .22), transparent 32%),
      linear-gradient(135deg, #ffffff 0%, #f5f9ff 52%, #edf5ff 100%);
    box-shadow: 0 16px 36px rgba(15, 23, 42, .08);
  }

  .dash-hero-card::after {
    content: "";
    position: absolute;
    right: -60px;
    top: -80px;
    width: 210px;
    height: 210px;
    border-radius: 999px;
    background: rgba(31, 90, 166, .08);
  }

  .dash-hero-main {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 118px 1fr;
    gap: 18px;
    align-items: center;
    height: 100%;
    padding: 22px;
  }

  .dash-ring {
    --value: 0;
    width: 112px;
    height: 112px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background:
      radial-gradient(circle closest-side, #ffffff 68%, transparent 70% 100%),
      conic-gradient(#1f7a3d calc(var(--value) * 1%), #dbe7f6 0);
    box-shadow: inset 0 0 0 1px #dce8f6, 0 12px 24px rgba(15, 23, 42, .08);
  }

  .dash-ring strong {
    color: #12345c;
    font-size: 25px;
    font-weight: 950;
    letter-spacing: -.7px;
  }

  .dash-hero-title {
    margin: 0 0 6px;
    color: #122a4a;
    font-size: 20px;
    font-weight: 950;
    line-height: 1.05;
  }

  .dash-hero-text {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 750;
    line-height: 1.35;
  }

  .dash-hero-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  .dash-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 28px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid #d9e6f7;
    background: #ffffff;
    color: #174f9a;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
  }

  .dash-chip::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: currentColor;
  }

  .dash-filter-card {
    padding: 18px;
  }

  .dash-filter-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
  }

  .dash-filter-header h2 {
    margin: 0;
    color: #152f52;
    font-size: 18px;
    font-weight: 950;
  }

  .dash-filter-form {
    display: grid;
    grid-template-columns: minmax(220px, 1.2fr) minmax(150px, .85fr) minmax(150px, .85fr) auto;
    gap: 10px;
    align-items: end;
  }

  .dash-field span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
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
  }

  .dash-top-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(150px, 1fr));
    gap: 12px;
  }

  .dash-kpi-pro {
    position: relative;
    overflow: hidden;
    min-height: 118px;
    padding: 16px;
    border: 1px solid #dbe7f6;
    border-radius: 22px;
    background: #ffffff;
    box-shadow: 0 13px 28px rgba(15, 23, 42, .06);
  }

  .dash-kpi-pro::after {
    content: "";
    position: absolute;
    right: -24px;
    top: -24px;
    width: 82px;
    height: 82px;
    border-radius: 999px;
    background: #f0f6ff;
  }

  .dash-kpi-pro .dash-kpi-label {
    position: relative;
    z-index: 1;
    color: #64748b;
    font-size: 12px;
    font-weight: 850;
  }

  .dash-kpi-pro .dash-kpi-value {
    position: relative;
    z-index: 1;
    margin-top: 7px;
    color: #12345c;
    font-size: 28px;
    font-weight: 950;
    letter-spacing: -.9px;
    line-height: 1;
  }

  .dash-kpi-pro .dash-kpi-status {
    position: relative;
    z-index: 1;
    margin-top: 7px;
    color: #174f9a;
    font-size: 12px;
    font-weight: 850;
  }

  .dash-kpi-pro.warn .dash-kpi-status {
    color: #9a5b00;
  }

  .dash-kpi-pro.danger .dash-kpi-status {
    color: #b42318;
  }

  .dash-kpi-pro.ok .dash-kpi-status {
    color: #1f7a3d;
  }

  .dash-micro-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(130px, 1fr));
    gap: 10px;
  }

  .dash-micro {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    min-height: 58px;
    padding: 10px 12px;
    border: 1px solid #dfe9f7;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 10px 22px rgba(15, 23, 42, .045);
  }

  .dash-micro span {
    color: #64748b;
    font-size: 11px;
    font-weight: 850;
    line-height: 1.15;
  }

  .dash-micro strong {
    color: #12345c;
    font-size: 18px;
    font-weight: 950;
    text-align: right;
    white-space: nowrap;
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
    min-height: 38px;
    padding: 9px 14px;
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
    padding-top: 14px;
  }

  .dash-tab-panel.is-active {
    display: block;
  }

  .dash-panel-grid {
    display: grid;
    grid-template-columns: .9fr 1.1fr;
    gap: 14px;
    align-items: start;
  }

  .dash-panel-grid-three {
    display: grid;
    grid-template-columns: .85fr 1.15fr 1fr;
    gap: 14px;
    align-items: start;
  }

  .dash-panel {
    min-height: 304px;
    padding: 15px;
    border: 1px solid #dbe7f6;
    border-radius: 20px;
    background: #ffffff;
  }

  .dash-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
  }

  .dash-panel-header h3 {
    margin: 0;
    color: #152f52;
    font-size: 16px;
    font-weight: 950;
  }

  .dash-scroll-panel {
    max-height: 258px;
    overflow-y: auto;
    padding-right: 4px;
  }

  .dash-scroll-panel::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .dash-scroll-panel::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #c8d7ea;
  }

  .dash-progress-list {
    display: grid;
    gap: 11px;
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
    height: 12px;
    overflow: hidden;
    border-radius: 999px;
    background: #e8eef7;
  }

  .dash-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #1f5aa6, #4f8fe8);
  }

  .dash-progress-bar.warn {
    background: linear-gradient(90deg, #d97706, #fbbf24);
  }

  .dash-progress-bar.danger {
    background: linear-gradient(90deg, #b42318, #ef4444);
  }

  .dash-progress-bar.ok {
    background: linear-gradient(90deg, #1f7a3d, #22c55e);
  }

  .dash-table-scroll {
    width: 100%;
    overflow: auto;
    max-height: 258px;
    border: 1px solid #e5edf8;
    border-radius: 16px;
  }

  .dash-table-scroll table {
    min-width: 760px;
  }

  .dash-table-scroll th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f7fbff;
  }

  .dash-mini {
    color: #64748b;
    font-size: 12px;
    line-height: 1.35;
  }

  .dash-empty {
    padding: 16px;
    border: 1px dashed #cbd8ea;
    border-radius: 14px;
    background: #f8fbff;
    color: #64748b;
    font-size: 13px;
    font-weight: 850;
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
    padding: 14px;
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

  @media (max-width: 1360px) {
    .dash-hero {
      grid-template-columns: 1fr;
    }

    .dash-micro-grid {
      grid-template-columns: repeat(3, minmax(140px, 1fr));
    }

    .dash-panel-grid-three {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 1100px) {
    .dash-filter-form {
      grid-template-columns: 1fr 1fr;
    }

    .dash-top-kpis {
      grid-template-columns: repeat(2, minmax(160px, 1fr));
    }

    .dash-panel-grid {
      grid-template-columns: 1fr;
    }

    .dash-quick-grid {
      grid-template-columns: repeat(2, minmax(150px, 1fr));
    }
  }

  @media (max-width: 760px) {
    .dash-hero-main {
      grid-template-columns: 1fr;
      text-align: center;
      justify-items: center;
    }

    .dash-filter-form,
    .dash-top-kpis,
    .dash-micro-grid,
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

    $filtros = $filtros ?? [];
    $totalEstatus = max((int) ($kpis['total_expedientes'] ?? 0), 1);
    $porcentajeCompletos = (float) ($kpis['porcentaje_completos'] ?? 0);
    $porcentajePendientes = (float) ($kpis['porcentaje_incompletos'] ?? 0);
    $hasFilters = filled($filtros['planta_id'] ?? null) || filled($filtros['fecha_desde'] ?? null) || filled($filtros['fecha_hasta'] ?? null);
@endphp

@if ($errors->any())
    <div style="margin-bottom:14px;padding:12px 14px;border-radius:14px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="dash-compact-shell">

  <div class="dash-hero">
    <section class="dash-hero-card">
      <div class="dash-hero-main">
        <div class="dash-ring" style="--value: {{ min(max($porcentajeCompletos, 0), 100) }};">
          <strong>{{ number_format($porcentajeCompletos, 1) }}%</strong>
        </div>

        <div>
          <h2 class="dash-hero-title">Salud documental SWAFI</h2>
          <p class="dash-hero-text">
            Porcentaje de expedientes completos con PDF/XML vigente y datos integrados.
            El objetivo operativo es reducir pendientes documentales y mejorar trazabilidad por activo fijo.
          </p>

          <div class="dash-hero-pills">
            <span class="dash-chip">{{ number_format((int) $kpis['total_expedientes']) }} expedientes</span>
            <span class="dash-chip">{{ number_format((int) $kpis['expedientes_completos']) }} completos</span>
            <span class="dash-chip">{{ number_format((int) $kpis['eventos_auditoria']) }} eventos</span>
          </div>
        </div>
      </div>
    </section>

    <section class="card dash-filter-card">
      <div class="dash-filter-header">
        <h2>Filtros ejecutivos</h2>
        <span class="pill {{ $hasFilters ? 'warn' : 'ok' }}">
          {{ $hasFilters ? 'Filtro aplicado' : 'Conectado a MySQL' }}
        </span>
      </div>

      <form method="GET" action="{{ route('dashboard') }}" class="dash-filter-form">
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
          <span>Desde</span>
          <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
        </label>

        <label class="dash-field">
          <span>Hasta</span>
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

  <div class="dash-top-kpis">
    <div class="dash-kpi-pro">
      <div class="dash-kpi-label">Activos registrados</div>
      <div class="dash-kpi-value">{{ number_format((int) $kpis['total_activos']) }}</div>
      <div class="dash-kpi-status">Activos vigentes en SWAFI</div>
    </div>

    <div class="dash-kpi-pro ok">
      <div class="dash-kpi-label">Expedientes completos</div>
      <div class="dash-kpi-value">{{ number_format((int) $kpis['expedientes_completos']) }}</div>
      <div class="dash-kpi-status">{{ number_format($porcentajeCompletos, 1) }}% del total filtrado</div>
    </div>

    <div class="dash-kpi-pro {{ $kpis['expedientes_incompletos'] > 0 ? 'danger' : 'ok' }}">
      <div class="dash-kpi-label">Pendientes documentales</div>
      <div class="dash-kpi-value">{{ number_format((int) $kpis['expedientes_incompletos']) }}</div>
      <div class="dash-kpi-status">{{ number_format($porcentajePendientes, 1) }}% incompletos/observados</div>
    </div>

    <div class="dash-kpi-pro">
      <div class="dash-kpi-label">Monto registrado</div>
      <div class="dash-kpi-value">{{ $formatMoneyCompact($kpis['monto_total']) }}</div>
      <div class="dash-kpi-status">Suma de facturas del filtro</div>
    </div>
  </div>

  <div class="dash-micro-grid">
    <div class="dash-micro">
      <span>Expedientes registrados</span>
      <strong>{{ number_format((int) $kpis['total_expedientes']) }}</strong>
    </div>

    <div class="dash-micro">
      <span>Activos sin ubicación</span>
      <strong>{{ number_format((int) $kpis['activos_sin_ubicacion']) }}</strong>
    </div>

    <div class="dash-micro">
      <span>Activos sin valores</span>
      <strong>{{ number_format((int) $kpis['activos_sin_valores']) }}</strong>
    </div>

    <div class="dash-micro">
      <span>PDF vigentes</span>
      <strong>{{ number_format((int) $kpis['documentos_pdf']) }}</strong>
    </div>

    <div class="dash-micro">
      <span>XML vigentes</span>
      <strong>{{ number_format((int) $kpis['documentos_xml']) }}</strong>
    </div>
  </div>

  <section class="card dash-tabs-card">
    <div class="dash-tabbar" role="tablist" aria-label="Secciones del dashboard">
      <button type="button" class="dash-tab-button is-active" data-dashboard-tab="resumen">
        Resumen operativo
      </button>
      <button type="button" class="dash-tab-button" data-dashboard-tab="seguimiento">
        Seguimiento documental
      </button>
      <button type="button" class="dash-tab-button" data-dashboard-tab="documentos">
        Documentos y auditoría
      </button>
      <button type="button" class="dash-tab-button" data-dashboard-tab="accesos">
        Accesos rápidos
      </button>
    </div>

    <div class="dash-tab-panel is-active" data-dashboard-panel="resumen">
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

    <div class="dash-tab-panel" data-dashboard-panel="seguimiento">
      <div class="dash-panel">
        <div class="dash-panel-header">
          <h3>Expedientes que requieren atención</h3>
          <span class="pill warn">PDF/XML · ubicación · valores</span>
        </div>

        <div class="dash-table-scroll">
          <table>
            <thead>
              <tr>
                <th>Activo</th>
                <th>Factura</th>
                <th>Proveedor / Planta</th>
                <th>Estatus</th>
                <th>PDF</th>
                <th>XML</th>
                <th>Ubicación</th>
                <th>Acciones</th>
              </tr>
            </thead>

            <tbody>
              @forelse($expedientesAtencion as $item)
                <tr>
                  <td>
                    <strong>{{ $item->numero_activo }}</strong><br>
                    <span class="dash-mini">{{ $item->activo_descripcion }}</span>
                  </td>

                  <td>{{ $item->folio_factura }}</td>

                  <td>
                    {{ $item->proveedor_nombre ?: 'Sin proveedor' }}<br>
                    <span class="dash-mini">{{ $item->planta_nombre ?: 'Sin planta' }}</span>
                  </td>

                  <td>
                    <span class="pill {{ $estatusClass($item->estatus) }}">
                      {{ $statusText($item->estatus) }}
                    </span>
                  </td>

                  <td>{{ ((int) $item->total_pdf) > 0 ? 'Sí' : 'No' }}</td>
                  <td>{{ ((int) $item->total_xml) > 0 ? 'Sí' : 'No' }}</td>
                  <td>{{ $item->ubicacion_id ? 'Asignada' : 'Pendiente' }}</td>

                  <td>
                    <div class="table-actions">
                      @if($can('expedientes.ver'))
                        <a href="{{ route('expediente', $item->expediente_id) }}">Consultar</a>
                      @endif

                      @if($can('expedientes.editar'))
                        <a href="{{ route('expedientes.editar', $item->expediente_id) }}">Editar</a>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8">
                    No hay expedientes pendientes con los filtros seleccionados.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="dash-tab-panel" data-dashboard-panel="documentos">
      <div class="dash-panel-grid">
        <div class="dash-panel">
          <div class="dash-panel-header">
            <h3>Últimos documentos cargados</h3>
            <span class="pill ok">Vigentes</span>
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

          @if ($can('valores.administrar'))
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

          @if ($can('catalogos.administrar'))
            <a class="dash-quick-link" href="{{ route('catalogos') }}">
              <strong>Catálogos base</strong>
              <span>Proveedores, plantas y datos maestros.</span>
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

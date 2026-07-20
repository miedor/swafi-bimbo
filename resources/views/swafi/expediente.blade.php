@extends('layouts.app')

@section('title', 'Detalle de expediente | SWAFI')
@section('page_title', 'Detalle de expediente')
@section('page_subtitle', 'Vista integral del expediente documental, fiscal y patrimonial')
@section('breadcrumb', 'Detalle de expediente')

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
  .detail-shell {
    display: grid;
    gap: 12px;
  }

  .detail-summary {
    padding: 14px;
    border: 1px solid #dbe7f6;
    border-radius: 20px;
    background: linear-gradient(135deg, #ffffff 0%, #f6f9fe 100%);
    box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
  }

  .detail-summary-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
  }

  .detail-summary-title {
    display: flex;
    gap: 12px;
    align-items: center;
    min-width: 0;
  }

  .detail-summary-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 14px;
    background: linear-gradient(135deg, #174f9a, #2d72c6);
    color: #ffffff;
    font-weight: 950;
  }

  .detail-summary-title h2 {
    margin: 0;
    color: #152f52;
    font-size: 20px;
    font-weight: 950;
    line-height: 1.1;
  }

  .detail-summary-title p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
  }

  .detail-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 7px;
  }

  .detail-actions .tab {
    min-height: 34px;
    padding: 7px 11px;
    font-size: 12px;
  }

  .detail-kpis {
    display: grid;
    grid-template-columns: repeat(6, minmax(120px, 1fr));
    gap: 8px;
    margin-top: 12px;
  }

  .detail-kpi {
    min-height: 66px;
    padding: 10px 11px;
    border: 1px solid #e0e9f5;
    border-radius: 15px;
    background: #ffffff;
  }

  .detail-kpi span {
    display: block;
    color: #64748b;
    font-size: 10.5px;
    font-weight: 900;
    line-height: 1.2;
  }

  .detail-kpi strong {
    display: block;
    margin-top: 5px;
    color: #12345c;
    font-size: 17px;
    font-weight: 950;
    line-height: 1;
  }

  .detail-kpi small {
    display: block;
    margin-top: 5px;
    color: #174f9a;
    font-size: 10.5px;
    font-weight: 850;
  }

  .detail-workspace {
    overflow: hidden;
    padding: 0;
    border: 1px solid #dbe7f6;
    border-radius: 22px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
  }

  .detail-tabbar {
    position: sticky;
    top: 0;
    z-index: 15;
    display: flex;
    gap: 7px;
    overflow-x: auto;
    padding: 12px 14px;
    border-bottom: 1px solid #e3edf8;
    background: rgba(255, 255, 255, .98);
    backdrop-filter: blur(10px);
  }

  .detail-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 36px;
    padding: 8px 13px;
    border: 1px solid #d7e4f4;
    border-radius: 999px;
    background: #ffffff;
    color: #174f9a;
    font-size: 12.5px;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
  }

  .detail-tab:hover {
    background: #eef5ff;
  }

  .detail-tab.is-active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(23, 79, 154, .18);
  }

  .detail-tab-count {
    min-width: 21px;
    padding: 2px 6px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .22);
    font-size: 10px;
  }

  .detail-tab:not(.is-active) .detail-tab-count {
    background: #edf4ff;
    color: #174f9a;
  }

  .detail-workspace-body {
    max-height: calc(100vh - 365px);
    min-height: 430px;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 14px;
    scrollbar-gutter: stable;
  }

  .detail-panel {
    display: none;
  }

  .detail-panel.is-active {
    display: block;
  }

  .detail-section {
    padding: 14px;
    border: 1px solid #e0e9f5;
    border-radius: 18px;
    background: #ffffff;
  }

  .detail-section + .detail-section {
    margin-top: 12px;
  }

  .detail-section-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
  }

  .detail-section-head h3 {
    margin: 0;
    color: #152f52;
    font-size: 16px;
    font-weight: 950;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(150px, 1fr));
    gap: 9px;
  }

  .detail-grid.two {
    grid-template-columns: repeat(2, minmax(220px, 1fr));
  }

  .detail-field {
    min-height: 68px;
    padding: 10px 11px;
    border: 1px solid #e1eaf6;
    border-radius: 14px;
    background: #f8fbff;
  }

  .detail-field strong {
    display: block;
    margin-bottom: 4px;
    color: #1d3558;
    font-size: 11.5px;
    font-weight: 950;
  }

  .detail-field div {
    color: #16304d;
    font-size: 13px;
    line-height: 1.3;
    overflow-wrap: anywhere;
  }

  .detail-field small {
    color: #64748b;
    font-size: 11px;
  }

  .detail-note {
    padding: 11px 13px;
    border: 1px solid #dbe7f6;
    border-radius: 14px;
    background: #f8fbff;
    color: #324b6d;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.45;
  }

  .detail-note.warn {
    border-color: #f9d36a;
    background: #fff7db;
    color: #8a4b00;
  }

  .detail-note.danger {
    border-color: #fecaca;
    background: #fff0ee;
    color: #b42318;
  }

  .detail-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e3edf8;
    border-radius: 15px;
  }

  .detail-table-wrap table {
    min-width: 920px;
    margin: 0;
  }

  .detail-table-wrap th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f6faff;
  }

  .detail-list {
    display: grid;
    gap: 9px;
  }

  .detail-card-item {
    padding: 12px;
    border: 1px solid #dbe7f6;
    border-radius: 16px;
    background: #ffffff;
  }

  .detail-card-item-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: flex-start;
    margin-bottom: 8px;
  }

  .detail-card-item-head h4 {
    margin: 0;
    color: #152f52;
    font-size: 14px;
    font-weight: 950;
  }

  .detail-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
  }

  .detail-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border: 1px solid #dbe7f6;
    border-radius: 999px;
    background: #f8fbff;
    color: #174f9a;
    font-size: 10.5px;
    font-weight: 900;
  }

  .detail-badge.ok {
    border-color: #b9e5bf;
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .detail-badge.warn {
    border-color: #f9d36a;
    background: #fff7db;
    color: #8a4b00;
  }

  .detail-badge.danger {
    border-color: #fecaca;
    background: #fff0ee;
    color: #b42318;
  }

  .detail-body-text {
    display: grid;
    gap: 6px;
    color: #324b6d;
    font-size: 12px;
    line-height: 1.42;
  }

  .detail-body-text strong {
    color: #152f52;
  }

  .detail-muted {
    color: #64748b;
    font-size: 11px;
    font-weight: 750;
  }

  .detail-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(180px, 1fr));
    gap: 10px;
  }

  .detail-form-grid .full {
    grid-column: 1 / -1;
  }

  .detail-form-field span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 11.5px;
    font-weight: 900;
  }

  .detail-form-field input,
  .detail-form-field select,
  .detail-form-field textarea {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 12px;
    font-weight: 750;
  }

  .detail-form-field textarea {
    min-height: 76px;
    resize: vertical;
  }

  .detail-collapsible {
    border: 1px solid #dbe7f6;
    border-radius: 16px;
    background: #f8fbff;
  }

  .detail-collapsible summary {
    padding: 12px 14px;
    color: #174f9a;
    font-size: 13px;
    font-weight: 950;
    cursor: pointer;
  }

  .detail-collapsible-content {
    padding: 0 14px 14px;
  }

  .detail-observation-actions {
    display: grid;
    gap: 9px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e5edf8;
  }

  .detail-action-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 9px;
    align-items: end;
  }

  .detail-action-row.validation {
    grid-template-columns: minmax(145px, .32fr) 1fr auto;
  }

  .evidence-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
  }

  .evidence-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 6px 9px;
    border: 1px solid #d7e4f4;
    border-radius: 12px;
    background: #f8fbff;
    font-size: 11px;
    font-weight: 850;
  }

  .evidence-chip a {
    color: #174f9a;
    text-decoration: none;
  }

  .evidence-chip form {
    display: inline;
  }

  .detail-pagination {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e5edf8;
  }

  .detail-pagination-summary {
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
  }

  .detail-pagination-links {
    display: flex;
    gap: 6px;
  }

  .detail-page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 31px;
    padding: 6px 10px;
    border: 1px solid #d7e4f4;
    border-radius: 10px;
    background: #ffffff;
    color: #174f9a;
    font-size: 11px;
    font-weight: 900;
    text-decoration: none;
  }

  .detail-page-link.is-active {
    border-color: #174f9a;
    background: #174f9a;
    color: #ffffff;
  }

  .detail-page-link.is-disabled {
    opacity: .45;
  }

  .detail-empty {
    padding: 14px;
    border: 1px dashed #cbd8ea;
    border-radius: 14px;
    background: #f8fbff;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
  }


  .document-deactivation {
    position: relative;
  }

  .document-deactivation > summary {
    color: #b42318;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    list-style: none;
  }

  .document-deactivation > summary::-webkit-details-marker {
    display: none;
  }

  .document-deactivation-form {
    display: grid;
    gap: 8px;
    width: min(320px, 76vw);
    margin-top: 8px;
    padding: 10px;
    border: 1px solid #fecaca;
    border-radius: 12px;
    background: #fff7f6;
  }

  .document-deactivation-form label {
    display: grid;
    gap: 5px;
    color: #7f1d1d;
    font-size: 11px;
    font-weight: 900;
  }

  .document-deactivation-form textarea {
    width: 100%;
    min-height: 76px;
    resize: vertical;
    padding: 8px 9px;
    border: 1px solid #f3b4ae;
    border-radius: 10px;
    background: #ffffff;
    color: #3f1d1d;
    font: inherit;
    font-size: 12px;
  }

  .document-deactivation-form button {
    min-height: 34px;
    border: 0;
    border-radius: 10px;
    background: #b42318;
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
  }

  .document-deactivation-form button:focus-visible,
  .document-deactivation > summary:focus-visible {
    outline: 3px solid rgba(180, 35, 24, .24);
    outline-offset: 2px;
  }

  @media (max-width: 1280px) {
    .detail-kpis {
      grid-template-columns: repeat(3, minmax(130px, 1fr));
    }

    .detail-grid {
      grid-template-columns: repeat(3, minmax(150px, 1fr));
    }
  }

  @media (max-width: 980px) {
    .detail-summary-head {
      flex-direction: column;
    }

    .detail-actions {
      justify-content: flex-start;
    }

    .detail-grid,
    .detail-grid.two,
    .detail-form-grid,
    .detail-action-row,
    .detail-action-row.validation {
      grid-template-columns: 1fr 1fr;
    }

    .detail-form-grid .full {
      grid-column: 1 / -1;
    }
  }

  @media (max-width: 760px) {
    .detail-kpis,
    .detail-grid,
    .detail-grid.two,
    .detail-form-grid,
    .detail-action-row,
    .detail-action-row.validation {
      grid-template-columns: 1fr;
    }

    .detail-form-grid .full {
      grid-column: auto;
    }

    .detail-workspace-body {
      max-height: none;
      min-height: 0;
      overflow: visible;
    }

    .detail-pagination {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
@endsection

@section('content')

@if(session('success'))
  <div style="margin-bottom:12px;padding:11px 13px;border-radius:13px;background:#e8f7ea;border:1px solid #b9e5bf;color:#1f6b2a;font-weight:800;">
    {{ session('success') }}
  </div>
@endif

@if(session('warning'))
  <div style="margin-bottom:12px;padding:11px 13px;border-radius:13px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
    {{ session('warning') }}
  </div>
@endif

@if($errors->any())
  <div style="margin-bottom:12px;padding:11px 13px;border-radius:13px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
    @foreach($errors->all() as $error)
      <div>{{ $error }}</div>
    @endforeach
  </div>
@endif

@php
  $swafiRoles = session('swafi_roles', []);
  $swafiPermissions = session('swafi_permissions', []);
  $currentUserId = (int) (session('swafi_user_id') ?: auth()->id());

  $isOfficialAdminSwafi = in_array('Administrador SWAFI', $swafiRoles, true);
  $isAdminSwafi = $isOfficialAdminSwafi || in_array('Administrador', $swafiRoles, true);
  $isCaptura = in_array('Usuario Captura', $swafiRoles, true) || in_array('Capturista', $swafiRoles, true);
  $isConsultaAuditoria = in_array('Usuario Consulta / Auditoría', $swafiRoles, true)
      || in_array('Usuario Consulta / Auditoria', $swafiRoles, true)
      || in_array('Consultor', $swafiRoles, true)
      || in_array('Auditor', $swafiRoles, true);
  $isPlantaInventarios = in_array('Usuario Planta / Inventarios', $swafiRoles, true);

  $canCreateExpedientes = $isAdminSwafi || in_array('expedientes.crear', $swafiPermissions, true);
  $canEditExpediente = $isAdminSwafi || in_array('expedientes.editar', $swafiPermissions, true);
  $canManageDocuments = $isAdminSwafi
      || in_array('documentos.cargar', $swafiPermissions, true)
      || in_array('expedientes.editar', $swafiPermissions, true);
  $canDeactivateDocuments = $isOfficialAdminSwafi
      && in_array('documentos.eliminar', $swafiPermissions, true);
  $canValidateCfdi = $isAdminSwafi || in_array('cfdi.validar', $swafiPermissions, true);
  $canManageValues = $isAdminSwafi || in_array('valores.administrar', $swafiPermissions, true);
  $canManageLocation = $isAdminSwafi || in_array('ubicaciones.administrar', $swafiPermissions, true);
  $canViewAudit = $isAdminSwafi || in_array('bitacora.ver', $swafiPermissions, true);

  $canCreateObservation = $isAdminSwafi || $isConsultaAuditoria || in_array('observaciones.crear', $swafiPermissions, true);
  $canAttendObservation = $isAdminSwafi || in_array('observaciones.atender', $swafiPermissions, true);
  $canValidateObservation = $isAdminSwafi || $isConsultaAuditoria || in_array('observaciones.validar', $swafiPermissions, true);

  $activeTab = $activeTab ?? 'resumen';
  if (!$canViewAudit && $activeTab === 'bitacora') {
      $activeTab = 'resumen';
  }

  $resumenContadores = $resumenContadores ?? [];
  $usuariosAsignablesObservacion = $usuariosAsignablesObservacion ?? collect();

  $tipoObservacionLabels = [
      'falta_pdf' => 'Falta PDF',
      'falta_xml' => 'Falta XML',
      'falta_valores' => 'Falta valores fiscales/financieros',
      'falta_ubicacion' => 'Falta ubicación física',
      'ubicacion_incorrecta' => 'Ubicación incorrecta',
      'datos_inconsistentes' => 'Datos inconsistentes',
      'documento_incorrecto' => 'Documento incorrecto',
      'otro' => 'Otro seguimiento',
  ];

  $prioridadLabels = [
      'baja' => 'Baja',
      'media' => 'Media',
      'alta' => 'Alta',
      'critica' => 'Crítica',
  ];

  $estatusObservacionLabels = [
      'abierta' => 'Abierta',
      'en_atencion' => 'En atención',
      'atendida' => 'Atendida, pendiente de validar',
      'cerrada' => 'Cerrada',
      'rechazada' => 'Rechazada',
      'cancelada' => 'Cancelada',
  ];

  $obsBadgeClass = function (?string $estatus): string {
      $estatus = (string) $estatus;
      if ($estatus === 'cerrada') return 'ok';
      if ($estatus === 'atendida' || $estatus === 'en_atencion') return 'warn';
      if ($estatus === 'rechazada' || $estatus === 'abierta') return 'danger';
      return '';
  };

  $priorityBadgeClass = function (?string $prioridad): string {
      $prioridad = (string) $prioridad;
      if ($prioridad === 'critica' || $prioridad === 'alta') return 'danger';
      if ($prioridad === 'media') return 'warn';
      if ($prioridad === 'baja') return 'ok';
      return '';
  };

  $inventoryStatusLabel = function (?string $status): string {
      $labels = [
          'localizado' => 'Localizado',
          'no_encontrado' => 'No encontrado',
          'diferencia' => 'Diferencia de ubicación',
          'pendiente' => 'Pendiente',
      ];
      return $labels[(string) $status] ?? ucfirst(str_replace('_', ' ', (string) $status));
  };

  $inventoryStatusClass = function (?string $status): string {
      $status = (string) $status;
      if ($status === 'localizado') return 'ok';
      if ($status === 'pendiente' || $status === 'diferencia') return 'warn';
      return 'danger';
  };
@endphp

@if(!$expediente)
  <section class="card">
    <div class="section-title">
      <h2>Detalle de expediente</h2>
      <span class="pill warn">Sin registros</span>
    </div>
    <p>No existen expedientes registrados todavía.</p>
    <div class="action-group">
      @if($canCreateExpedientes)
        <a class="tab" href="{{ route('registro-individual') }}">Ir a registro individual</a>
      @endif
      <a class="tab" href="{{ route('busqueda') }}">Ir a búsqueda avanzada</a>
    </div>
  </section>
@else

<div class="detail-shell">
  <section class="detail-summary">
    <div class="detail-summary-head">
      <div class="detail-summary-title">
        <div class="detail-summary-icon">AF</div>
        <div>
          <h2>{{ $expediente->numero_activo }} · {{ $expediente->activo_descripcion }}</h2>
          <p>Factura {{ $expediente->folio_factura }} · {{ $expediente->proveedor_nombre ?? 'Sin proveedor' }}</p>
        </div>
      </div>

      <div class="detail-actions">
        <a class="tab" href="{{ route('busqueda') }}">Regresar a búsqueda</a>
        @if($canEditExpediente)
          <a class="tab" href="{{ route('expedientes.editar', $expediente->expediente_id) }}">Editar expediente</a>
        @endif
        @if($canCreateExpedientes)
          <a class="tab" href="{{ route('registro-individual') }}">Nuevo registro</a>
        @endif
        @if(($resumenContadores['documentos'] ?? 0) > 0)
          <a class="tab" href="{{ route('documentos.descargar-todos', $expediente->expediente_id) }}">Descargar ZIP</a>
        @endif
      </div>
    </div>

    <div class="detail-kpis">
      <div class="detail-kpi">
        <span>Estatus documental</span>
        <strong>{{ ucfirst($expediente->expediente_estatus) }}</strong>
        <small>{{ ($resumenContadores['observaciones_pendientes'] ?? 0) }} observación(es) pendiente(s)</small>
      </div>
      <div class="detail-kpi">
        <span>Monto factura</span>
        <strong>$ {{ number_format((float) $expediente->monto_factura, 2) }}</strong>
        <small>{{ $expediente->moneda }}</small>
      </div>
      <div class="detail-kpi">
        <span>Documentos vigentes</span>
        <strong>{{ $resumenContadores['documentos'] ?? 0 }}</strong>
        <small>{{ $resumenContadores['cfdi'] ?? 0 }} validación(es) CFDI</small>
      </div>
      <div class="detail-kpi">
        <span>Inventarios</span>
        <strong>{{ $resumenContadores['inventarios'] ?? 0 }}</strong>
        <small>{{ $resumenContadores['discrepancias'] ?? 0 }} discrepancia(s)</small>
      </div>
      <div class="detail-kpi">
        <span>Movimientos</span>
        <strong>{{ $resumenContadores['movimientos'] ?? 0 }}</strong>
        <small>Historial de ubicación</small>
      </div>
      <div class="detail-kpi">
        <span>Eventos de auditoría</span>
        <strong>{{ $resumenContadores['bitacora'] ?? 0 }}</strong>
        <small>Consulta paginada</small>
      </div>
    </div>
  </section>

  <section class="detail-workspace">
    <div class="detail-tabbar" role="tablist" aria-label="Secciones del expediente">
      <button type="button" class="detail-tab {{ $activeTab === 'resumen' ? 'is-active' : '' }}" data-detail-tab="resumen">
        Ficha ejecutiva
      </button>
      <button type="button" class="detail-tab {{ $activeTab === 'ubicacion' ? 'is-active' : '' }}" data-detail-tab="ubicacion">
        Valores, ubicación e inventario
        <span class="detail-tab-count">{{ $resumenContadores['inventarios'] ?? 0 }}</span>
      </button>
      <button type="button" class="detail-tab {{ $activeTab === 'documentos' ? 'is-active' : '' }}" data-detail-tab="documentos">
        Documentos y CFDI
        <span class="detail-tab-count">{{ $resumenContadores['documentos'] ?? 0 }}</span>
      </button>
      <button type="button" class="detail-tab {{ $activeTab === 'observaciones' ? 'is-active' : '' }}" data-detail-tab="observaciones">
        Observaciones
        <span class="detail-tab-count">{{ $resumenContadores['observaciones_pendientes'] ?? 0 }}</span>
      </button>
      @if($canViewAudit)
        <button type="button" class="detail-tab {{ $activeTab === 'bitacora' ? 'is-active' : '' }}" data-detail-tab="bitacora">
          Bitácora
          <span class="detail-tab-count">{{ $resumenContadores['bitacora'] ?? 0 }}</span>
        </button>
      @endif
    </div>

    <div class="detail-workspace-body" id="detail-workspace-body">
      <div class="detail-panel {{ $activeTab === 'resumen' ? 'is-active' : '' }}" data-detail-panel="resumen">
        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Datos generales del expediente</h3>
            @php
              $documentStatusClass = 'danger';
              if ($expediente->expediente_estatus === 'completo') $documentStatusClass = 'ok';
              elseif ($expediente->expediente_estatus === 'observado') $documentStatusClass = 'warn';
            @endphp
            <span class="pill {{ $documentStatusClass }}">{{ ucfirst($expediente->expediente_estatus) }}</span>
          </div>

          <div class="detail-grid">
            <div class="detail-field"><strong>Folio factura</strong><div>{{ $expediente->folio_factura }}</div></div>
            <div class="detail-field"><strong>UUID CFDI</strong><div>{{ $expediente->uuid_cfdi ?: 'No capturado' }}</div></div>
            <div class="detail-field"><strong>Fecha factura</strong><div>{{ $expediente->fecha_factura }}</div></div>
            <div class="detail-field"><strong>Monto</strong><div>$ {{ number_format((float) $expediente->monto_factura, 2) }} {{ $expediente->moneda }}</div></div>
            <div class="detail-field"><strong>Proveedor</strong><div>{{ $expediente->proveedor_nombre ?? 'Sin proveedor' }}</div></div>
            <div class="detail-field"><strong>RFC proveedor</strong><div>{{ $expediente->proveedor_rfc ?? 'Sin RFC' }}</div></div>
            <div class="detail-field"><strong>Fecha registro</strong><div>{{ $expediente->expediente_creado }}</div></div>
            <div class="detail-field"><strong>Última actualización</strong><div>{{ $expediente->expediente_actualizado }}</div></div>
          </div>
        </div>

        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Activo fijo asociado</h3>
            <span class="pill ok">{{ $expediente->numero_activo }}</span>
          </div>

          <div class="detail-grid">
            <div class="detail-field"><strong>Descripción</strong><div>{{ $expediente->activo_descripcion }}</div></div>
            <div class="detail-field"><strong>Tipo de activo</strong><div>{{ $expediente->tipo_activo ?? 'Sin tipo' }}</div></div>
            <div class="detail-field"><strong>Serie</strong><div>{{ $expediente->serie ?: 'Sin serie' }}</div></div>
            <div class="detail-field"><strong>Marca / Modelo</strong><div>{{ $expediente->marca ?: 'Sin marca' }} / {{ $expediente->modelo ?: 'Sin modelo' }}</div></div>
            <div class="detail-field"><strong>Fecha adquisición</strong><div>{{ $expediente->fecha_adquisicion ?: 'Sin fecha' }}</div></div>
            <div class="detail-field"><strong>Estatus operativo</strong><div>{{ ucfirst(str_replace('_', ' ', $expediente->estatus_operativo)) }}</div></div>
            <div class="detail-field"><strong>Planta</strong><div>{{ $expediente->planta_nombre ?? 'Sin planta' }}</div></div>
            <div class="detail-field"><strong>Centro de costo</strong><div>{{ $expediente->centro_costo_clave ?? 'Sin centro' }}<br><small>{{ $expediente->centro_costo_descripcion }}</small></div></div>
          </div>
        </div>

        @if($expediente->observaciones)
          <div class="detail-section">
            <div class="detail-section-head"><h3>Notas generales del expediente</h3></div>
            <div class="detail-note">{{ $expediente->observaciones }}</div>
          </div>
        @endif
      </div>

      <div class="detail-panel {{ $activeTab === 'ubicacion' ? 'is-active' : '' }}" data-detail-panel="ubicacion">
        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Valores fiscales y financieros</h3>
            @if($canManageValues)
              <a class="tab" href="{{ route('valores', ['numero_activo' => $expediente->numero_activo]) }}">Administrar valores</a>
            @endif
          </div>

          <div class="detail-grid">
            <div class="detail-field"><strong>Valor fiscal</strong><div>{{ $valor ? '$ ' . number_format((float) $valor->valor_fiscal, 2) : 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Valor financiero</strong><div>{{ $valor ? '$ ' . number_format((float) $valor->valor_financiero, 2) : 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Depreciación acumulada</strong><div>{{ $valor ? '$ ' . number_format((float) $valor->depreciacion_acumulada, 2) : 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Valor en libros oficial</strong><div>{{ $valor ? '$ ' . number_format((float) $valor->valor_en_libros, 2) : 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Depreciación estimada</strong><div>{{ $valor && $valor->depreciacion_estimada !== null ? '$ ' . number_format((float) $valor->depreciacion_estimada, 2) : 'Sin cálculo referencial' }}</div></div>
            <div class="detail-field"><strong>Valor en libros estimado</strong><div>{{ $valor && $valor->valor_en_libros_estimado !== null ? '$ ' . number_format((float) $valor->valor_en_libros_estimado, 2) : 'Sin cálculo referencial' }}</div></div>
            <div class="detail-field"><strong>Método / inicio</strong><div>{{ $valor && $valor->metodo_depreciacion ? \Illuminate\Support\Str::headline($valor->metodo_depreciacion) . ' / ' . ($valor->fecha_inicio_depreciacion ?: 'Sin fecha') : 'Sin cálculo referencial' }}</div></div>
            <div class="detail-field"><strong>Valor residual</strong><div>{{ $valor ? '$ ' . number_format((float) ($valor->valor_residual ?? 0), 2) : 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Moneda / tipo de cambio</strong><div>{{ $valor ? (($valor->moneda ?? 'MXN') . ' / ' . ($valor->tipo_cambio ? number_format((float) $valor->tipo_cambio, 6) : 'N/A')) : 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Fecha de corte</strong><div>{{ $valor->fecha_corte ?? 'Pendiente' }}</div></div>
            <div class="detail-field"><strong>Estatus contable</strong><div>{{ $valor ? ucfirst(str_replace('_', ' ', $valor->estatus_contable)) : 'Pendiente' }}</div></div>
            <div class="detail-field">
              <strong>Conciliación CFDI</strong>
              @php
                $valorConciliacion = $valor->conciliacion_cfdi ?? 'sin_xml';
                $valorConciliacionClass = $valorConciliacion === 'validado' ? 'ok' : ($valorConciliacion === 'observado' ? 'warn' : 'danger');
              @endphp
              <div><span class="pill {{ $valorConciliacionClass }}">{{ ucfirst(str_replace('_', ' ', $valorConciliacion)) }}</span></div>
            </div>
          </div>
        </div>

        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Ubicación física actual</h3>
            @if($canManageLocation)
              <a class="tab" href="{{ route('ubicacion', ['numero_activo' => $expediente->numero_activo]) }}">Registrar movimiento o inventario</a>
            @endif
          </div>

          <div class="detail-grid">
            <div class="detail-field"><strong>Planta</strong><div>{{ $expediente->planta_nombre ?? 'Sin planta' }}</div></div>
            <div class="detail-field"><strong>Área</strong><div>{{ $expediente->area_nombre ?? 'Sin área' }}</div></div>
            <div class="detail-field"><strong>Código de ubicación</strong><div>{{ $expediente->ubicacion_codigo ?? 'Sin ubicación' }}</div></div>
            <div class="detail-field"><strong>Descripción</strong><div>{{ $expediente->ubicacion_descripcion ?? 'Sin descripción' }}</div></div>
            <div class="detail-field"><strong>Edificio</strong><div>{{ $expediente->edificio ?: 'No indicado' }}</div></div>
            <div class="detail-field"><strong>Piso / Pasillo</strong><div>{{ $expediente->piso ?: 'N/A' }} / {{ $expediente->pasillo ?: 'N/A' }}</div></div>
            <div class="detail-field"><strong>Responsable</strong><div>{{ $expediente->responsable_nombre ?? 'Sin responsable' }}</div></div>
            <div class="detail-field"><strong>Correo responsable</strong><div>{{ $expediente->responsable_correo ?? 'Sin correo' }}</div></div>
          </div>
        </div>

        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Historial de tomas de inventario</h3>
            <span class="pill {{ ($resumenContadores['discrepancias'] ?? 0) > 0 ? 'warn' : 'ok' }}">
              {{ $resumenContadores['discrepancias'] ?? 0 }} discrepancia(s)
            </span>
          </div>

          <div class="detail-list">
            @forelse($inventarios as $inventario)
              <article class="detail-card-item">
                <div class="detail-card-item-head">
                  <div>
                    <h4>{{ $inventoryStatusLabel($inventario->estatus_localizacion) }} · {{ $inventario->fecha_inventario }}</h4>
                    <div class="detail-muted">
                      Verificó: {{ $inventario->verificado_por_nombre ?: ($inventario->verificado_por_email ?: 'Usuario no identificado') }}
                    </div>
                  </div>
                  <div class="detail-badges">
                    <span class="detail-badge {{ $inventoryStatusClass($inventario->estatus_localizacion) }}">{{ $inventoryStatusLabel($inventario->estatus_localizacion) }}</span>
                    <span class="detail-badge">{{ $inventario->evidencias->count() }} evidencia(s)</span>
                  </div>
                </div>

                <div class="detail-body-text">
                  <div><strong>Ubicación verificada:</strong> {{ $inventario->ubicacion_verificada_codigo ?: 'No indicada' }} · {{ $inventario->ubicacion_verificada_descripcion ?: 'Sin descripción' }}</div>
                  <div><strong>Planta / Área:</strong> {{ $inventario->ubicacion_verificada_planta ?: 'Sin planta' }} / {{ $inventario->ubicacion_verificada_area ?: 'Sin área' }}</div>
                  <div><strong>Observaciones:</strong> {{ $inventario->observaciones ?: 'Sin observaciones' }}</div>
                  @if(!empty($inventario->notificado_a_email))
                    <div class="detail-muted">
                      Notificación: {{ $inventario->notificado_at ? 'Enviada el ' . $inventario->notificado_at : 'Pendiente o fallida' }}
                      · {{ $inventario->notificado_a_nombre ?: $inventario->notificado_a_email }}
                    </div>
                  @endif
                </div>

                @if($inventario->evidencias->isNotEmpty())
                  <div class="evidence-list">
                    @foreach($inventario->evidencias as $evidencia)
                      <div class="evidence-chip">
                        <span>{{ $evidencia->tipo_evidencia }}</span>
                        <a href="{{ route('inventario-evidencias.ver', $evidencia->id) }}" target="_blank" rel="noopener">{{ $evidencia->nombre_archivo }}</a>
                        <a href="{{ route('inventario-evidencias.descargar', $evidencia->id) }}">Descargar</a>
                        @if($canManageLocation)
                          <form method="POST" action="{{ route('inventario-evidencias.eliminar', $evidencia->id) }}" data-confirm="¿Deseas dar de baja esta evidencia? El archivo físico se conservará para trazabilidad.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="border:0;background:none;color:#b42318;font-weight:900;cursor:pointer;padding:0;">Dar de baja</button>
                          </form>
                        @endif
                      </div>
                    @endforeach
                  </div>
                @endif
              </article>
            @empty
              <div class="detail-empty">Todavía no existen tomas de inventario para este activo.</div>
            @endforelse
          </div>

          @include('swafi.partials.detail-paginator', ['paginator' => $inventarios, 'label' => 'inventarios'])
        </div>

        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Historial de movimientos de ubicación</h3>
            <span class="pill ok">{{ $resumenContadores['movimientos'] ?? 0 }} movimiento(s)</span>
          </div>

          <div class="detail-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Origen</th>
                  <th>Destino</th>
                  <th>Motivo</th>
                  <th>Responsable</th>
                  <th>Registró</th>
                </tr>
              </thead>
              <tbody>
                @forelse($movimientos as $movimiento)
                  <tr>
                    <td>{{ $movimiento->fecha_movimiento }}</td>
                    <td>{{ $movimiento->origen_codigo ?: 'Sin origen' }}<br><small>{{ $movimiento->origen_descripcion }}</small></td>
                    <td>{{ $movimiento->destino_codigo ?: 'Sin destino' }}<br><small>{{ $movimiento->destino_descripcion }}</small></td>
                    <td>{{ $movimiento->motivo ?: 'Sin motivo' }}<br><small>{{ $movimiento->evidencia }}</small></td>
                    <td>{{ $movimiento->responsable_nombre ?: 'Sin responsable' }}</td>
                    <td>{{ $movimiento->registrado_por_nombre ?: ($movimiento->registrado_por_email ?: 'Usuario no identificado') }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6">No existen movimientos de ubicación para este activo.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @include('swafi.partials.detail-paginator', ['paginator' => $movimientos, 'label' => 'movimientos'])
        </div>
      </div>

      <div class="detail-panel {{ $activeTab === 'documentos' ? 'is-active' : '' }}" data-detail-panel="documentos">
        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Validación técnica del XML CFDI</h3>
            @php
              $cfdiPillClass = 'ok';
              if (($resumenContadores['cfdi_invalidos'] ?? 0) > 0) $cfdiPillClass = 'danger';
              elseif (($resumenContadores['cfdi_observados'] ?? 0) > 0 || ($resumenContadores['cfdi'] ?? 0) === 0) $cfdiPillClass = 'warn';
            @endphp
            <span class="pill {{ $cfdiPillClass }}">{{ $resumenContadores['cfdi'] ?? 0 }} validación(es)</span>
          </div>

          <div class="detail-note">
            SWAFI verifica estructura XML, UUID, RFC emisor, fecha, total, moneda, timbre, sello, certificado y consistencia contra el expediente. Esta revisión técnica no sustituye una consulta en línea del estado fiscal ante SAT.
          </div>

          @if($canValidateCfdi)
            <form method="POST" action="{{ route('cfdi.revalidar', $expediente->expediente_id) }}" style="margin:10px 0;">
              @csrf
              <button type="submit" class="tab">Revalidar XML CFDI vigentes</button>
            </form>
          @endif

          <div class="detail-list">
            @forelse($cfdiValidaciones as $cfdi)
              @php
                $cfdiErrors = is_string($cfdi->errores) ? json_decode($cfdi->errores, true) : $cfdi->errores;
                $cfdiWarnings = is_string($cfdi->advertencias) ? json_decode($cfdi->advertencias, true) : $cfdi->advertencias;
                $cfdiErrors = is_array($cfdiErrors) ? $cfdiErrors : [];
                $cfdiWarnings = is_array($cfdiWarnings) ? $cfdiWarnings : [];
                $cfdiStatusClass = $cfdi->estatus_validacion === 'valido' ? 'ok' : ($cfdi->estatus_validacion === 'observado' ? 'warn' : 'danger');
              @endphp

              <article class="detail-card-item">
                <div class="detail-card-item-head">
                  <div>
                    <h4>{{ $cfdi->nombre_archivo }}</h4>
                    <div class="detail-muted">Versión {{ $cfdi->documento_version }} · {{ $cfdi->validado_at }}</div>
                  </div>
                  <span class="detail-badge {{ $cfdiStatusClass }}">{{ ucfirst($cfdi->estatus_validacion) }}</span>
                </div>

                <div class="detail-grid">
                  <div class="detail-field"><strong>UUID</strong><div>{{ $cfdi->uuid_cfdi ?: 'No localizado' }}</div></div>
                  <div class="detail-field"><strong>Emisor</strong><div>{{ $cfdi->rfc_emisor ?: 'Sin RFC' }}<br><small>{{ $cfdi->nombre_emisor }}</small></div></div>
                  <div class="detail-field"><strong>Total CFDI</strong><div>{{ $cfdi->total !== null ? '$ ' . number_format((float) $cfdi->total, 2) . ' ' . $cfdi->moneda : 'No localizado' }}</div></div>
                  <div class="detail-field"><strong>Tipo de cambio</strong><div>{{ $cfdi->tipo_cambio !== null ? number_format((float) $cfdi->tipo_cambio, 6) : 'No aplica' }}</div></div>
                  <div class="detail-field"><strong>Coincidencias</strong><div>UUID: {{ $cfdi->coincide_uuid === null ? 'N/A' : ($cfdi->coincide_uuid ? 'Sí' : 'No') }} · RFC: {{ $cfdi->coincide_rfc === null ? 'N/A' : ($cfdi->coincide_rfc ? 'Sí' : 'No') }} · Monto: {{ $cfdi->coincide_monto === null ? 'N/A' : ($cfdi->coincide_monto ? 'Sí' : 'No') }}</div></div>
                  <div class="detail-field"><strong>Integridad</strong><div>Sello: {{ $cfdi->sello_presente ? 'Sí' : 'No' }} · Certificado: {{ $cfdi->certificado_presente ? 'Sí' : 'No' }} · Timbre: {{ $cfdi->timbre_presente ? 'Sí' : 'No' }}</div></div>
                </div>

                @if($cfdiErrors)
                  <div class="detail-note danger" style="margin-top:8px;"><strong>Errores:</strong> {{ implode(' ', $cfdiErrors) }}</div>
                @endif
                @if($cfdiWarnings)
                  <div class="detail-note warn" style="margin-top:8px;"><strong>Advertencias:</strong> {{ implode(' ', $cfdiWarnings) }}</div>
                @endif
              </article>
            @empty
              <div class="detail-empty">No existe una validación CFDI registrada. Carga un XML vigente o utiliza la opción de revalidación.</div>
            @endforelse
          </div>

          @include('swafi.partials.detail-paginator', ['paginator' => $cfdiValidaciones, 'label' => 'validaciones CFDI'])
        </div>

        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Documentos asociados</h3>
            <span class="pill {{ ($resumenContadores['documentos'] ?? 0) > 0 ? 'ok' : 'warn' }}">{{ $resumenContadores['documentos'] ?? 0 }} vigente(s)</span>
          </div>

          @if($canManageDocuments)
            <details class="detail-collapsible" style="margin-bottom:10px;">
              <summary>Agregar o reemplazar documentos PDF/XML</summary>
              <div class="detail-collapsible-content">
                <form method="POST" action="{{ route('documentos.store', $expediente->expediente_id) }}" enctype="multipart/form-data">
                  @csrf
                  <label class="detail-form-field">
                    <span>Seleccionar documentos</span>
                    <input type="file" name="documentos[]" accept=".pdf,.xml" multiple required>
                  </label>
                  <div class="detail-note" style="margin:9px 0;">
                    Un archivo con el mismo nombre o contenido se registra como nueva versión; un documento diferente se suma al expediente. El activo {{ $expediente->numero_activo }} no se duplica.
                  </div>
                  <button class="tab" type="submit">Ligar documentos al expediente</button>
                </form>
              </div>
            </details>
          @endif

          <div class="detail-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Documento</th>
                  <th>Tipo</th>
                  <th>Versión</th>
                  <th>Tamaño</th>
                  <th>Integridad</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                @forelse($documentos as $documento)
                  <tr>
                    <td><strong>{{ $documento->nombre_archivo }}</strong><br><small>{{ $documento->mime_type ?: 'MIME no registrado' }}</small></td>
                    <td>{{ $documento->tipo_documento }}</td>
                    <td>v{{ $documento->version }}</td>
                    <td>{{ $documento->tamano_bytes ? number_format(((float) $documento->tamano_bytes) / 1024, 2) . ' KB' : 'No registrado' }}</td>
                    <td>
                      @if($documento->hash_sha256)
                        <span class="pill ok">SHA-256</span><br><small>{{ substr($documento->hash_sha256, 0, 16) }}...</small>
                      @else
                        <span class="pill warn">Pendiente</span>
                      @endif
                    </td>
                    <td>
                      <div class="table-actions">
                        <a href="{{ route('documentos.ver', $documento->id) }}" target="_blank" rel="noopener">Ver</a>
                        <a href="{{ route('documentos.descargar', $documento->id) }}">Descargar</a>
                        @if($canDeactivateDocuments)
                          <details class="document-deactivation">
                            <summary>Dar de baja</summary>
                            <form
                              method="POST"
                              action="{{ route('documentos.eliminar', $documento->id) }}"
                              class="document-deactivation-form"
                              data-confirm="¿Confirmas la baja lógica de este documento? El archivo físico, sus versiones y la trazabilidad se conservarán."
                            >
                              @csrf
                              @method('DELETE')
                              <label>
                                Motivo de la baja
                                <textarea
                                  name="motivo_baja"
                                  required
                                  minlength="10"
                                  maxlength="500"
                                  placeholder="Describe por qué debe dejar de estar vigente este documento."
                                >{{ old('motivo_baja') }}</textarea>
                              </label>
                              <button type="submit">Confirmar baja lógica</button>
                            </form>
                          </details>
                        @endif
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6">El expediente no cuenta con documentos vigentes.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @include('swafi.partials.detail-paginator', ['paginator' => $documentos, 'label' => 'documentos'])
        </div>
      </div>

      <div class="detail-panel {{ $activeTab === 'observaciones' ? 'is-active' : '' }}" data-detail-panel="observaciones">
        <div class="detail-section">
          <div class="detail-section-head">
            <h3>Seguimiento y validación cruzada</h3>
            <div class="detail-badges">
              <span class="pill {{ ($resumenContadores['observaciones_pendientes'] ?? 0) > 0 ? 'warn' : 'ok' }}">{{ $resumenContadores['observaciones_pendientes'] ?? 0 }} pendiente(s)</span>
              @if(($resumenContadores['observaciones_vencidas'] ?? 0) > 0)
                <span class="pill danger">{{ $resumenContadores['observaciones_vencidas'] }} vencida(s)</span>
              @elseif(($resumenContadores['observaciones_por_vencer'] ?? 0) > 0)
                <span class="pill warn">{{ $resumenContadores['observaciones_por_vencer'] }} por vencer</span>
              @endif
            </div>
          </div>

          <div class="detail-note" style="margin-bottom:10px;">
            Consulta / Auditoría registra y valida; Captura atiende observaciones documentales o de valores; Planta / Inventarios atiende observaciones de ubicación. La separación de funciones se conserva en bitácora.
          </div>

          @if($canCreateObservation)
            <details class="detail-collapsible" {{ old('tipo_observacion') ? 'open' : '' }} style="margin-bottom:10px;">
              <summary>Registrar y notificar una nueva observación</summary>
              <div class="detail-collapsible-content">
                <form method="POST" action="{{ route('observaciones.store', $expediente->expediente_id) }}">
                  @csrf
                  <div class="detail-form-grid">
                    <label class="detail-form-field">
                      <span>Tipo de observación</span>
                      <select name="tipo_observacion" id="obs_tipo_observacion" required>
                        <option value="">Seleccione...</option>
                        @foreach($tipoObservacionLabels as $key => $label)
                          <option value="{{ $key }}" {{ old('tipo_observacion') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                      </select>
                    </label>

                    <label class="detail-form-field">
                      <span>Prioridad</span>
                      <select name="prioridad" required>
                        @foreach($prioridadLabels as $key => $label)
                          <option value="{{ $key }}" {{ old('prioridad', 'media') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                      </select>
                    </label>

                    <label class="detail-form-field">
                      <span>Rol responsable</span>
                      <select name="rol_destino" id="obs_rol_destino" required>
                        <option value="">Seleccione...</option>
                        <option value="Usuario Captura" {{ old('rol_destino') === 'Usuario Captura' ? 'selected' : '' }}>Usuario Captura</option>
                        <option value="Usuario Planta / Inventarios" {{ old('rol_destino') === 'Usuario Planta / Inventarios' ? 'selected' : '' }}>Usuario Planta / Inventarios</option>
                      </select>
                    </label>

                    <label class="detail-form-field full">
                      <span>Usuario responsable</span>
                      <select name="asignado_a" id="obs_asignado_a" required>
                        <option value="">Seleccione...</option>
                        @foreach($usuariosAsignablesObservacion as $usuarioAsignable)
                          <option value="{{ $usuarioAsignable->id }}" data-rol="{{ $usuarioAsignable->rol_nombre }}" {{ (string) old('asignado_a') === (string) $usuarioAsignable->id ? 'selected' : '' }}>
                            {{ $usuarioAsignable->name }} · {{ $usuarioAsignable->rol_nombre }} · {{ $usuarioAsignable->email }}
                          </option>
                        @endforeach
                      </select>
                    </label>

                    <label class="detail-form-field">
                      <span>Fecha compromiso</span>
                      <input
                        type="date"
                        name="fecha_compromiso"
                        min="{{ \Carbon\CarbonImmutable::now((string) config('swafi.observaciones_recordatorios.zona_horaria', 'America/Mexico_City'))->addDay()->toDateString() }}"
                        value="{{ old('fecha_compromiso') }}"
                        required
                      >
                    </label>

                    <label class="detail-form-field full">
                      <span>Descripción</span>
                      <textarea name="descripcion" required placeholder="Describe con claridad qué se detectó y qué debe corregirse">{{ old('descripcion') }}</textarea>
                    </label>
                  </div>
                  <button type="submit" class="tab" style="margin-top:10px;">Registrar y notificar observación</button>
                </form>
              </div>
            </details>
          @endif

          @php
            $observationDeadlineService = app(\App\Services\ObservationDeadlineService::class);
            $observationReminderTimezone = (string) config(
                'swafi.observaciones_recordatorios.zona_horaria',
                'America/Mexico_City'
            );
            $observationDeadlineNow = \Carbon\CarbonImmutable::now($observationReminderTimezone);
            $observationDueSoonDays = (int) config(
                'swafi.observaciones_recordatorios.dias_anticipacion',
                2
            );
          @endphp

          <div class="detail-list">
            @forelse($observaciones as $observacion)
              @php
                $estatus = (string) $observacion->estatus;
                $tipo = $tipoObservacionLabels[$observacion->tipo_observacion] ?? $observacion->tipo_observacion;
                $prioridad = $prioridadLabels[$observacion->prioridad] ?? $observacion->prioridad;
                $assignedToCurrentUser = empty($observacion->asignado_a) || (int) $observacion->asignado_a === $currentUserId;
                $canTakeThis = $canAttendObservation
                    && in_array($estatus, ['abierta', 'rechazada'], true)
                    && ($isAdminSwafi || $assignedToCurrentUser)
                    && ($isAdminSwafi || (int) $observacion->creado_por !== $currentUserId);
                $canAttendThis = $canAttendObservation
                    && in_array($estatus, ['abierta', 'en_atencion', 'rechazada'], true)
                    && ($isAdminSwafi || $assignedToCurrentUser)
                    && ($isAdminSwafi || (int) $observacion->creado_por !== $currentUserId);
                $canValidateThis = $canValidateObservation
                    && $estatus === 'atendida'
                    && ($isAdminSwafi || (int) $observacion->atendido_por !== $currentUserId);
                $canCancelThis = $canValidateObservation && !in_array($estatus, ['cerrada', 'cancelada'], true);
                $canManageDeadline = $canValidateObservation
                    && in_array($estatus, ['abierta', 'en_atencion', 'rechazada'], true);
                $fechaCompromiso = $observacion->fecha_compromiso ?? null;
                $deadlineDays = $fechaCompromiso
                    ? $observationDeadlineService->daysRemaining($fechaCompromiso, $observationDeadlineNow)
                    : null;
                $deadlineState = $observationDeadlineService->state(
                    $fechaCompromiso,
                    $estatus,
                    $observationDeadlineNow,
                    $observationDueSoonDays
                );
                $deadlineLabel = $observationDeadlineService->label($deadlineState, $deadlineDays);
                $deadlineBadgeClass = $observationDeadlineService->badgeClass($deadlineState);
              @endphp

              <article class="detail-card-item">
                <div class="detail-card-item-head">
                  <div>
                    <h4>{{ $tipo }}</h4>
                    <div class="detail-muted">Registró: {{ $observacion->creado_por_nombre ?: ($observacion->creado_por_email ?: 'Usuario no identificado') }} · {{ $observacion->created_at }}</div>
                  </div>
                  <div class="detail-badges">
                    <span class="detail-badge {{ $obsBadgeClass($estatus) }}">{{ $estatusObservacionLabels[$estatus] ?? ucfirst($estatus) }}</span>
                    <span class="detail-badge {{ $priorityBadgeClass($observacion->prioridad) }}">{{ $prioridad }}</span>
                    <span class="detail-badge {{ $deadlineBadgeClass }}">{{ $deadlineLabel }}</span>
                  </div>
                </div>

                <div class="detail-body-text">
                  <div><strong>Observación:</strong> {{ $observacion->descripcion }}</div>
                  <div class="detail-muted">
                    Asignado a: {{ $observacion->asignado_a_nombre ?: ($observacion->asignado_a_email ?: 'Pendiente') }}
                    @if($observacion->rol_destino) · {{ $observacion->rol_destino }} @endif
                    @if($observacion->fecha_notificacion) · Correo enviado {{ $observacion->fecha_notificacion }} @elseif($observacion->notificacion_error) · Correo no enviado @endif
                  </div>
                  <div>
                    <strong>Fecha compromiso:</strong>
                    {{ $fechaCompromiso ? \Carbon\CarbonImmutable::parse($fechaCompromiso)->format('d/m/Y') : 'Sin fecha registrada' }}
                  </div>
                  @if($canManageDeadline)
                    <form method="POST" action="{{ route('observaciones.actualizar-fecha', $observacion->id) }}" class="detail-action-row" style="margin-top:8px;">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="observacion_contexto" value="{{ $observacion->id }}">
                      <label class="detail-form-field">
                        <span>Nueva fecha compromiso</span>
                        <input
                          type="date"
                          name="nueva_fecha_compromiso"
                          min="{{ \Carbon\CarbonImmutable::now((string) config('swafi.observaciones_recordatorios.zona_horaria', 'America/Mexico_City'))->addDay()->toDateString() }}"
                          value="{{ (string) old('observacion_contexto') === (string) $observacion->id ? old('nueva_fecha_compromiso') : ($fechaCompromiso ? \Carbon\CarbonImmutable::parse($fechaCompromiso)->toDateString() : '') }}"
                          required
                        >
                      </label>
                      <button type="submit" class="tab">Actualizar fecha</button>
                    </form>
                  @endif
                  @if($observacion->fecha_ultimo_recordatorio)
                    <div class="detail-muted">
                      Último recordatorio: {{ $observacion->fecha_ultimo_recordatorio }} ·
                      {{ (int) ($observacion->recordatorios_enviados ?? 0) }} enviado(s)
                    </div>
                  @elseif($observacion->recordatorio_error_referencia)
                    <div class="detail-muted">
                      El último recordatorio no pudo enviarse. Referencia: {{ $observacion->recordatorio_error_referencia }}.
                    </div>
                  @endif
                  @if($observacion->respuesta_atencion)
                    <div><strong>Respuesta:</strong> {{ $observacion->respuesta_atencion }}</div>
                    <div class="detail-muted">Atendió: {{ $observacion->atendido_por_nombre ?: ($observacion->atendido_por_email ?: 'Usuario no identificado') }} · {{ $observacion->fecha_atencion ?: $observacion->updated_at }}</div>
                  @endif
                  @if($observacion->comentario_validacion)
                    <div><strong>Validación:</strong> {{ $observacion->comentario_validacion }}</div>
                    <div class="detail-muted">Validó/Canceló: {{ $observacion->validado_por_nombre ?: ($observacion->validado_por_email ?: ($observacion->cancelado_por_nombre ?: ($observacion->cancelado_por_email ?: 'Usuario no identificado'))) }}</div>
                  @endif
                </div>

                @if($canTakeThis || $canAttendThis || $canValidateThis || $canCancelThis)
                  <div class="detail-observation-actions">
                    @if($canTakeThis)
                      <form method="POST" action="{{ route('observaciones.tomar', $observacion->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="tab">Tomar en atención</button>
                      </form>
                    @endif

                    @if($canAttendThis)
                      <form method="POST" action="{{ route('observaciones.atender', $observacion->id) }}" class="detail-action-row">
                        @csrf
                        @method('PATCH')
                        <label class="detail-form-field">
                          <span>Respuesta de atención</span>
                          <textarea name="respuesta_atencion" required placeholder="Describe la corrección realizada"></textarea>
                        </label>
                        <button type="submit" class="tab">Marcar atendida</button>
                      </form>
                    @endif

                    @if($canValidateThis)
                      <form method="POST" action="{{ route('observaciones.validar', $observacion->id) }}" class="detail-action-row validation">
                        @csrf
                        @method('PATCH')
                        <label class="detail-form-field">
                          <span>Decisión</span>
                          <select name="decision" required>
                            <option value="cerrada">Cerrar corrección</option>
                            <option value="rechazada">Rechazar corrección</option>
                          </select>
                        </label>
                        <label class="detail-form-field">
                          <span>Comentario de validación</span>
                          <textarea name="comentario_validacion" required placeholder="Indica si la evidencia es correcta o por qué se rechaza"></textarea>
                        </label>
                        <button type="submit" class="tab">Validar</button>
                      </form>
                    @endif

                    @if($canCancelThis)
                      <form method="POST" action="{{ route('observaciones.cancelar', $observacion->id) }}" data-confirm="¿Deseas cancelar esta observación?">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="comentario_validacion" value="Observación cancelada por Consulta/Auditoría.">
                        <button type="submit" style="border:0;background:none;color:#b42318;font-weight:900;cursor:pointer;padding:0;">Cancelar observación</button>
                      </form>
                    @endif
                  </div>
                @endif
              </article>
            @empty
              <div class="detail-empty">El expediente no tiene observaciones de seguimiento.</div>
            @endforelse
          </div>

          @include('swafi.partials.detail-paginator', ['paginator' => $observaciones, 'label' => 'observaciones'])
        </div>
      </div>

      @if($canViewAudit)
        <div class="detail-panel {{ $activeTab === 'bitacora' ? 'is-active' : '' }}" data-detail-panel="bitacora">
          <div class="detail-section">
            <div class="detail-section-head">
              <h3>Bitácora de auditoría</h3>
              <span class="pill ok">{{ $resumenContadores['bitacora'] ?? 0 }} evento(s)</span>
            </div>

            <div class="detail-note" style="margin-bottom:10px;">
              La bitácora se muestra paginada para conservar una vista compacta. Las consultas repetidas del mismo expediente dentro de cinco minutos se agrupan para evitar ruido innecesario.
            </div>

            <div class="detail-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Acción</th>
                    <th>Módulo</th>
                    <th>Usuario</th>
                    <th>Tabla / registro</th>
                    <th>IP</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($bitacora as $evento)
                    <tr>
                      <td>{{ $evento->fecha_evento }}</td>
                      <td><strong>{{ $evento->accion }}</strong></td>
                      <td>{{ $evento->modulo }}</td>
                      <td>{{ $evento->usuario_nombre ?: ($evento->usuario_email ?: 'Sistema / no identificado') }}</td>
                      <td>{{ $evento->tabla_afectada ?: 'N/A' }}<br><small>{{ $evento->registro_clave }}</small></td>
                      <td>{{ $evento->ip ?: 'No registrada' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="6">Aún no existen eventos registrados para este activo.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            @include('swafi.partials.detail-paginator', ['paginator' => $bitacora, 'label' => 'eventos de auditoría'])
          </div>
        </div>
      @endif
    </div>
  </section>
</div>

@endif
@endsection

@section('page_scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('[data-detail-tab]');
    const panels = document.querySelectorAll('[data-detail-panel]');
    const workspaceBody = document.getElementById('detail-workspace-body');

    function activateTab(tabName, updateUrl) {
      let targetExists = false;

      panels.forEach(function (panel) {
        const active = panel.getAttribute('data-detail-panel') === tabName;
        panel.classList.toggle('is-active', active);
        if (active) targetExists = true;
      });

      if (!targetExists) return;

      buttons.forEach(function (button) {
        button.classList.toggle('is-active', button.getAttribute('data-detail-tab') === tabName);
      });

      if (workspaceBody) workspaceBody.scrollTop = 0;

      if (updateUrl) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        history.replaceState({}, '', url.toString());
      }
    }

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        activateTab(button.getAttribute('data-detail-tab'), true);
      });
    });

    const tipo = document.getElementById('obs_tipo_observacion');
    const rol = document.getElementById('obs_rol_destino');
    const usuario = document.getElementById('obs_asignado_a');

    if (tipo && rol && usuario) {
      const tiposPlanta = ['falta_ubicacion', 'ubicacion_incorrecta'];

      function filterUsersByRole() {
        const selectedRole = rol.value;
        let selectedVisible = false;

        Array.from(usuario.options).forEach(function (option) {
          if (option.value === '') {
            option.hidden = false;
            return;
          }

          const visible = option.getAttribute('data-rol') === selectedRole;
          option.hidden = !visible;

          if (visible && option.selected) selectedVisible = true;
        });

        if (!selectedVisible) usuario.value = '';
      }

      function syncRoleByType() {
        if (tiposPlanta.includes(tipo.value)) {
          rol.value = 'Usuario Planta / Inventarios';
        } else if (tipo.value !== '') {
          rol.value = 'Usuario Captura';
        }
        filterUsersByRole();
      }

      tipo.addEventListener('change', syncRoleByType);
      rol.addEventListener('change', filterUsersByRole);
      syncRoleByType();
    }
  });
</script>
@endsection

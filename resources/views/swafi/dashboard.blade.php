@extends('layouts.app')

@section('title', 'Dashboard principal | SWAFI')
@section('page_title', 'Dashboard principal')
@section('page_subtitle', 'Resumen ejecutivo del control documental y patrimonial')
@section('breadcrumb', 'Dashboard')

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
@endphp

@if ($errors->any())
    <div style="margin-bottom:14px;padding:12px 14px;border-radius:14px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="grid-kpi">
  <div class="card kpi">
    <div class="label">Facturas registradas</div>
    <div class="value">12,480</div>
    <div class="status">+ 4.7% vs mes anterior</div>
  </div>

  <div class="card kpi">
    <div class="label">Pendientes de validación</div>
    <div class="value">186</div>
    <div class="status">Requieren revisión documental</div>
  </div>

  <div class="card kpi">
    <div class="label">Ubicación pendiente</div>
    <div class="value">74</div>
    <div class="status">Activos sin localización confirmada</div>
  </div>

  <div class="card kpi">
    <div class="label">Valores incompletos</div>
    <div class="value">53</div>
    <div class="status">Campos fiscales/financieros faltantes</div>
  </div>

  <div class="card kpi">
    <div class="label">Reportes generados</div>
    <div class="value">328</div>
    <div class="status">Exportaciones del periodo</div>
  </div>
</div>

<div class="content-grid">
  <section class="card chart-box">
    <div class="section-title">
      <h2>Estado de expedientes</h2>

      @if ($can('reportes.exportar'))
        <a class="btn btn-secondary" href="{{ route('reportes') }}">Ver reportes</a>
      @endif
    </div>

    <div class="chart-placeholder">
      <span style="height:50%"></span>
      <span style="height:78%"></span>
      <span style="height:62%"></span>
      <span style="height:90%"></span>
      <span style="height:72%"></span>
      <span style="height:38%"></span>
      <span style="height:85%"></span>
    </div>

    <div class="footer-note">
      Visualización referencial para el avance del prototipo.
    </div>
  </section>

  <section class="card">
    <div class="section-title">
      <h2>Actividad reciente</h2>

      @if ($can('bitacora.ver'))
        <a class="pill ok" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">Bitácora activa</a>
      @else
        <span class="pill ok">Actividad SWAFI</span>
      @endif
    </div>

    <div class="list">
      <div class="list-item"><strong>Carga masiva de expedientes</strong><span>Control documental</span></div>
      <div class="list-item"><strong>Actualización de ubicación en planta</strong><span>Inventario físico</span></div>
      <div class="list-item"><strong>Reporte de expedientes incompletos</strong><span>Seguimiento</span></div>
      <div class="list-item"><strong>Alta de registros en catálogo base</strong><span>Administración</span></div>
    </div>
  </section>
</div>

<section class="card">
  <div class="section-title">
    <h2>Accesos rápidos permitidos</h2>
    <span class="pill warn">Filtrado por rol</span>
  </div>

  <div class="quick-links">
    @if ($can('expedientes.crear'))
      <a href="{{ route('registro-individual') }}">Registro individual</a>
      <a href="{{ route('registro-masivo') }}">Registro masivo</a>
    @endif

    @if ($can('valores.administrar'))
      <a href="{{ route('valores') }}">Valores fiscales y financieros</a>
    @endif

    @if ($can('ubicaciones.administrar'))
      <a href="{{ route('ubicacion') }}">Ubicación e inventario</a>
    @endif

    @if ($can('expedientes.ver'))
      <a href="{{ route('busqueda') }}">Búsqueda avanzada</a>
      <a href="{{ route('expediente') }}">Detalle de expediente</a>
    @endif

    @if ($can('reportes.exportar'))
      <a href="{{ route('reportes') }}">Reportes ad hoc</a>
    @endif

    @if ($can('catalogos.administrar'))
      <a href="{{ route('catalogos') }}">Catálogos base</a>
    @endif

    @if ($can('seguridad.administrar'))
      <a href="{{ route('seguridad', ['tab' => 'usuarios']) }}">Seguridad y acceso</a>
    @endif

    @if ($can('bitacora.ver'))
      <a href="{{ route('seguridad', ['tab' => 'bitacora']) }}">Bitácora</a>
    @endif
  </div>
</section>

@endsection

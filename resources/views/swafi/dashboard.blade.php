@extends('layouts.app')

@section('title', 'Dashboard principal | SWAFI')
@section('page_title', 'Dashboard principal')
@section('page_subtitle', 'Resumen ejecutivo del control documental y patrimonial')
@section('breadcrumb', 'Dashboard')
@section('content')

<div class="grid-kpi">
  <div class="card kpi"><div class="label">Facturas registradas</div><div class="value">12,480</div><div class="status">+ 4.7% vs mes anterior</div></div>
  <div class="card kpi"><div class="label">Pendientes de validación</div><div class="value">186</div><div class="status">Requieren revisión documental</div></div>
  <div class="card kpi"><div class="label">Ubicación pendiente</div><div class="value">74</div><div class="status">Activos sin localización confirmada</div></div>
  <div class="card kpi"><div class="label">Valores incompletos</div><div class="value">53</div><div class="status">Campos fiscales/financieros faltantes</div></div>
  <div class="card kpi"><div class="label">Reportes generados</div><div class="value">328</div><div class="status">Exportaciones del periodo</div></div>
</div>
<div class="content-grid">
  <section class="card chart-box">
    <div class="section-title"><h2>Estado de expedientes</h2><a class="btn btn-secondary" href="{{ route('reportes') }}">Ver reportes</a></div>
    <div class="chart-placeholder"><span style="height:50%"></span><span style="height:78%"></span><span style="height:62%"></span><span style="height:90%"></span><span style="height:72%"></span><span style="height:38%"></span><span style="height:85%"></span></div>
    <div class="footer-note">Visualización referencial para el avance del prototipo.</div>
  </section>
  <section class="card">
    <div class="section-title"><h2>Actividad reciente</h2><span class="pill ok">Bitácora activa</span></div>
    <div class="list">
      <div class="list-item"><strong>Carga masiva de 240 expedientes</strong><span>Hace 12 min</span></div>
      <div class="list-item"><strong>Actualización de ubicación en Planta Tía Rosa</strong><span>Hace 28 min</span></div>
      <div class="list-item"><strong>Reporte de expedientes incompletos exportado</strong><span>Hace 42 min</span></div>
      <div class="list-item"><strong>Alta de responsable en catálogo base</strong><span>Hace 1 h</span></div>
    </div>
  </section>
</div>
<section class="card">
  <div class="section-title"><h2>Accesos rápidos</h2><span class="pill warn">Maquetado UI/UX</span></div>
  <div class="quick-links">
    <a href="{{ route('registro-individual') }}">Registro individual</a>
    <a href="{{ route('registro-masivo') }}">Registro masivo</a>
    <a href="{{ route('busqueda') }}">Búsqueda avanzada</a>
    <a href="{{ route('expediente') }}">Detalle de expediente</a>
  </div>
</section>

@endsection

@extends('layouts.app')
@section('title','SWAFI | Dashboard')
@section('section','Dashboard')
@section('page_title','Panel ejecutivo')
@section('content')
<div class="kpi-grid">
    <div class="kpi-card"><span>Total de facturas</span><strong>12,480</strong><small>+8% este mes</small></div>
    <div class="kpi-card"><span>Pendientes de validación</span><strong>246</strong><small>87 críticas</small></div>
    <div class="kpi-card"><span>Activos sin ubicación</span><strong>53</strong><small>Seguimiento inmediato</small></div>
    <div class="kpi-card"><span>Reportes generados</span><strong>118</strong><small>Últimos 30 días</small></div>
</div>
<div class="content-grid two-col">
    <section class="card">
        <div class="card-head"><h3>Resumen operativo</h3><a href="{{ route('swafi.busqueda') }}">Ver detalle</a></div>
        <div class="chart-placeholder bars">
            <div style="height:55%"></div><div style="height:82%"></div><div style="height:68%"></div><div style="height:91%"></div><div style="height:40%"></div>
        </div>
        <p class="muted">Distribución estimada de expedientes por estatus documental.</p>
    </section>
    <section class="card">
        <div class="card-head"><h3>Actividad reciente</h3><a href="{{ route('swafi.reportes') }}">Exportar</a></div>
        <ul class="timeline">
            <li><strong>Registro masivo</strong><span>1,240 expedientes cargados desde planta Guadalajara.</span></li>
            <li><strong>Validación fiscal</strong><span>Se actualizaron montos y depreciación de 84 activos.</span></li>
            <li><strong>Toma de inventario</strong><span>Almacén central cerró conciliación física del mes.</span></li>
            <li><strong>Seguridad</strong><span>Nuevo rol Supervisor asignado a contraloría patrimonial.</span></li>
        </ul>
    </section>
</div>
<div class="content-grid three-col">
    <a class="module-card" href="{{ route('swafi.registro-individual') }}"><h4>Registro individual</h4><p>Alta manual de expedientes con evidencia PDF y XML.</p></a>
    <a class="module-card" href="{{ route('swafi.registro-masivo') }}"><h4>Registro masivo</h4><p>Carga por layout con validación previa y bitácora.</p></a>
    <a class="module-card" href="{{ route('swafi.valores') }}"><h4>Valores fiscales</h4><p>Consulta y edición de importes, depreciación y vida útil.</p></a>
    <a class="module-card" href="{{ route('swafi.ubicacion') }}"><h4>Ubicación física</h4><p>Control de planta, área y responsable del activo fijo.</p></a>
    <a class="module-card" href="{{ route('swafi.busqueda') }}"><h4>Búsqueda avanzada</h4><p>Filtros combinados por proveedor, activo y estatus.</p></a>
    <a class="module-card" href="{{ route('swafi.expediente') }}"><h4>Ficha de expediente</h4><p>Vista integral con trazabilidad documental y financiera.</p></a>
</div>
@endsection

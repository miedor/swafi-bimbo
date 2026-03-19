@extends('layouts.app')
@section('title','SWAFI | Reportes')
@section('section','Reportes ad hoc')
@section('page_title','Constructor de reportes')
@section('content')
<div class="content-grid two-col">
    <section class="card">
        <h3>Parámetros de salida</h3>
        <div class="form-grid two-col">
            <label><span>Reporte</span><input type="text" value="Activos por ubicación"></label>
            <label><span>Formato</span><input type="text" value="PDF"></label>
            <label><span>Planta</span><input type="text" value="Todas"></label>
            <label><span>Periodo</span><input type="text" value="Marzo 2026"></label>
        </div>
        <div class="actions"><button class="btn btn-light">Vista previa</button><button class="btn btn-primary">Generar</button></div>
    </section>
    <section class="card">
        <h3>Reportes frecuentes</h3>
        <ul class="simple-list">
            <li>Facturas por planta</li>
            <li>Expedientes incompletos</li>
            <li>Valores fiscales y financieros</li>
            <li>Toma de inventario</li>
            <li>Bitácora de cambios</li>
        </ul>
    </section>
</div>
<section class="card">
    <div class="chart-placeholder donuts">
        <div></div><div></div><div></div>
    </div>
</section>
@endsection

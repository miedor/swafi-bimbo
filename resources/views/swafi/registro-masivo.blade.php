@extends('layouts.app')
@section('title','SWAFI | Registro masivo')
@section('section','Registro masivo')
@section('page_title','Carga masiva de expedientes')
@section('content')
<section class="card">
    <div class="card-head"><h3>Importación por layout</h3><div class="actions"><button class="btn btn-light">Descargar plantilla</button><button class="btn btn-primary">Procesar carga</button></div></div>
    <div class="upload-box large">Suelta aquí el archivo Excel o layout institucional para validación.</div>
    <div class="kpi-grid compact">
        <div class="kpi-card"><span>Registros leídos</span><strong>1,248</strong></div>
        <div class="kpi-card"><span>Exitosos</span><strong>1,196</strong></div>
        <div class="kpi-card"><span>Con observación</span><strong>37</strong></div>
        <div class="kpi-card"><span>Rechazados</span><strong>15</strong></div>
    </div>
    <table class="data-table">
        <thead><tr><th>Fila</th><th>Folio</th><th>Activo</th><th>Proveedor</th><th>Estatus</th><th>Observación</th></tr></thead>
        <tbody>
            <tr><td>12</td><td>FAC-00124</td><td>AF-009824</td><td>Tecnología Patrimonial</td><td><span class="tag ok">Correcto</span></td><td>Sin observaciones</td></tr>
            <tr><td>19</td><td>FAC-00131</td><td>AF-009921</td><td>Servicios Integrales</td><td><span class="tag warn">Validar</span></td><td>Falta XML adjunto</td></tr>
            <tr><td>28</td><td>FAC-00146</td><td>AF-010015</td><td>Activos del Bajío</td><td><span class="tag bad">Error</span></td><td>RFC inválido</td></tr>
        </tbody>
    </table>
</section>
@endsection

@extends('layouts.app')
@section('title','SWAFI | Ubicación')
@section('section','Ubicación e inventario')
@section('page_title','Localización física del activo fijo')
@section('content')
<div class="content-grid two-col">
    <section class="card">
        <h3>Asignación de ubicación</h3>
        <div class="form-grid two-col">
            <label><span>Planta</span><input type="text" value="Planta Guadalajara"></label>
            <label><span>Área</span><input type="text" value="Almacén"></label>
            <label><span>Edificio</span><input type="text" value="B"></label>
            <label><span>Piso</span><input type="text" value="PB"></label>
            <label><span>Pasillo</span><input type="text" value="4"></label>
            <label><span>Responsable</span><input type="text" value="Jefatura de almacén"></label>
        </div>
    </section>
    <section class="card">
        <h3>Semáforo de localización</h3>
        <div class="status-stack">
            <div class="status-line"><span class="dot ok"></span> Localizados: 2,842</div>
            <div class="status-line"><span class="dot warn"></span> Pendientes: 97</div>
            <div class="status-line"><span class="dot bad"></span> No encontrados: 14</div>
        </div>
    </section>
</div>
<section class="card">
    <table class="data-table">
        <thead><tr><th>Activo</th><th>Descripción</th><th>Planta</th><th>Ubicación</th><th>Responsable</th><th>Estatus</th></tr></thead>
        <tbody>
            <tr><td>AF-009824</td><td>Montacargas eléctrico</td><td>Guadalajara</td><td>Almacén 2 / Andén 4</td><td>Jefatura almacén</td><td><span class="tag ok">Localizado</span></td></tr>
            <tr><td>AF-010201</td><td>Servidor industrial</td><td>Toluca</td><td>Site principal</td><td>TI infraestructura</td><td><span class="tag warn">Pendiente</span></td></tr>
        </tbody>
    </table>
</section>
@endsection

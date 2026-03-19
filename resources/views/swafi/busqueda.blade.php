@extends('layouts.app')
@section('title','SWAFI | Búsqueda')
@section('section','Búsqueda avanzada')
@section('page_title','Consulta por criterios')
@section('content')
<section class="card">
    <div class="filter-row wrap">
        <input type="text" placeholder="Folio factura">
        <input type="text" placeholder="Proveedor">
        <input type="text" placeholder="RFC">
        <input type="text" placeholder="Número de activo">
        <input type="text" placeholder="Serie">
        <input type="text" placeholder="Planta">
        <input type="text" placeholder="Ubicación">
        <button class="btn btn-primary">Buscar</button>
    </div>
    <table class="data-table">
        <thead><tr><th>Folio</th><th>Proveedor</th><th>Activo</th><th>Serie</th><th>Planta</th><th>Estatus</th><th>Acción</th></tr></thead>
        <tbody>
            <tr><td>FAC-2026-00124</td><td>Tecnología Patrimonial</td><td>AF-009824</td><td>MCG-AXP-8842</td><td>Guadalajara</td><td><span class="tag ok">Completo</span></td><td><a href="{{ route('swafi.expediente') }}">Consultar</a></td></tr>
            <tr><td>FAC-2026-00125</td><td>Servicios Integrales</td><td>AF-009825</td><td>SRV-00118</td><td>Toluca</td><td><span class="tag warn">Incompleto</span></td><td><a href="{{ route('swafi.expediente') }}">Consultar</a></td></tr>
        </tbody>
    </table>
</section>
@endsection

@extends('layouts.app')
@section('title','SWAFI | Catálogos')
@section('section','Catálogos base')
@section('page_title','Administración de catálogos')
@section('content')
<section class="card">
    <div class="card-head"><h3>Catálogos del sistema</h3><div class="actions"><button class="btn btn-light">Nuevo catálogo</button><button class="btn btn-primary">Nuevo registro</button></div></div>
    <table class="data-table">
        <thead><tr><th>Catálogo</th><th>Registros</th><th>Última actualización</th><th>Estatus</th></tr></thead>
        <tbody>
            <tr><td>Proveedores</td><td>684</td><td>19/03/2026</td><td><span class="tag ok">Activo</span></td></tr>
            <tr><td>Plantas</td><td>22</td><td>18/03/2026</td><td><span class="tag ok">Activo</span></td></tr>
            <tr><td>Centros de costo</td><td>318</td><td>17/03/2026</td><td><span class="tag ok">Activo</span></td></tr>
            <tr><td>Tipos de activo</td><td>46</td><td>19/03/2026</td><td><span class="tag warn">Revisión</span></td></tr>
        </tbody>
    </table>
</section>
@endsection

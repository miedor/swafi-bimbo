@extends('layouts.app')

@section('title', 'Catálogos base | SWAFI')
@section('page_title', 'Catálogos base')
@section('page_subtitle', 'Administración de catálogos transversales del sistema')
@section('breadcrumb', 'Catálogos base')
@section('content')

<section class="card table-card">
  <div class="section-title"><h2>Catálogos base</h2><div class="tabs"><span class="tab">Proveedores</span><span class="tab">Plantas</span><span class="tab">Centros de costo</span><span class="tab">Responsables</span></div></div>
  <table>
    <thead><tr><th>Catálogo</th><th>Registros</th><th>Estatus</th><th>Acción</th></tr></thead>
    <tbody>
      <tr><td>Proveedores</td><td>1,280</td><td><span class="pill ok">Activo</span></td><td>Administrar</td></tr>
      <tr><td>Plantas</td><td>42</td><td><span class="pill ok">Activo</span></td><td>Administrar</td></tr>
      <tr><td>Centros de costo</td><td>315</td><td><span class="pill warn">Revisión</span></td><td>Administrar</td></tr>
    </tbody>
  </table>
</section>

@endsection

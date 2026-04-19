@extends('layouts.app')

@section('title', 'Catálogos base | SWAFI')
@section('page_title', 'Catálogos base')
@section('page_subtitle', 'Administración de catálogos transversales del sistema')
@section('breadcrumb', 'Catálogos base')
@section('content')

<section class="card form-card">
  <div class="section-title">
    <h2>Mantenimiento de catálogos</h2>
    <div class="tabs"><span class="tab">Proveedores</span><span class="tab">Plantas</span><span class="tab">Centros de costo</span><span class="tab">Responsables</span></div>
  </div>
  <div class="form-row three">
    <label><span>Tipo de catálogo</span><select><option>Proveedores</option></select></label>
    <label><span>Clave</span><input value="PROV-001"></label>
    <label><span>Descripción</span><input value="Proveedor industrial del centro"></label>
  </div>
  <div class="form-row three">
    <label><span>Estatus</span><select><option>Activo</option></select></label>
    <label><span>Responsable</span><input value="María Hernández"></label>
    <label><span>Observaciones</span><input value="Alta inicial para pruebas del prototipo"></label>
  </div>
  <div class="action-group"><span class="tab">Guardar</span><span class="tab">Editar</span><span class="tab tab-danger">Eliminar</span><span class="tab">Limpiar</span></div>
</section>

<section class="card table-card" style="margin-top:20px">
  <div class="section-title"><h2>Catálogos registrados</h2><span class="pill ok">CRUD habilitado</span></div>
  <table>
    <thead><tr><th>Catálogo</th><th>Registros</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr><td>Proveedores</td><td>1,280</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>Plantas</td><td>42</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>Centros de costo</td><td>315</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
    </tbody>
  </table>

  <div class="table-footer">
    <div class="table-summary">Mostrando 1–10 de 248 resultados</div>
    <div class="table-pagination">
      <span class="page-link disabled">Anterior</span>
      <span class="page-link active">1</span>
      <span class="page-link">2</span>
      <span class="page-link">3</span>
      <span class="page-link">Siguiente</span>
    </div>
    <div class="table-page-size">
      <span>Ver</span>
      <select>
        <option selected>10</option>
        <option>25</option>
        <option>50</option>
      </select>
      <span>registros</span>
    </div>
  </div>

</section>

@endsection

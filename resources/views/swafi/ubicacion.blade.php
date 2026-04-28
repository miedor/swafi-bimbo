@extends('layouts.app')

@section('title', 'Ubicación física e inventario | SWAFI')
@section('page_title', 'Ubicación física e inventario')
@section('page_subtitle', 'Control de localización de activos y seguimiento de toma de inventario')
@section('breadcrumb', 'Ubicación física e inventario')
@section('content')

<section class="card form-card">
  <div class="section-title"><h2>Ubicación física e inventario</h2><span class="pill danger">74 pendientes</span></div>
  <div class="form-row three">
    <label><span>Planta</span><input value="Planta Santa María"></label>
    <label><span>Área</span><input value="Producción"></label>
    <label><span>Edificio / Piso</span><input value="Edificio B / Piso 1"></label>
  </div>
  <div class="form-row three">
    <label><span>Pasillo</span><input value="Pasillo B"></label>
    <label><span>Responsable</span><input value="Mantenimiento Planta"></label>
    <label><span>Código interno</span><input value="UBI-PLT-B-14"></label>
  </div>
  <div class="action-group"><span class="tab">Guardar</span><span class="tab">Editar</span><span class="tab tab-danger">Eliminar</span><span class="tab">Registrar toma</span></div>
</section>

<section class="card table-card" style="margin-top:20px">
  <div class="section-title">
    <h2>Consulta de ubicación e inventario</h2>
    <div class="tabs">
      <span class="tab">Exportar Excel</span>
      <span class="tab">Exportar PDF</span>
    </div>
  </div>

  <div class="query-toolbar">
    <div class="query-grid query-grid-four">
      <label><span>Planta</span><select><option>Todas</option><option>Santa María</option><option>Tía Rosa</option></select></label>
      <label><span>Área</span><select><option>Todas</option><option>Producción</option><option>Almacén</option></select></label>
      <label><span>Activo desde</span><input value="AF-PLT-00001"></label>
      <label><span>Activo hasta</span><input value="AF-PLT-99999"></label>
    </div>
    <div class="query-grid query-grid-four">
      <label><span>Fecha inventario desde</span><input type="date" value="2026-03-01"></label>
      <label><span>Fecha inventario hasta</span><input type="date" value="2026-03-31"></label>
      <label><span>Estatus ubicación</span><select><option>Todos</option><option>Localizado</option><option>No encontrado</option></select></label>
      <label><span>Responsable</span><input value="Jorge"></label>
    </div>
    <div class="action-group">
      <span class="tab">Consultar</span>
      <span class="tab">Limpiar filtros</span>
      <span class="tab">Exportar consulta</span>
    </div>
  </div>

  <table>
    <thead><tr><th>Activo</th><th>Ubicación</th><th>Responsable</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr><td>AF-PLT-00945</td><td>Línea 3 / Pasillo B</td><td>Jorge Méndez</td><td><span class="pill ok">Localizado</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>AF-PLT-00946</td><td>Almacén temporal</td><td>María Ponce</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>AF-PLT-00947</td><td>No identificada</td><td>Sin asignar</td><td><span class="pill danger">No encontrado</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
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

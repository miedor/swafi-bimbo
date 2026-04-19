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

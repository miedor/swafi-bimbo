@extends('layouts.app')

@section('title', 'Búsqueda avanzada | SWAFI')
@section('page_title', 'Búsqueda avanzada')
@section('page_subtitle', 'Localización por múltiples criterios del expediente y del activo')
@section('breadcrumb', 'Búsqueda avanzada')
@section('content')

<section class="card form-card">
  <div class="section-title">
    <h2>Búsqueda avanzada</h2>
    <div class="tabs">
      <span class="tab">Consultar</span>
      <span class="tab">Exportar Excel</span>
      <span class="tab">Exportar PDF</span>
    </div>
  </div>

  <div class="query-grid query-grid-four">
    <label><span>Folio factura</span><input value="FAC-2026"></label>
    <label><span>Proveedor</span><input value="Proveedor industrial"></label>
    <label><span>RFC</span><input value="PIC010101ABC"></label>
    <label><span>Número de activo</span><input value="AF-PLT"></label>
  </div>

  <div class="query-grid query-grid-four">
    <label><span>Planta</span><input value="Santa María"></label>
    <label><span>Ubicación</span><input value="Línea 3"></label>
    <label><span>Fecha desde</span><input type="date" value="2026-03-01"></label>
    <label><span>Fecha hasta</span><input type="date" value="2026-03-31"></label>
  </div>

  <div class="query-grid query-grid-four">
    <label><span>Monto desde</span><input type="number" step="0.01" value="50000"></label>
    <label><span>Monto hasta</span><input type="number" step="0.01" value="250000"></label>
    <label><span>Estatus documental</span><select><option>Todos</option><option selected>Completo</option><option>Incompleto</option></select></label>
    <label><span>Serie o modelo</span><input value="TRS / Belt Pro"></label>
  </div>

  <div class="action-group">
    <span class="tab">Consultar</span>
    <span class="tab">Limpiar filtros</span>
    <span class="tab">Exportar consulta</span>
  </div>
</section>

<section class="card table-card" style="margin-top:20px">
  <div class="section-title"><h2>Resultados de consulta</h2><span class="pill ok">Consulta por rango habilitada</span></div>
  <table>
    <thead><tr><th>Folio</th><th>Activo</th><th>Proveedor</th><th>Planta</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr><td>FAC-184</td><td>AF-PLT-00945</td><td>ACME Industrial</td><td>Santa María</td><td><span class="pill ok">Completo</span></td><td><div class="table-actions"><a href="{{ route('expediente') }}">Consultar</a><a href="{{ route('registro-individual') }}">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>FAC-185</td><td>AF-PLT-00946</td><td>Equipos del Centro</td><td>Tía Rosa</td><td><span class="pill ok">Completo</span></td><td><div class="table-actions"><a href="{{ route('expediente') }}">Consultar</a><a href="{{ route('registro-individual') }}">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>FAC-186</td><td>AF-PLT-00947</td><td>Refacciones Delta</td><td>Santa María</td><td><span class="pill warn">Incompleto</span></td><td><div class="table-actions"><a href="{{ route('expediente') }}">Consultar</a><a href="{{ route('registro-individual') }}">Editar</a><a href="#">Eliminar</a></div></td></tr>
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

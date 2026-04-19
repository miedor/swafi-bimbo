@extends('layouts.app')

@section('title', 'Valores fiscales y financieros | SWAFI')
@section('page_title', 'Valores fiscales y financieros')
@section('page_subtitle', 'Consulta y control de datos contables del activo fijo')
@section('breadcrumb', 'Valores fiscales y financieros')
@section('content')

<section class="card table-card">
  <div class="section-title"><h2>Valores fiscales y financieros</h2><div class="tabs"><span class="tab">Planta</span><span class="tab">Proveedor</span><span class="tab">Año</span><span class="tab">Tipo de activo</span></div></div>
  <div class="action-group action-group-spaced"><span class="tab">Guardar</span><span class="tab">Editar</span><span class="tab tab-danger">Eliminar</span><span class="tab">Exportar</span></div>
  <table>
    <thead><tr><th>Folio</th><th>Activo</th><th>Valor fiscal</th><th>Depreciación</th><th>Valor en libros</th><th>Valor financiero</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr><td>FAC-184</td><td>AF-PLT-00945</td><td>$ 185,000</td><td>$ 12,400</td><td>$ 172,600</td><td>$ 182,300</td><td><span class="pill ok">Completo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>FAC-185</td><td>AF-PLT-00946</td><td>$ 98,500</td><td>$ 6,900</td><td>$ 91,600</td><td>$ 95,000</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>FAC-186</td><td>AF-PLT-00947</td><td>$ 240,000</td><td>$ 16,500</td><td>$ 223,500</td><td>$ 236,200</td><td><span class="pill ok">Completo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
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

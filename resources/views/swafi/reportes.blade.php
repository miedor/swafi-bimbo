@extends('layouts.app')

@section('title', 'Reportes ad hoc | SWAFI')
@section('page_title', 'Reportes ad hoc')
@section('page_subtitle', 'Generación dinámica de reportes ejecutivos y operativos')
@section('breadcrumb', 'Reportes')
@section('content')

<section class="card">
  <div class="section-title"><h2>Reportes ad hoc</h2><div class="tabs"><span class="tab">PDF</span><span class="tab">Excel</span><span class="tab">Vista previa</span></div></div>
  <div class="quick-links">
    <a href="#">Facturas por planta</a>
    <a href="#">Activos por ubicación</a>
    <a href="#">Expedientes incompletos</a>
    <a href="#">Valores fiscales y financieros</a>
  </div>
</section>
<section class="card table-card" style="margin-top:20px">
  <table>
    <thead><tr><th>Reporte</th><th>Última ejecución</th><th>Formato</th><th>Responsable</th></tr></thead>
    <tbody>
      <tr><td>Inventario por ubicación</td><td>19/03/2026 17:22</td><td>Excel</td><td>Analista patrimonial</td></tr>
      <tr><td>Expedientes incompletos</td><td>19/03/2026 16:48</td><td>PDF</td><td>Contabilidad</td></tr>
      <tr><td>Depreciación mensual</td><td>19/03/2026 15:10</td><td>Excel</td><td>Finanzas</td></tr>
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

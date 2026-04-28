@extends('layouts.app')

@section('title', 'Reportes ad hoc | SWAFI')
@section('page_title', 'Reportes ad hoc')
@section('page_subtitle', 'Generación dinámica de reportes ejecutivos y operativos')
@section('breadcrumb', 'Reportes')
@section('content')

<section class="card">
  <div class="section-title">
    <h2>Consulta para generación de reportes</h2>
    <div class="tabs">
      <span class="tab">Exportar Excel</span>
      <span class="tab">Exportar PDF</span>
      <span class="tab">Vista previa</span>
    </div>
  </div>

  <div class="quick-links">
    <a href="#">Facturas por planta</a>
    <a href="#">Activos por ubicación</a>
    <a href="#">Expedientes incompletos</a>
    <a href="#">Valores fiscales y financieros</a>
  </div>

  <div class="query-toolbar" style="margin-top:18px">
    <div class="query-grid query-grid-four">
      <label><span>Tipo de reporte</span><select><option>Expedientes incompletos</option><option>Facturas por planta</option><option>Valores fiscales</option></select></label>
      <label><span>Planta</span><select><option>Todas</option><option>Santa María</option><option>Tía Rosa</option></select></label>
      <label><span>Periodo desde</span><input type="date" value="2026-03-01"></label>
      <label><span>Periodo hasta</span><input type="date" value="2026-03-31"></label>
    </div>
    <div class="query-grid query-grid-four">
      <label><span>Centro de costo desde</span><input value="CC-1000"></label>
      <label><span>Centro de costo hasta</span><input value="CC-4999"></label>
      <label><span>Monto desde</span><input type="number" step="0.01" value="10000"></label>
      <label><span>Monto hasta</span><input type="number" step="0.01" value="500000"></label>
    </div>
    <div class="action-group">
      <span class="tab">Consultar</span>
      <span class="tab">Limpiar filtros</span>
      <span class="tab">Exportar consulta</span>
    </div>
  </div>
</section>

<section class="card table-card" style="margin-top:20px">
  <div class="section-title"><h2>Resultados del reporte</h2><span class="pill ok">Exportación habilitada</span></div>
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

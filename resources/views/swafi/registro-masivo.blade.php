@extends('layouts.app')

@section('title', 'Registro masivo | SWAFI')
@section('page_title', 'Registro masivo')
@section('page_subtitle', 'Carga de expedientes mediante layout Excel y validación previa')
@section('breadcrumb', 'Registro masivo')
@section('content')

<section class="card">
  <div class="section-title"><h2>Registro masivo por layout</h2><span class="pill ok">Importación referencial</span></div>
  <div class="list">
    <div class="list-item"><strong>Archivo cargado</strong><span>layout_activo_fijo_marzo.xlsx</span></div>
    <div class="list-item"><strong>Registros detectados</strong><span>240</span></div>
    <div class="list-item"><strong>Registros correctos</strong><span>228</span></div>
    <div class="list-item"><strong>Registros observados</strong><span>12</span></div>
  </div>
</section>
<section class="card table-card" style="margin-top:20px">
  <div class="section-title"><h2>Vista previa de validación</h2><div><span class="tab">Descargar plantilla</span><span class="tab">Validar archivo</span><span class="tab">Procesar carga</span></div></div>
  <table>
    <thead><tr><th>ID activo</th><th>Proveedor</th><th>Planta</th><th>Monto</th><th>Estatus</th></tr></thead>
    <tbody>
      <tr><td>AF-1001</td><td>ACME Industrial</td><td>Planta Marinela</td><td>$ 85,000</td><td><span class="pill ok">Aceptado</span></td></tr>
      <tr><td>AF-1002</td><td>Equipos del Centro</td><td>Planta Tía Rosa</td><td>$ 122,300</td><td><span class="pill warn">Observado</span></td></tr>
      <tr><td>AF-1003</td><td>Refacciones Delta</td><td>Planta Santa María</td><td>$ 48,900</td><td><span class="pill ok">Aceptado</span></td></tr>
    </tbody>
  </table>
</section>

@endsection

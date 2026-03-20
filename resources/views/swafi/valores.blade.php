@extends('layouts.app')

@section('title', 'Valores fiscales y financieros | SWAFI')
@section('page_title', 'Valores fiscales y financieros')
@section('page_subtitle', 'Consulta y control de datos contables del activo fijo')
@section('breadcrumb', 'Valores fiscales y financieros')
@section('content')

<section class="card table-card">
  <div class="section-title"><h2>Valores fiscales y financieros</h2><div class="tabs"><span class="tab">Planta</span><span class="tab">Proveedor</span><span class="tab">Año</span><span class="tab">Tipo de activo</span></div></div>
  <table>
    <thead><tr><th>Folio</th><th>Activo</th><th>Valor fiscal</th><th>Depreciación</th><th>Valor en libros</th><th>Valor financiero</th><th>Estatus</th></tr></thead>
    <tbody>
      <tr><td>FAC-184</td><td>AF-PLT-00945</td><td>$ 185,000</td><td>$ 12,400</td><td>$ 172,600</td><td>$ 182,300</td><td><span class="pill ok">Completo</span></td></tr>
      <tr><td>FAC-185</td><td>AF-PLT-00946</td><td>$ 98,500</td><td>$ 6,900</td><td>$ 91,600</td><td>$ 95,000</td><td><span class="pill warn">Revisión</span></td></tr>
      <tr><td>FAC-186</td><td>AF-PLT-00947</td><td>$ 240,000</td><td>$ 16,500</td><td>$ 223,500</td><td>$ 236,200</td><td><span class="pill ok">Completo</span></td></tr>
    </tbody>
  </table>
</section>

@endsection

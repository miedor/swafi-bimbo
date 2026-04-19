@extends('layouts.app')

@section('title', 'Búsqueda avanzada | SWAFI')
@section('page_title', 'Búsqueda avanzada')
@section('page_subtitle', 'Localización por múltiples criterios del expediente y del activo')
@section('breadcrumb', 'Búsqueda avanzada')
@section('content')

<section class="card form-card">
  <div class="section-title"><h2>Búsqueda avanzada</h2><span class="pill ok">Consulta en segundos</span></div>
  <div class="form-row three">
    <label><span>Folio factura</span><input value="FAC-2026"></label>
    <label><span>Proveedor</span><input value="Proveedor industrial"></label>
    <label><span>RFC</span><input value="PIC010101ABC"></label>
  </div>
  <div class="form-row three">
    <label><span>Número de activo</span><input value="AF-PLT"></label>
    <label><span>Serie</span><input value="TRS"></label>
    <label><span>Planta</span><input value="Santa María"></label>
  </div>
  <div class="form-row three">
    <label><span>Ubicación</span><input value="Línea 3"></label>
    <label><span>Fecha</span><input value="2026-03"></label>
    <label><span>Estatus documental</span><select><option>Completo</option></select></label>
  </div>
</section>
<section class="card table-card" style="margin-top:20px">
  <table>
    <thead><tr><th>Folio</th><th>Activo</th><th>Proveedor</th><th>Planta</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr><td>FAC-184</td><td>AF-PLT-00945</td><td>ACME Industrial</td><td>Santa María</td><td><span class="pill ok">Completo</span></td><td><div class="table-actions"><a href="{{ route('expediente') }}">Consultar</a><a href="{{ route('registro-individual') }}">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>FAC-185</td><td>AF-PLT-00946</td><td>Equipos del Centro</td><td>Tía Rosa</td><td><span class="pill warn">Observado</span></td><td><div class="table-actions"><a href="{{ route('expediente') }}">Consultar</a><a href="{{ route('registro-individual') }}">Editar</a><a href="#">Eliminar</a></div></td></tr>
    </tbody>
  </table>
</section>

@endsection

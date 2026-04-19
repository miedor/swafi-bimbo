@extends('layouts.app')

@section('title', 'Registro individual | SWAFI')
@section('page_title', 'Registro individual')
@section('page_subtitle', 'Captura manual y mantenimiento del expediente de activo fijo')
@section('breadcrumb', 'Registro individual')
@section('content')

<section class="card form-card">
  <div class="section-title"><h2>Registro individual de expedientes</h2><div><a class="btn btn-secondary" href="{{ route('dashboard') }}">Volver al dashboard</a></div></div>
  <div class="form-row three">
    <label><span>Folio de factura</span><input value="FAC-2026-000184"></label>
    <label><span>RFC proveedor</span><input value="BIM011108DJ5"></label>
    <label><span>Nombre del proveedor</span><input value="Proveedor industrial del centro"></label>
  </div>
  <div class="form-row three">
    <label><span>Fecha de factura</span><input value="2026-03-18"></label>
    <label><span>Número de activo fijo</span><input value="AF-PLT-00945"></label>
    <label><span>Descripción del bien</span><input value="Transportador de banda"></label>
  </div>
  <div class="form-row three">
    <label><span>Serie</span><input value="TRS-88-A"></label>
    <label><span>Marca</span><input value="Siemens"></label>
    <label><span>Modelo</span><input value="Belt Pro 500"></label>
  </div>
  <div class="form-row three">
    <label><span>Monto fiscal</span><input value="$ 185,000.00"></label>
    <label><span>Valor financiero</span><input value="$ 182,300.00"></label>
    <label><span>Moneda</span><select><option>MXN</option></select></label>
  </div>
  <div class="form-row three">
    <label><span>Centro de costo</span><input value="CC-4450"></label>
    <label><span>Planta o sucursal</span><input value="Planta Santa María"></label>
    <label><span>Ubicación física</span><input value="Línea 3 / Pasillo B"></label>
  </div>
  <div class="form-row">
    <label><span>Responsable</span><input value="Jorge Méndez"></label>
    <label><span>Documento PDF/XML</span><input value="factura_184.pdf, factura_184.xml"></label>
  </div>
  <label><span>Observaciones</span><textarea>Expediente capturado para fines demostrativos del prototipo UI/UX.</textarea></label>
  <div class="action-group"><span class="tab">Guardar</span><span class="tab">Editar</span><span class="tab tab-danger">Eliminar</span><span class="tab">Cancelar</span><span class="tab">Vista previa</span></div>
</section>

@endsection

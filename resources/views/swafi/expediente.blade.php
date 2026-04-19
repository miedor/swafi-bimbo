@extends('layouts.app')

@section('title', 'Detalle de expediente | SWAFI')
@section('page_title', 'Detalle de expediente')
@section('page_subtitle', 'Vista integral del expediente documental y patrimonial')
@section('breadcrumb', 'Detalle de expediente')
@section('content')

<section class="card">
  <div class="section-title"><h2>Detalle de expediente</h2><span class="pill ok">Ficha ejecutiva</span></div>
  <div class="action-group action-group-spaced"><span class="tab">Editar</span><span class="tab tab-danger">Eliminar</span><span class="tab">Descargar</span></div>
  <div class="tabs"><span class="tab">Datos generales</span><span class="tab">Activo fijo</span><span class="tab">Valores</span><span class="tab">Ubicación</span><span class="tab">Documentos</span><span class="tab">Historial</span></div>
  <div class="meta-grid">
    <div class="meta-box"><strong>Folio factura</strong><div>FAC-2026-000184</div></div>
    <div class="meta-box"><strong>ID activo</strong><div>AF-PLT-00945</div></div>
    <div class="meta-box"><strong>Proveedor</strong><div>ACME Industrial</div></div>
    <div class="meta-box"><strong>Estatus</strong><div><span class="pill ok">Completo</span></div></div>
    <div class="meta-box"><strong>Valor fiscal</strong><div>$ 185,000</div></div>
    <div class="meta-box"><strong>Valor financiero</strong><div>$ 182,300</div></div>
    <div class="meta-box"><strong>Ubicación física</strong><div>Línea 3 / Pasillo B</div></div>
    <div class="meta-box"><strong>Responsable</strong><div>Jorge Méndez</div></div>
  </div>
</section>
<section class="card" style="margin-top:20px">
  <div class="list">
    <div class="list-item"><strong>Documentos asociados</strong><span>PDF, XML, evidencia de alta</span></div>
    <div class="list-item"><strong>Última modificación</strong><span>19/03/2026 17:44 por admin.swafi</span></div>
    <div class="list-item"><strong>Trazabilidad</strong><span>Creación, revisión, actualización de ubicación y exportación de reporte</span></div>
  </div>
</section>

@endsection

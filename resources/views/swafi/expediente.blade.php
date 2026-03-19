@extends('layouts.app')
@section('title','SWAFI | Expediente')
@section('section','Detalle de expediente')
@section('page_title','Ficha integral del expediente')
@section('content')
<div class="content-grid two-col">
    <section class="card detail-block">
        <h3>Datos generales</h3>
        <dl>
            <div><dt>Folio</dt><dd>FAC-2026-00124</dd></div>
            <div><dt>Proveedor</dt><dd>Tecnología Patrimonial del Centro</dd></div>
            <div><dt>RFC</dt><dd>BIM800123AA1</dd></div>
            <div><dt>Fecha factura</dt><dd>19/03/2026</dd></div>
        </dl>
    </section>
    <section class="card detail-block">
        <h3>Datos del activo</h3>
        <dl>
            <div><dt>Activo fijo</dt><dd>AF-009824</dd></div>
            <div><dt>Serie</dt><dd>MCG-AXP-8842</dd></div>
            <div><dt>Marca / modelo</dt><dd>Toyota / 8FBE20</dd></div>
            <div><dt>Ubicación</dt><dd>Almacén 2 / Andén 4</dd></div>
        </dl>
    </section>
    <section class="card detail-block">
        <h3>Valores fiscales y financieros</h3>
        <dl>
            <div><dt>Monto fiscal</dt><dd>$482,000.00</dd></div>
            <div><dt>Valor financiero</dt><dd>$469,500.00</dd></div>
            <div><dt>Depreciación</dt><dd>$24,100.00</dd></div>
            <div><dt>Vida útil</dt><dd>10 años</dd></div>
        </dl>
    </section>
    <section class="card detail-block">
        <h3>Trazabilidad</h3>
        <ul class="simple-list">
            <li>Alta inicial del expediente</li>
            <li>Validación fiscal completada</li>
            <li>Asignación de ubicación física</li>
            <li>Carga de documentos XML y PDF</li>
        </ul>
    </section>
</div>
@endsection

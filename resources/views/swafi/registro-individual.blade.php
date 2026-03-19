@extends('layouts.app')
@section('title','SWAFI | Registro individual')
@section('section','Registro individual')
@section('page_title','Alta individual de expedientes')
@section('content')
<section class="card">
    <div class="card-head"><h3>Captura del expediente</h3><div class="actions"><button class="btn btn-light">Vista previa</button><button class="btn btn-primary">Guardar</button></div></div>
    <div class="form-grid three-col">
        <label><span>Folio de factura</span><input type="text" value="FAC-2026-00124"></label>
        <label><span>RFC proveedor</span><input type="text" value="BIM800123AA1"></label>
        <label><span>Proveedor</span><input type="text" value="Tecnología Patrimonial del Centro"></label>
        <label><span>Fecha de factura</span><input type="text" value="19/03/2026"></label>
        <label><span>Número de activo fijo</span><input type="text" value="AF-009824"></label>
        <label><span>Descripción del bien</span><input type="text" value="Montacargas eléctrico"></label>
        <label><span>Serie</span><input type="text" value="MCG-AXP-8842"></label>
        <label><span>Marca</span><input type="text" value="Toyota"></label>
        <label><span>Modelo</span><input type="text" value="8FBE20"></label>
        <label><span>Monto fiscal</span><input type="text" value="$ 482,000.00"></label>
        <label><span>Valor financiero</span><input type="text" value="$ 469,500.00"></label>
        <label><span>Moneda</span><input type="text" value="MXN"></label>
        <label><span>Centro de costo</span><input type="text" value="CC-ALM-201"></label>
        <label><span>Planta / sucursal</span><input type="text" value="Planta Guadalajara"></label>
        <label><span>Ubicación física</span><input type="text" value="Almacén 2 / Andén 4"></label>
        <label class="full"><span>Observaciones</span><textarea>Equipo incorporado al inventario 2026 con documentación completa.</textarea></label>
    </div>
    <div class="upload-box">Arrastra aquí PDF / XML o selecciona archivos de evidencia.</div>
</section>
@endsection

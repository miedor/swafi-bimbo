@extends('layouts.app')
@section('title','SWAFI | Valores')
@section('section','Valores fiscales y financieros')
@section('page_title','Consulta de valores fiscales y financieros')
@section('content')
<section class="card">
    <div class="filter-row">
        <input type="text" placeholder="Proveedor">
        <input type="text" placeholder="Planta">
        <input type="text" placeholder="Año">
        <input type="text" placeholder="Tipo de activo">
        <button class="btn btn-primary">Filtrar</button>
    </div>
    <table class="data-table">
        <thead><tr><th>Folio</th><th>Activo</th><th>Valor fiscal</th><th>Depreciación</th><th>Valor en libros</th><th>Vida útil</th><th>Estatus</th></tr></thead>
        <tbody>
            <tr><td>FAC-2026-00124</td><td>AF-009824</td><td>$482,000.00</td><td>$24,100.00</td><td>$457,900.00</td><td>10 años</td><td><span class="tag ok">Vigente</span></td></tr>
            <tr><td>FAC-2026-00125</td><td>AF-009825</td><td>$156,000.00</td><td>$7,800.00</td><td>$148,200.00</td><td>5 años</td><td><span class="tag warn">Revisión</span></td></tr>
            <tr><td>FAC-2026-00126</td><td>AF-009826</td><td>$1,280,000.00</td><td>$64,000.00</td><td>$1,216,000.00</td><td>12 años</td><td><span class="tag ok">Vigente</span></td></tr>
        </tbody>
    </table>
</section>
@endsection

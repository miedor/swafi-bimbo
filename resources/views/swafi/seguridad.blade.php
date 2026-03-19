@extends('layouts.app')
@section('title','SWAFI | Seguridad')
@section('section','Seguridad y acceso')
@section('page_title','Gestión de usuarios, roles y bitácora')
@section('content')
<div class="kpi-grid compact">
    <div class="kpi-card"><span>Usuarios</span><strong>54</strong></div>
    <div class="kpi-card"><span>Roles</span><strong>5</strong></div>
    <div class="kpi-card"><span>Accesos hoy</span><strong>142</strong></div>
    <div class="kpi-card"><span>Movimientos auditados</span><strong>1,286</strong></div>
</div>
<section class="card">
    <table class="data-table">
        <thead><tr><th>Usuario</th><th>Rol</th><th>Correo</th><th>Último acceso</th><th>Estatus</th></tr></thead>
        <tbody>
            <tr><td>María Gómez</td><td>Administrador</td><td>mgomez@empresa.com</td><td>19/03/2026 13:11</td><td><span class="tag ok">Activo</span></td></tr>
            <tr><td>José Luna</td><td>Capturista</td><td>jluna@empresa.com</td><td>19/03/2026 12:42</td><td><span class="tag ok">Activo</span></td></tr>
            <tr><td>Laura Pérez</td><td>Auditor</td><td>lperez@empresa.com</td><td>18/03/2026 18:02</td><td><span class="tag warn">Bloqueado</span></td></tr>
        </tbody>
    </table>
</section>
@endsection

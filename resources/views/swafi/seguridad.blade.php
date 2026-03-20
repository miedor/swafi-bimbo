@extends('layouts.app')

@section('title', 'Seguridad y acceso | SWAFI')
@section('page_title', 'Seguridad y acceso')
@section('page_subtitle', 'Gestión de usuarios, roles, permisos e historial de accesos')
@section('breadcrumb', 'Seguridad y acceso')
@section('content')

<section class="card table-card">
  <div class="section-title"><h2>Seguridad y acceso</h2><span class="pill ok">Bitácora habilitada</span></div>
  <table>
    <thead><tr><th>Usuario</th><th>Rol</th><th>Módulos</th><th>Último acceso</th><th>Estatus</th></tr></thead>
    <tbody>
      <tr><td>admin.swafi</td><td>Administrador</td><td>Todos</td><td>19/03/2026 18:02</td><td><span class="pill ok">Activo</span></td></tr>
      <tr><td>captura.af</td><td>Capturista</td><td>Registro / Búsqueda</td><td>19/03/2026 16:40</td><td><span class="pill ok">Activo</span></td></tr>
      <tr><td>auditoria.int</td><td>Auditor</td><td>Consulta / Reportes</td><td>18/03/2026 09:15</td><td><span class="pill warn">Pendiente</span></td></tr>
    </tbody>
  </table>
</section>

@endsection

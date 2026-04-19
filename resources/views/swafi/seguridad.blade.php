@extends('layouts.app')

@section('title', 'Seguridad y acceso | SWAFI')
@section('page_title', 'Seguridad y acceso')
@section('page_subtitle', 'Gestión de usuarios, roles, permisos e historial de accesos')
@section('breadcrumb', 'Seguridad y acceso')
@section('content')

<section class="card form-card">
  <div class="section-title"><h2>Administración de usuarios</h2><span class="pill ok">Bitácora habilitada</span></div>
  <div class="form-row three">
    <label><span>Usuario</span><input value="captura.af"></label>
    <label><span>Nombre completo</span><input value="Ana López"></label>
    <label><span>Correo</span><input value="ana.lopez@grupobimbo.com"></label>
  </div>
  <div class="form-row three">
    <label><span>Rol</span><select><option>Capturista</option></select></label>
    <label><span>Estatus</span><select><option>Activo</option></select></label>
    <label><span>Módulos autorizados</span><input value="Registro individual, Búsqueda, Detalle de expediente"></label>
  </div>
  <div class="action-group"><span class="tab">Guardar</span><span class="tab">Editar</span><span class="tab tab-danger">Eliminar</span><span class="tab">Restablecer contraseña</span></div>
</section>

<section class="card table-card" style="margin-top:20px">
  <div class="section-title"><h2>Usuarios registrados</h2><span class="pill ok">CRUD habilitado</span></div>
  <table>
    <thead><tr><th>Usuario</th><th>Rol</th><th>Módulos</th><th>Último acceso</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr><td>admin.swafi</td><td>Administrador</td><td>Todos</td><td>19/03/2026 18:02</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>captura.af</td><td>Capturista</td><td>Registro / Búsqueda</td><td>19/03/2026 16:40</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
      <tr><td>auditoria.int</td><td>Auditor</td><td>Consulta / Reportes</td><td>18/03/2026 09:15</td><td><span class="pill ok">Activo</span></td><td><div class="table-actions"><a href="#">Consultar</a><a href="#">Editar</a><a href="#">Eliminar</a></div></td></tr>
    </tbody>
  </table>

  <div class="table-footer">
    <div class="table-summary">Mostrando 1–10 de 248 resultados</div>
    <div class="table-pagination">
      <span class="page-link disabled">Anterior</span>
      <span class="page-link active">1</span>
      <span class="page-link">2</span>
      <span class="page-link">3</span>
      <span class="page-link">Siguiente</span>
    </div>
    <div class="table-page-size">
      <span>Ver</span>
      <select>
        <option selected>10</option>
        <option>25</option>
        <option>50</option>
      </select>
      <span>registros</span>
    </div>
  </div>

</section>

@endsection

@extends('layouts.app')

@section('title', 'Seguridad y acceso | SWAFI')
@section('page_title', 'Seguridad y acceso')
@section('page_subtitle', 'Gestión funcional de usuarios, roles, permisos y bitácora')
@section('breadcrumb', 'Seguridad y acceso')

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .sec-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .sec-tabs a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 9px 14px;
        border: 1px solid #d7e4f4;
        border-radius: 13px;
        background: #ffffff;
        color: #174f9a;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
    }

    .sec-tabs a.active {
        background: #154f9b;
        color: #ffffff;
        border-color: #154f9b;
    }

    .sec-grid {
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        gap: 18px;
        align-items: start;
    }

    .sec-form-grid,
    .sec-filter-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .sec-field-wide {
        grid-column: 1 / -1;
    }

    .sec-form-grid label,
    .sec-filter-grid label {
        display: block;
    }

    .sec-form-grid span,
    .sec-filter-grid span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .sec-form-grid input,
    .sec-form-grid select,
    .sec-filter-grid input,
    .sec-filter-grid select,
    .sec-form-grid textarea {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .sec-form-grid textarea {
        min-height: 80px;
        resize: vertical;
    }

    .sec-message {
        margin-bottom: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 700;
    }

    .sec-message-success {
        background: #e8f7ea;
        color: #1f6b2a;
        border: 1px solid #b9e5bf;
    }

    .sec-message-error {
        background: #fdeaea;
        color: #8a1f1f;
        border: 1px solid #f2baba;
    }

    .sec-message ul {
        margin: 6px 0 0 18px;
    }

    .sec-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .sec-kpi {
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .sec-kpi strong {
        display: block;
        color: #12345a;
        font-size: 18px;
        font-weight: 900;
    }

    .sec-kpi span {
        color: #64748b;
        font-size: 12px;
    }

    .sec-help {
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 13px;
        background: #eef6ff;
        color: #385b82;
        font-size: 12px;
        line-height: 1.4;
    }

    .sec-check-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 6px;
    }

    .sec-check {
        display: flex !important;
        align-items: flex-start;
        gap: 8px;
        padding: 8px;
        border: 1px solid #e1eaf6;
        border-radius: 12px;
        background: #ffffff;
        color: #16304d;
        font-size: 12px;
        font-weight: 800;
    }

    .sec-check input {
        width: auto !important;
        min-height: auto !important;
        margin-top: 2px;
    }

    .sec-filter {
        padding: 14px;
        border: 1px solid #e1eaf6;
        border-radius: 18px;
        background: #f8fbff;
        margin-bottom: 16px;
    }

    .sec-table-scroll {
        width: 100%;
        overflow-x: auto;
    }

    .sec-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .sec-status-ok {
        background: #e8f7ea;
        color: #1f6b2a;
    }

    .sec-status-warn {
        background: #fff4d6;
        color: #8a5a00;
    }

    .sec-status-danger {
        background: #fdeaea;
        color: #b42318;
    }

    .sec-policy-box {
        grid-column: 1 / -1;
        padding: 10px 12px;
        border: 1px solid #d9e6f7;
        border-radius: 13px;
        background: #f8fbff;
        color: #36557a;
        font-size: 12px;
        line-height: 1.45;
    }


    .sec-system-note {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 10px 12px;
        border: 1px solid #cfe0f5;
        border-radius: 13px;
        background: #f1f7ff;
        color: #31577f;
        font-size: 12px;
        line-height: 1.45;
    }

    .sec-permission-groups {
        display: grid;
        gap: 10px;
        margin-top: 8px;
    }

    .sec-permission-group {
        border: 1px solid #dce7f4;
        border-radius: 13px;
        background: #fbfdff;
        overflow: hidden;
    }

    .sec-permission-group > summary {
        cursor: pointer;
        padding: 10px 12px;
        color: #174f9a;
        font-size: 12px;
        font-weight: 900;
        list-style-position: inside;
    }

    .sec-permission-group .sec-check-grid {
        padding: 0 10px 10px;
    }

    .sec-action-muted {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        color: #7a8798;
        font-size: 11px;
        font-weight: 800;
    }

    .sec-code {
        display: inline-block;
        max-width: 100%;
        padding: 3px 7px;
        border-radius: 8px;
        background: #eef4fb;
        color: #173f70;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 11px;
        overflow-wrap: anywhere;
    }

    .sec-inline-form {
        display: inline;
    }

    .sec-inline-form button {
        border: 0;
        background: none;
        font-weight: 800;
        cursor: pointer;
        padding: 0;
    }

    .sec-inline-form button.danger {
        color: #b42318;
    }

    .sec-inline-form button.success {
        color: #176b36;
    }



    .sec-audit-detail {
        margin-top: 20px;
        border: 1px solid #cfe0f5;
        box-shadow: 0 14px 34px rgba(18, 52, 90, 0.08);
    }

    .sec-audit-meta-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .sec-audit-meta {
        min-width: 0;
        padding: 10px 12px;
        border: 1px solid #e1eaf6;
        border-radius: 13px;
        background: #f8fbff;
    }

    .sec-audit-meta span {
        display: block;
        margin-bottom: 4px;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .sec-audit-meta strong {
        display: block;
        color: #173f70;
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .sec-audit-value {
        display: block;
        min-width: 180px;
        max-width: 420px;
        max-height: 140px;
        overflow: auto;
        padding: 8px 9px;
        border-radius: 10px;
        background: #f7f9fc;
        color: #243b58;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 11px;
        line-height: 1.45;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .sec-audit-snapshots {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    .sec-audit-snapshot {
        border: 1px solid #e1eaf6;
        border-radius: 13px;
        background: #ffffff;
        overflow: hidden;
    }

    .sec-audit-snapshot > summary {
        cursor: pointer;
        padding: 10px 12px;
        color: #174f9a;
        font-size: 12px;
        font-weight: 900;
    }

    .sec-audit-snapshot-list {
        display: grid;
        gap: 8px;
        padding: 0 12px 12px;
    }

    .sec-audit-snapshot-item {
        padding: 8px;
        border-radius: 10px;
        background: #f8fbff;
    }

    .sec-audit-snapshot-item strong {
        display: block;
        margin-bottom: 3px;
        color: #31577f;
        font-size: 11px;
    }

    .sec-export-note {
        margin-top: 10px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.4;
    }

    @media (max-width: 1100px) {
        .sec-grid,
        .sec-kpi-grid,
        .sec-audit-meta-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 760px) {
        .sec-form-grid,
        .sec-filter-grid,
        .sec-check-grid,
        .sec-audit-meta-grid,
        .sec-audit-snapshots {
            grid-template-columns: 1fr !important;
        }

        .sec-audit-value {
            min-width: 150px;
            max-width: 300px;
        }
    }
</style>
@endsection

@section('content')

@if (session('success'))
    <div class="sec-message sec-message-success">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="sec-message sec-message-error">
        <strong>Se encontraron errores:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="sec-kpi-grid">
    @if ($canManageSecurity)
        <div class="sec-kpi">
            <strong>{{ number_format((int) $kpis['usuarios_total']) }}</strong>
            <span>Usuarios</span>
        </div>

        <div class="sec-kpi">
            <strong>{{ number_format((int) $kpis['usuarios_activos']) }}</strong>
            <span>Usuarios activos</span>
        </div>

        <div class="sec-kpi">
            <strong>{{ number_format((int) ($kpis['usuarios_bloqueados'] ?? 0)) }}</strong>
            <span>Usuarios bloqueados</span>
        </div>

        <div class="sec-kpi">
            <strong>{{ number_format((int) $kpis['roles_activos']) }}</strong>
            <span>Roles activos</span>
        </div>

        <div class="sec-kpi">
            <strong>{{ number_format((int) $kpis['permisos_total']) }}</strong>
            <span>Permisos</span>
        </div>
    @endif

    @if ($canViewAudit)
        <div class="sec-kpi">
            <strong>{{ number_format((int) $kpis['eventos_bitacora']) }}</strong>
            <span>Eventos bitácora</span>
        </div>
    @endif
</div>

<div class="sec-tabs" aria-label="Secciones de Seguridad y acceso">
    @if ($canManageSecurity)
        <a class="{{ $tabActivo === 'usuarios' ? 'active' : '' }}" href="{{ route('seguridad', ['tab' => 'usuarios']) }}">
            Usuarios
        </a>

        <a class="{{ $tabActivo === 'roles' ? 'active' : '' }}" href="{{ route('seguridad', ['tab' => 'roles']) }}">
            Roles y permisos
        </a>
    @endif

    @if ($canViewAudit)
        <a class="{{ $tabActivo === 'bitacora' ? 'active' : '' }}" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">
            Bitácora
        </a>
    @endif
</div>

@if ($tabActivo === 'usuarios')
    @php
        $editandoUsuario = $usuarioEdit !== null;
        $estatusUsuario = old('estatus', $usuarioEdit->estatus ?? 'activo');
        $rolesSeleccionados = old('role_ids', $usuarioRoles);

        if (!is_array($rolesSeleccionados)) {
            $rolesSeleccionados = [];
        }
    @endphp

    <div data-swafi-query-workspace data-swafi-query-key="seguridad-usuarios">
    <section class="sec-grid" data-swafi-query-panel>
        <div class="card">
            <div class="section-title">
                <h2>{{ $editandoUsuario ? 'Editar usuario' : 'Alta de usuario' }}</h2>
                <span class="pill ok">Autenticación real</span>
            </div>

            <form method="POST" action="{{ route('seguridad.usuarios.store') }}">
                @csrf

                @if ($editandoUsuario)
                    <input type="hidden" name="id" value="{{ $usuarioEdit->id }}">
                @endif

                <div class="sec-form-grid">
                    <label>
                        <span>Usuario</span>
                        <input name="usuario" value="{{ old('usuario', $usuarioEdit->usuario ?? '') }}" required>
                    </label>

                    <label>
                        <span>Nombre completo</span>
                        <input name="name" value="{{ old('name', $usuarioEdit->name ?? '') }}" required>
                    </label>

                    <label>
                        <span>Correo electrónico</span>
                        <input type="email" name="email" value="{{ old('email', $usuarioEdit->email ?? '') }}" required>
                    </label>

                    <label>
                        <span>Contraseña {{ $editandoUsuario ? '(solo si se cambia)' : '' }}</span>
                        <input type="password" name="password" {{ $editandoUsuario ? '' : 'required' }}>
                    </label>

                    <label>
                        <span>Estatus</span>
                        <select name="estatus" required>
                            <option value="activo" {{ $estatusUsuario === 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ $estatusUsuario === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                            <option value="bloqueado" {{ $estatusUsuario === 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                        </select>
                    </label>

                    <div class="sec-policy-box">
                        <strong>Política de contraseña:</strong> mínimo 8 caracteres, al menos una mayúscula, una minúscula, un número y un carácter especial.
                        Si el usuario está bloqueado, el Administrador SWAFI debe capturar una nueva contraseña y cambiar el estatus a <strong>Activo</strong> para desbloquearlo.
                    </div>

                    <div class="sec-field-wide">
                        <span>Roles asignados</span>
                        <small>Selecciona al menos un rol activo. Los roles inactivos no pueden asignarse.</small>

                        <div class="sec-check-grid">
                            @foreach ($rolesAsignables as $rol)
                                @php
                                    $rolId = (string) $rol->id;
                                    $rolMarcado = in_array($rolId, array_map('strval', $rolesSeleccionados), true);
                                @endphp

                                <label class="sec-check">
                                    <input type="checkbox" name="role_ids[]" value="{{ $rol->id }}" {{ $rolMarcado ? 'checked' : '' }}>
                                    <span>
                                        {{ $rol->nombre }}<br>
                                        <small>{{ ((int) $rol->activo) === 1 ? 'Activo' : 'Inactivo' }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="action-group" style="margin-top:14px">
                    <button class="tab" type="submit">
                        {{ $editandoUsuario ? 'Actualizar usuario' : 'Guardar usuario' }}
                    </button>

                    <a class="tab" href="{{ route('seguridad', ['tab' => 'usuarios']) }}">
                        Limpiar
                    </a>
                </div>
            </form>

            <div class="sec-help">
                Cada cuenta debe conservar al menos un rol activo. Los cambios de contraseña, estatus o roles revocan las sesiones anteriores del usuario afectado.
                Para cambiar la contraseña de tu propia cuenta utiliza <strong>Perfil</strong>. SWAFI impide desactivar al usuario de la sesión actual y evita que el sistema quede sin un Administrador activo.
            </div>
        </div>

        <div class="card">
            <div class="section-title">
                <h2>Filtros de usuarios</h2>
                <span class="pill ok">Exportación CSV</span>
            </div>

            <form method="GET" action="{{ route('seguridad') }}" class="sec-filter" data-swafi-query-form>
                <input type="hidden" name="tab" value="usuarios">

                <div class="sec-filter-grid">
                    <label>
                        <span>Buscar</span>
                        <input name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Usuario, nombre o correo">
                    </label>

                    <label>
                        <span>Rol</span>
                        @php
                            $filtroRol = (string) ($filtros['rol_id'] ?? '');
                        @endphp

                        <select name="rol_id">
                            <option value="">Todos</option>
                            @foreach ($roles as $rol)
                                <option value="{{ $rol->id }}" {{ $filtroRol === (string) $rol->id ? 'selected' : '' }}>
                                    {{ $rol->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Estatus</span>
                        @php
                            $filtroEstatus = $filtros['estatus'] ?? '';
                        @endphp

                        <select name="estatus">
                            <option value="">Todos</option>
                            <option value="activo" {{ $filtroEstatus === 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ $filtroEstatus === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                            <option value="bloqueado" {{ $filtroEstatus === 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                        </select>
                    </label>

                    <label>
                        <span>Registros por página</span>
                        @php
                            $perPageSeleccionado = (string) ($filtros['per_page'] ?? 10);
                        @endphp

                        <select name="per_page">
                            @foreach ([10, 25, 50] as $size)
                                <option value="{{ $size }}" {{ $perPageSeleccionado === (string) $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Consultar</button>
                    <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                    <a class="tab" href="{{ route('seguridad', ['tab' => 'usuarios']) }}">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card table-card" style="margin-top:20px" data-swafi-query-results id="swafi-seguridad-usuarios-resultados">
        <div class="section-title">
            <h2>Usuarios registrados</h2>
            <span class="pill ok">Paginación real</span>
        </div>

        <div class="sec-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Roles</th>
                        <th>Último acceso</th>
                        <th>Seguridad</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($usuarios as $usuario)
                        <tr>
                            <td><strong>{{ $usuario->usuario }}</strong></td>
                            <td>{{ $usuario->name }}</td>
                            <td>{{ $usuario->email }}</td>
                            <td>{{ $usuario->roles }}</td>
                            <td>
                                {{ $usuario->ultimo_acceso ?: 'Sin acceso' }}<br>
                                <small>{{ $usuario->ultimo_ip ?: 'Sin IP' }}</small>
                            </td>
                            <td>
                                Intentos: {{ (int) ($usuario->intentos_fallidos ?? 0) }}<br>
                                <small>
                                    {{ $usuario->bloqueado_en ? 'Bloqueado: ' . $usuario->bloqueado_en : 'Sin bloqueo' }}
                                </small>
                            </td>

                            <td>
                                @php
                                    if ($usuario->estatus === 'activo') {
                                        $claseUsuario = 'sec-status-ok';
                                    } elseif ($usuario->estatus === 'bloqueado') {
                                        $claseUsuario = 'sec-status-danger';
                                    } else {
                                        $claseUsuario = 'sec-status-warn';
                                    }
                                @endphp

                                <span class="sec-status {{ $claseUsuario }}">
                                    {{ ucfirst($usuario->estatus) }}
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('seguridad', array_merge(request()->query(), ['tab' => 'usuarios', 'editar_usuario' => $usuario->id])) }}">
                                        Editar
                                    </a>

                                    @if ($usuario->estatus === 'activo')
                                        <form method="POST" action="{{ route('seguridad.usuarios.destroy', $usuario->id) }}" style="display:inline" data-confirm="¿Deseas desactivar este usuario? Sus sesiones activas serán revocadas.">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="estatus" value="inactivo">

                                            <button type="submit" style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0">
                                                Desactivar
                                            </button>
                                        </form>
                                    @elseif ($usuario->estatus === 'inactivo')
                                        <form method="POST" action="{{ route('seguridad.usuarios.activate', $usuario->id) }}" style="display:inline" data-confirm="¿Deseas activar este usuario?">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="estatus" value="activo">

                                            <button type="submit" style="border:0;background:none;color:#067647;font-weight:800;cursor:pointer;padding:0">
                                                Activar
                                            </button>
                                        </form>
                                    @else
                                        <span style="color:#667085;font-weight:700">Editar para desbloquear</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No existen usuarios con los criterios seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="table-summary">
                Mostrando {{ $usuarios->firstItem() ?? 0 }}–{{ $usuarios->lastItem() ?? 0 }}
                de {{ $usuarios->total() }} resultados
            </div>

            <div class="table-pagination">
                @if ($usuarios->onFirstPage())
                    <span class="page-link disabled">Anterior</span>
                @else
                    <a class="page-link" href="{{ $usuarios->previousPageUrl() }}">Anterior</a>
                @endif

                <span class="page-link active">{{ $usuarios->currentPage() }}</span>

                @if ($usuarios->hasMorePages())
                    <a class="page-link" href="{{ $usuarios->nextPageUrl() }}">Siguiente</a>
                @else
                    <span class="page-link disabled">Siguiente</span>
                @endif
            </div>

            <div class="table-page-size">
                <span>M04 usuarios funcional</span>
            </div>
        </div>
    </section>
    </div>
@endif

@if ($tabActivo === 'roles')
    @php
        $editandoRol = $rolEdit !== null;
        $editandoPermiso = $permisoEdit !== null;
        $rolEsSistema = $editandoRol && ((int) ($rolEdit->es_sistema ?? 0)) === 1;
        $rolEsAdministrador = $editandoRol && (($rolEdit->nombre ?? '') === 'Administrador SWAFI');
        $activoRol = (string) old('activo', $rolEdit ? (string) $rolEdit->activo : '1');
        $permisosSeleccionados = old('permission_ids', $rolPermisos);

        if (!is_array($permisosSeleccionados)) {
            $permisosSeleccionados = [];
        }

        $permisosSeleccionados = array_map('strval', $permisosSeleccionados);
    @endphp

    <section class="sec-grid">
        <div class="card">
            <div class="section-title">
                <h2>{{ $editandoRol ? 'Editar rol' : 'Alta de rol' }}</h2>
                <span class="pill ok">HU-091</span>
            </div>

            <form method="POST" action="{{ route('seguridad.roles.store') }}">
                @csrf

                @if ($editandoRol)
                    <input type="hidden" name="id" value="{{ $rolEdit->id }}">
                    <input type="hidden" name="activo" value="{{ (int) $rolEdit->activo === 1 ? '1' : '0' }}">
                @endif

                <div class="sec-form-grid">
                    <label>
                        <span>Nombre del rol</span>
                        <input
                            name="nombre"
                            value="{{ old('nombre', $rolEdit->nombre ?? '') }}"
                            minlength="3"
                            maxlength="50"
                            {{ $rolEsSistema ? 'readonly' : '' }}
                            required
                        >
                    </label>

                    @if (!$editandoRol)
                        <label>
                            <span>Estatus inicial</span>
                            <select name="activo" required>
                                <option value="1" {{ $activoRol === '1' ? 'selected' : '' }}>Activo</option>
                                <option value="0" {{ $activoRol === '0' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </label>
                    @else
                        <div class="sec-system-note">
                            <strong>Estatus:</strong>
                            <span>
                                {{ (int) $rolEdit->activo === 1 ? 'Activo' : 'Inactivo' }}.
                                Utiliza la acción del listado para cambiarlo con trazabilidad.
                            </span>
                        </div>
                    @endif

                    <label class="sec-field-wide">
                        <span>Descripción</span>
                        <textarea name="descripcion" minlength="10" maxlength="255" required>{{ old('descripcion', $rolEdit->descripcion ?? '') }}</textarea>
                    </label>

                    @if ($rolEsSistema)
                        <div class="sec-system-note sec-field-wide">
                            <strong>Rol base protegido.</strong>
                            <span>
                                Su nombre y estatus no pueden modificarse porque existen flujos y reglas de autorización que dependen de esa identidad.
                            </span>
                        </div>
                    @endif

                    <div class="sec-field-wide">
                        <span>Permisos activos asignados</span>

                        @if ($rolEsAdministrador)
                            <div class="sec-system-note">
                                <strong>Acceso integral.</strong>
                                <span>
                                    Administrador SWAFI recibe automáticamente todos los permisos activos. La matriz se sincroniza en el servidor.
                                </span>
                            </div>
                        @else
                            <div class="sec-permission-groups">
                                @foreach ($permissionsByModule as $moduloPermiso => $permisosModulo)
                                    @php
                                        $idsModulo = collect($permisosModulo)
                                            ->pluck('id')
                                            ->map(fn ($id) => (string) $id)
                                            ->all();
                                        $grupoTieneSeleccion = count(array_intersect($idsModulo, $permisosSeleccionados)) > 0;
                                    @endphp

                                    <details class="sec-permission-group" {{ $grupoTieneSeleccion ? 'open' : '' }}>
                                        <summary>
                                            {{ strtoupper($moduloPermiso) }} · {{ $permisosModulo->count() }} permiso(s)
                                        </summary>

                                        <div class="sec-check-grid">
                                            @foreach ($permisosModulo as $permission)
                                                @php
                                                    $permissionId = (string) $permission->id;
                                                    $permissionMarcado = in_array($permissionId, $permisosSeleccionados, true);
                                                @endphp

                                                <label class="sec-check">
                                                    <input
                                                        type="checkbox"
                                                        name="permission_ids[]"
                                                        value="{{ $permission->id }}"
                                                        {{ $permissionMarcado ? 'checked' : '' }}
                                                    >
                                                    <span>
                                                        <span class="sec-code">{{ $permission->clave }}</span><br>
                                                        <small>{{ $permission->descripcion }}</small>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="action-group" style="margin-top:14px">
                    <button class="tab" type="submit">
                        {{ $editandoRol ? 'Actualizar rol y permisos' : 'Guardar rol' }}
                    </button>

                    <a class="tab" href="{{ route('seguridad', ['tab' => 'roles']) }}">
                        Limpiar
                    </a>
                </div>
            </form>

            <div class="sec-help">
                Los roles activos requieren al menos un permiso activo. Los roles base conservan su identidad y los cambios de permisos se reflejan en la siguiente solicitud autenticada.
            </div>
        </div>

        <div class="card">
            <div class="section-title">
                <h2>{{ $editandoPermiso ? 'Editar permiso' : 'Alta de permiso' }}</h2>
                <span class="pill ok">HU-092</span>
            </div>

            <form method="POST" action="{{ route('seguridad.permisos.store') }}">
                @csrf

                @if ($editandoPermiso)
                    <input type="hidden" name="id" value="{{ $permisoEdit->id }}">
                @endif

                <div class="sec-form-grid">
                    <label class="sec-field-wide">
                        <span>Clave técnica del permiso</span>
                        <input
                            name="clave"
                            value="{{ old('clave', $permisoEdit->clave ?? '') }}"
                            placeholder="ej. reportes.exportar"
                            minlength="5"
                            maxlength="80"
                            pattern="[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+"
                            {{ $editandoPermiso ? 'readonly' : '' }}
                            required
                        >
                    </label>

                    <label class="sec-field-wide">
                        <span>Descripción</span>
                        <textarea name="descripcion" minlength="10" maxlength="255" required>{{ old('descripcion', $permisoEdit->descripcion ?? '') }}</textarea>
                    </label>

                    @if ($editandoPermiso)
                        <div class="sec-system-note sec-field-wide">
                            <strong>Clave inmutable.</strong>
                            <span>
                                La clave técnica no puede renombrarse después de su creación porque puede estar vinculada con rutas, middleware y pruebas.
                            </span>
                        </div>
                    @endif
                </div>

                <div class="action-group" style="margin-top:14px">
                    <button class="tab" type="submit">
                        {{ $editandoPermiso ? 'Actualizar descripción' : 'Guardar permiso' }}
                    </button>

                    <a class="tab" href="{{ route('seguridad', ['tab' => 'roles']) }}">
                        Limpiar
                    </a>
                </div>
            </form>

            <div class="sec-help">
                Los permisos nuevos se crean activos y se incorporan automáticamente al rol Administrador SWAFI. Para desactivar un permiso personalizado primero debe retirarse de los demás roles.
            </div>
        </div>
    </section>

    <section class="card table-card" style="margin-top:20px">
        <div class="section-title">
            <h2>Roles registrados</h2>
            <div class="tabs">
                <a class="tab" href="{{ route('seguridad', ['tab' => 'roles', 'export' => 'csv']) }}">Exportar CSV</a>
            </div>
        </div>

        <div class="sec-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Rol</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Permisos activos</th>
                        <th>Usuarios</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($roles as $rol)
                        @php
                            $rolSistema = ((int) $rol->es_sistema) === 1;
                            $rolActivo = ((int) $rol->activo) === 1;
                            $tieneUsuarios = ((int) $rol->total_usuarios) > 0;
                        @endphp

                        <tr>
                            <td><strong>{{ $rol->nombre }}</strong></td>
                            <td>
                                <span class="sec-status {{ $rolSistema ? 'sec-status-ok' : 'sec-status-warn' }}">
                                    {{ $rolSistema ? 'Base' : 'Personalizado' }}
                                </span>
                            </td>
                            <td>{{ $rol->descripcion ?: 'Sin descripción' }}</td>
                            <td>
                                <strong>{{ $rol->total_permisos }}</strong><br>
                                <small>{{ $rol->permisos }}</small>
                            </td>
                            <td>
                                <strong>{{ $rol->total_usuarios }}</strong> asignado(s)<br>
                                <small>{{ $rol->usuarios_activos }} activo(s)</small>
                            </td>
                            <td>
                                <span class="sec-status {{ $rolActivo ? 'sec-status-ok' : 'sec-status-warn' }}">
                                    {{ $rolActivo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('seguridad', ['tab' => 'roles', 'editar_rol' => $rol->id]) }}">
                                        Editar
                                    </a>

                                    @if ($rolSistema)
                                        <span class="sec-action-muted">Protegido</span>
                                    @elseif ($rolActivo && $tieneUsuarios)
                                        <span class="sec-action-muted">Reasigna usuarios</span>
                                    @elseif ($rolActivo)
                                        <form
                                            class="sec-inline-form"
                                            method="POST"
                                            action="{{ route('seguridad.roles.destroy', $rol->id) }}"
                                            data-confirm="¿Deseas desactivar este rol personalizado?"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="estatus" value="inactivo">
                                            <input type="hidden" name="motivo" value="Desactivación administrativa del rol desde Seguridad y acceso.">
                                            <button class="danger" type="submit">Desactivar</button>
                                        </form>
                                    @else
                                        <form
                                            class="sec-inline-form"
                                            method="POST"
                                            action="{{ route('seguridad.roles.activate', $rol->id) }}"
                                            data-confirm="¿Deseas activar este rol personalizado?"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="estatus" value="activo">
                                            <input type="hidden" name="motivo" value="Reactivación administrativa del rol desde Seguridad y acceso.">
                                            <button class="success" type="submit">Activar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No existen roles registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card table-card" style="margin-top:20px">
        <div class="section-title">
            <h2>Permisos registrados</h2>
            <span class="pill ok">Catálogo técnico protegido</span>
        </div>

        <div class="sec-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Roles relacionados</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($permissions as $permission)
                        @php
                            $permisoSistema = ((int) $permission->es_sistema) === 1;
                            $permisoActivo = ((int) $permission->activo) === 1;
                            $asignadoOtrosRoles = ((int) $permission->total_roles_no_admin) > 0;
                        @endphp

                        <tr>
                            <td><span class="sec-code">{{ $permission->clave }}</span></td>
                            <td>
                                <span class="sec-status {{ $permisoSistema ? 'sec-status-ok' : 'sec-status-warn' }}">
                                    {{ $permisoSistema ? 'Sistema' : 'Personalizado' }}
                                </span>
                            </td>
                            <td>{{ $permission->descripcion ?: 'Sin descripción' }}</td>
                            <td>{{ $permission->roles_asignados }}</td>
                            <td>
                                <span class="sec-status {{ $permisoActivo ? 'sec-status-ok' : 'sec-status-warn' }}">
                                    {{ $permisoActivo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('seguridad', ['tab' => 'roles', 'editar_permiso' => $permission->id]) }}">
                                        Editar descripción
                                    </a>

                                    @if ($permisoSistema)
                                        <span class="sec-action-muted">Protegido</span>
                                    @elseif ($permisoActivo && $asignadoOtrosRoles)
                                        <span class="sec-action-muted">Retíralo de roles</span>
                                    @elseif ($permisoActivo)
                                        <form
                                            class="sec-inline-form"
                                            method="POST"
                                            action="{{ route('seguridad.permisos.destroy', $permission->id) }}"
                                            data-confirm="¿Deseas desactivar este permiso personalizado?"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="estatus" value="inactivo">
                                            <input type="hidden" name="motivo" value="Desactivación administrativa del permiso desde Seguridad y acceso.">
                                            <button class="danger" type="submit">Desactivar</button>
                                        </form>
                                    @else
                                        <form
                                            class="sec-inline-form"
                                            method="POST"
                                            action="{{ route('seguridad.permisos.activate', $permission->id) }}"
                                            data-confirm="¿Deseas activar este permiso personalizado?"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="estatus" value="activo">
                                            <input type="hidden" name="motivo" value="Reactivación administrativa del permiso desde Seguridad y acceso.">
                                            <button class="success" type="submit">Activar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No existen permisos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if ($tabActivo === 'bitacora')
    @php
        $bitacoraBaseParams = collect(request()->query())
            ->except(['export', 'detalle_bitacora', 'bitacora_page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $bitacoraBaseParams['tab'] = 'bitacora';
        $selectedAuditUser = (string) ($filtros['usuario_bitacora_id'] ?? '');
        $selectedAuditModule = (string) ($filtros['modulo'] ?? '');
        $selectedAuditAction = (string) ($filtros['accion'] ?? '');
    @endphp

    <div data-swafi-query-workspace data-swafi-query-key="seguridad-bitacora">
        <section class="card" data-swafi-query-panel>
            <div class="section-title">
                <h2>Filtros de bitácora</h2>
                <span class="pill ok">HU-093 · Auditoría</span>
            </div>

            <form method="GET" action="{{ route('seguridad') }}" class="sec-filter" data-swafi-query-form>
                <input type="hidden" name="tab" value="bitacora">

                <div class="sec-filter-grid">
                    <label>
                        <span>Buscar</span>
                        <input
                            name="buscar_bitacora"
                            value="{{ $filtros['buscar_bitacora'] ?? '' }}"
                            maxlength="160"
                            placeholder="Usuario, acción, módulo, tabla o registro"
                        >
                    </label>

                    <label>
                        <span>Persona usuaria</span>
                        <select name="usuario_bitacora_id">
                            <option value="">Todas las personas usuarias</option>
                            @foreach ($bitacoraOpciones['users'] as $auditUser)
                                <option
                                    value="{{ $auditUser->id }}"
                                    {{ $selectedAuditUser === (string) $auditUser->id ? 'selected' : '' }}
                                >
                                    {{ $auditUser->name }} · {{ $auditUser->usuario ?: $auditUser->email }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Número de activo</span>
                        <input
                            name="numero_activo"
                            value="{{ $filtros['numero_activo'] ?? '' }}"
                            maxlength="30"
                            placeholder="Ej. BIM-537028"
                        >
                    </label>

                    <label>
                        <span>Módulo</span>
                        <select name="modulo">
                            <option value="">Todos los módulos</option>
                            @foreach ($bitacoraOpciones['modules'] as $module)
                                <option value="{{ $module }}" {{ $selectedAuditModule === (string) $module ? 'selected' : '' }}>
                                    {{ $module }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Acción</span>
                        <select name="accion">
                            <option value="">Todas las acciones</option>
                            @foreach ($bitacoraOpciones['actions'] as $action)
                                <option value="{{ $action }}" {{ $selectedAuditAction === (string) $action ? 'selected' : '' }}>
                                    {{ $action }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Fecha desde</span>
                        <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                    </label>

                    <label>
                        <span>Fecha hasta</span>
                        <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                    </label>

                    <label>
                        <span>Registros por página</span>
                        @php
                            $perPageBitacora = (string) ($filtros['per_page'] ?? 10);
                        @endphp

                        <select name="per_page">
                            @foreach ([10, 25, 50] as $size)
                                <option value="{{ $size }}" {{ $perPageBitacora === (string) $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Consultar</button>
                    <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                    <button class="tab" type="submit" name="export" value="xlsx">Exportar Excel</button>
                    <button class="tab" type="submit" name="export" value="pdf">Exportar PDF</button>
                    <a class="tab" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">Limpiar filtros</a>
                </div>

                <p class="sec-export-note">
                    Las exportaciones respetan los filtros y admiten hasta
                    {{ number_format((int) $bitacoraExportLimit) }} eventos por archivo. Los valores sensibles
                    como contraseñas, tokens y secretos se excluyen del detalle y de las exportaciones.
                </p>
            </form>
        </section>

        @if ($bitacoraDetalle)
            @php
                $detalleEvento = $bitacoraDetalle['event'];
            @endphp

            <section
                class="card sec-audit-detail"
                id="swafi-seguridad-bitacora-detalle"
                aria-labelledby="swafi-audit-detail-title"
            >
                <div class="section-title">
                    <div>
                        <h2 id="swafi-audit-detail-title">Detalle del evento #{{ $detalleEvento->id }}</h2>
                        <small>Valores anteriores y posteriores protegidos contra exposición de secretos.</small>
                    </div>
                    <a class="tab" href="{{ route('seguridad', $bitacoraBaseParams) }}">Cerrar detalle</a>
                </div>

                <div class="sec-audit-meta-grid">
                    <div class="sec-audit-meta">
                        <span>Fecha y hora</span>
                        <strong>{{ $detalleEvento->fecha_evento }}</strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Persona usuaria</span>
                        <strong>
                            {{ $detalleEvento->usuario_nombre ?: 'Sistema' }}
                            @if ($detalleEvento->usuario || $detalleEvento->usuario_email)
                                · {{ $detalleEvento->usuario ?: $detalleEvento->usuario_email }}
                            @endif
                        </strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Módulo y acción</span>
                        <strong>{{ $detalleEvento->modulo }} · {{ $detalleEvento->accion }}</strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Origen</span>
                        <strong>{{ $detalleEvento->ip ?: 'IP no registrada' }}</strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Tabla</span>
                        <strong>{{ $detalleEvento->tabla_afectada ?: '—' }}</strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Registro</span>
                        <strong>{{ $detalleEvento->registro_clave ?: '—' }}</strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Activo</span>
                        <strong>{{ $detalleEvento->numero_activo ?: '—' }}</strong>
                    </div>
                    <div class="sec-audit-meta">
                        <span>Referencia</span>
                        <strong>AUD-{{ str_pad((string) $detalleEvento->id, 8, '0', STR_PAD_LEFT) }}</strong>
                    </div>
                </div>

                <div class="section-title">
                    <h3>Cambios identificados</h3>
                    <span class="pill ok">{{ count($bitacoraDetalle['changes']) }} diferencia(s)</span>
                </div>

                <div class="sec-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Valor anterior</th>
                                <th>Valor posterior</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bitacoraDetalle['changes'] as $change)
                                <tr>
                                    <td>
                                        <strong>{{ $change['label'] }}</strong><br>
                                        <small>{{ $change['field'] }}</small>
                                    </td>
                                    <td><span class="sec-audit-value">{{ $change['before'] }}</span></td>
                                    <td><span class="sec-audit-value">{{ $change['after'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        El evento no contiene diferencias comparables o corresponde a una acción informativa.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="sec-audit-snapshots">
                    <details class="sec-audit-snapshot">
                        <summary>Ver estado anterior completo</summary>
                        <div class="sec-audit-snapshot-list">
                            @forelse ($bitacoraDetalle['before'] as $item)
                                <div class="sec-audit-snapshot-item">
                                    <strong>{{ $item['label'] }}</strong>
                                    <span class="sec-audit-value">{{ $item['value'] }}</span>
                                </div>
                            @empty
                                <div class="sec-audit-snapshot-item">No existe estado anterior registrado.</div>
                            @endforelse
                        </div>
                    </details>

                    <details class="sec-audit-snapshot">
                        <summary>Ver estado posterior completo</summary>
                        <div class="sec-audit-snapshot-list">
                            @forelse ($bitacoraDetalle['after'] as $item)
                                <div class="sec-audit-snapshot-item">
                                    <strong>{{ $item['label'] }}</strong>
                                    <span class="sec-audit-value">{{ $item['value'] }}</span>
                                </div>
                            @empty
                                <div class="sec-audit-snapshot-item">No existe estado posterior registrado.</div>
                            @endforelse
                        </div>
                    </details>
                </div>
            </section>
        @endif

        <section class="card table-card" style="margin-top:20px" data-swafi-query-results id="swafi-seguridad-bitacora-resultados">
            <div class="section-title">
                <h2>Bitácora de auditoría</h2>
                <span class="pill ok">{{ number_format($bitacora->total()) }} evento(s)</span>
            </div>

            <div class="sec-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>Registro</th>
                            <th>Activo</th>
                            <th>IP</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($bitacora as $evento)
                            @php
                                $detailParams = array_merge(
                                    $bitacoraBaseParams,
                                    [
                                        'detalle_bitacora' => $evento->id,
                                        'swafi_focus' => 'swafi-seguridad-bitacora-detalle',
                                    ]
                                );
                            @endphp
                            <tr>
                                <td>{{ $evento->fecha_evento }}</td>
                                <td>
                                    {{ $evento->usuario ?: ($evento->usuario_email ?: 'Sistema') }}<br>
                                    <small>{{ $evento->usuario_nombre ?: 'Sin nombre' }}</small>
                                </td>
                                <td>{{ $evento->modulo }}</td>
                                <td><strong>{{ $evento->accion }}</strong></td>
                                <td>{{ $evento->tabla_afectada ?: '—' }}</td>
                                <td>{{ $evento->registro_clave ?: '—' }}</td>
                                <td>{{ $evento->numero_activo ?: '—' }}</td>
                                <td>{{ $evento->ip ?: '—' }}</td>
                                <td>
                                    <a class="tab" href="{{ route('seguridad', $detailParams) }}">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No existen eventos de bitácora con los criterios seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <div class="table-summary">
                    Mostrando {{ $bitacora->firstItem() ?? 0 }}–{{ $bitacora->lastItem() ?? 0 }}
                    de {{ $bitacora->total() }} resultados
                </div>

                <div class="table-pagination">
                    @if ($bitacora->onFirstPage())
                        <span class="page-link disabled">Anterior</span>
                    @else
                        <a class="page-link" href="{{ $bitacora->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span class="page-link active">{{ $bitacora->currentPage() }}</span>

                    @if ($bitacora->hasMorePages())
                        <a class="page-link" href="{{ $bitacora->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="page-link disabled">Siguiente</span>
                    @endif
                </div>

                <div class="table-page-size">
                    <span>HU-093 y HU-094</span>
                </div>
            </div>
        </section>
    </div>
@endif

@endsection

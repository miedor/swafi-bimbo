@extends('layouts.app')

@section('title', 'SWAFI | Acceso')

@section('content')
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <img src="{{ asset('swafi/img/logo-bimbo.jpg') }}" alt="Bimbo" class="login-logo">
            <div>
                <span class="eyebrow">Bimbo S.A. de C.V.</span>
                <h1>SWAFI</h1>
                <p>Sistema Web de Gestión de Facturas de Activo Fijo</p>
            </div>
        </div>

        <div class="login-copy">
            <h2>Acceso al sistema</h2>
            <p>Plataforma para resguardo, control, consulta y trazabilidad documental de activo fijo.</p>
        </div>

        <form class="login-form" action="{{ route('swafi.home') }}" method="GET">
            <label>
                Usuario
                <input type="text" placeholder="usuario.corporativo" value="admin.swafi">
            </label>
            <label>
                Contraseña
                <input type="password" placeholder="••••••••" value="12345678">
            </label>

            <div class="login-actions">
                <label class="remember-option">
                    <input type="checkbox" checked>
                    Recordar sesión
                </label>
                <a href="#">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Iniciar sesión</button>
        </form>

        <div class="login-foot">
            <span>Versión de maqueta UI/UX para avance del proyecto terminal.</span>
            <span class="status-pill status-success">Ambiente demostrativo</span>
        </div>
    </div>
</div>
@endsection

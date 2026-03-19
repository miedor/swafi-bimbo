<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SWAFI')</title>
    <link rel="stylesheet" href="{{ asset('swafi/css/swafi.css') }}">
</head>
<body>
    @php
        $current = request()->route()->getName();
    @endphp
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <img src="{{ asset('swafi/img/logo-bimbo.jpg') }}" alt="Bimbo" class="brand-logo">
                <div>
                    <span class="brand-kicker">Bimbo S.A. de C.V.</span>
                    <h1>SWAFI</h1>
                    <p>Sistema Web de Gestión de Facturas de Activo Fijo</p>
                </div>
            </div>
            <nav class="nav">
                <a class="{{ $current === 'swafi.dashboard' ? 'active' : '' }}" href="{{ route('swafi.dashboard') }}">Dashboard</a>
                <a class="{{ $current === 'swafi.registro-individual' ? 'active' : '' }}" href="{{ route('swafi.registro-individual') }}">Registro individual</a>
                <a class="{{ $current === 'swafi.registro-masivo' ? 'active' : '' }}" href="{{ route('swafi.registro-masivo') }}">Registro masivo</a>
                <a class="{{ $current === 'swafi.valores' ? 'active' : '' }}" href="{{ route('swafi.valores') }}">Valores fiscales y financieros</a>
                <a class="{{ $current === 'swafi.ubicacion' ? 'active' : '' }}" href="{{ route('swafi.ubicacion') }}">Ubicación e inventario</a>
                <a class="{{ $current === 'swafi.busqueda' ? 'active' : '' }}" href="{{ route('swafi.busqueda') }}">Búsqueda avanzada</a>
                <a class="{{ $current === 'swafi.reportes' ? 'active' : '' }}" href="{{ route('swafi.reportes') }}">Reportes ad hoc</a>
                <a class="{{ $current === 'swafi.catalogos' ? 'active' : '' }}" href="{{ route('swafi.catalogos') }}">Catálogos base</a>
                <a class="{{ $current === 'swafi.seguridad' ? 'active' : '' }}" href="{{ route('swafi.seguridad') }}">Seguridad y acceso</a>
                <a class="{{ $current === 'swafi.expediente' ? 'active' : '' }}" href="{{ route('swafi.expediente') }}">Detalle de expediente</a>
            </nav>
            <div class="sidebar-footer">
                <a href="{{ route('login') }}">Cerrar sesión</a>
            </div>
        </aside>
        <main class="content">
            <header class="topbar">
                <div>
                    <div class="breadcrumb">SWAFI / @yield('section')</div>
                    <h2>@yield('page_title')</h2>
                </div>
                <div class="topbar-tools">
                    <input type="text" placeholder="Buscar expediente, activo o proveedor">
                    <span class="pill">Administrador</span>
                </div>
            </header>
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('swafi/js/swafi.js') }}"></script>
</body>
</html>

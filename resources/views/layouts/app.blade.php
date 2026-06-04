<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'SWAFI')</title>

  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}?v={{ filemtime(public_path('assets/swafi/css/swafi.css')) }}">

  @yield('page_styles')
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand-panel">
      <div class="brand-badge">
        <img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Bimbo">
      </div>
      <div>
        <span>Bimbo S.A. de C.V.</span>
        <strong>SWAFI</strong>
      </div>
    </div>

    <nav class="nav-group">
      <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
        <span class="nav-dot"></span>Dashboard
      </a>

      <div class="nav-module">▼ M01 Expedientes</div>
      <a class="nav-item {{ request()->routeIs('registro-individual') ? 'active' : '' }}" href="{{ route('registro-individual') }}">
        Registro individual
      </a>
      <a class="nav-item {{ request()->routeIs('registro-masivo') ? 'active' : '' }}" href="{{ route('registro-masivo') }}">
        Registro masivo
      </a>
      <a class="nav-item {{ request()->routeIs('expediente') ? 'active' : '' }}" href="{{ route('expediente') }}">
        Detalle de expediente
      </a>

      <div class="nav-module">▶ M02 Control activo</div>
      <a class="nav-item {{ request()->routeIs('valores') ? 'active' : '' }}" href="{{ route('valores') }}">
        Valores fiscales y financieros
      </a>
      <a class="nav-item {{ request()->routeIs('ubicacion') ? 'active' : '' }}" href="{{ route('ubicacion') }}">
        Ubicación e inventario
      </a>

      <div class="nav-module">▶ M03 Consultas</div>
      <a class="nav-item {{ request()->routeIs('busqueda') ? 'active' : '' }}" href="{{ route('busqueda') }}">
        Búsqueda avanzada
      </a>
      <a class="nav-item {{ request()->routeIs('reportes') ? 'active' : '' }}" href="{{ route('reportes') }}">
        Reportes ad hoc
      </a>

      <div class="nav-module">▶ M04 Administración</div>
      <a class="nav-item {{ request()->routeIs('catalogos') ? 'active' : '' }}" href="{{ route('catalogos') }}">
        Catálogos base
      </a>
      <a class="nav-item {{ request()->routeIs('seguridad') ? 'active' : '' }}" href="{{ route('seguridad') }}">
        Seguridad y acceso
      </a>

      <a class="nav-item" href="{{ route('login') }}">
        <span class="nav-dot"></span>Cerrar sesión
      </a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="title-wrap">
        <h1>@yield('page_title', 'SWAFI')</h1>
        <p>@yield('page_subtitle', 'Sistema Web de Gestión de Facturas de Activo Fijo')</p>
      </div>

      <div class="userbar userbar-compact">
        <div class="avatar">ED</div>
      </div>
    </div>

    <div class="breadcrumb">Inicio / @yield('breadcrumb', 'Dashboard')</div>

    @yield('content')
  </main>
</div>

<script src="{{ asset('assets/swafi/js/swafi.js') }}?v={{ filemtime(public_path('assets/swafi/js/swafi.js')) }}"></script>

@yield('page_scripts')
</body>
</html>

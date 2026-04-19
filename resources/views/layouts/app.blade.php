<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'SWAFI')</title>
  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand-panel">
      <div class="brand-badge"><img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Bimbo"></div>
      <div>
        <span>Bimbo S.A. de C.V.</span>
        <strong>SWAFI</strong>
      </div>
    </div>
    <nav class="nav-group">
      <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-dot"></span>Dashboard</a>
      <a class="nav-item {{ request()->routeIs('registro-individual') ? 'active' : '' }}" href="{{ route('registro-individual') }}"><span class="nav-dot"></span>Registro individual</a>
      <a class="nav-item {{ request()->routeIs('registro-masivo') ? 'active' : '' }}" href="{{ route('registro-masivo') }}"><span class="nav-dot"></span>Registro masivo</a>
      <a class="nav-item {{ request()->routeIs('valores') ? 'active' : '' }}" href="{{ route('valores') }}"><span class="nav-dot"></span>Valores fiscales y financieros</a>
      <a class="nav-item {{ request()->routeIs('ubicacion') ? 'active' : '' }}" href="{{ route('ubicacion') }}"><span class="nav-dot"></span>Ubicación e inventario</a>
      <a class="nav-item {{ request()->routeIs('busqueda') ? 'active' : '' }}" href="{{ route('busqueda') }}"><span class="nav-dot"></span>Búsqueda avanzada</a>
      <a class="nav-item {{ request()->routeIs('reportes') ? 'active' : '' }}" href="{{ route('reportes') }}"><span class="nav-dot"></span>Reportes ad hoc</a>
      <a class="nav-item {{ request()->routeIs('catalogos') ? 'active' : '' }}" href="{{ route('catalogos') }}"><span class="nav-dot"></span>Catálogos base</a>
      <a class="nav-item {{ request()->routeIs('seguridad') ? 'active' : '' }}" href="{{ route('seguridad') }}"><span class="nav-dot"></span>Seguridad y acceso</a>
      <a class="nav-item {{ request()->routeIs('expediente') ? 'active' : '' }}" href="{{ route('expediente') }}"><span class="nav-dot"></span>Detalle de expediente</a>
      <a class="nav-item" href="{{ route('login') }}"><span class="nav-dot"></span>Cerrar sesión</a>
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
<script src="{{ asset('assets/swafi/js/swafi.js') }}"></script>
</body>
</html>

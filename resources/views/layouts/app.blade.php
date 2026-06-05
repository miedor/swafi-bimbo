@php
  $swafiIcon = function (string $name, string $class = 'swafi-icon') {
    $icons = [
      'dashboard' => '<path d="M3 13h8V3H3v10Z"></path><path d="M13 21h8V11h-8v10Z"></path><path d="M13 3v6h8V3h-8Z"></path><path d="M3 21h8v-6H3v6Z"></path>',
      'folder' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"></path>',
      'file-plus' => '<path d="M14 3v5h5"></path><path d="M6 21h12a2 2 0 0 0 2-2V8l-5-5H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"></path><path d="M12 12v6"></path><path d="M9 15h6"></path>',
      'upload' => '<path d="M12 16V4"></path><path d="M7 9l5-5 5 5"></path><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>',
      'file-search' => '<path d="M14 3v5h5"></path><path d="M6 21h7.5"></path><path d="M20 13.5V8l-5-5H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2"></path><path d="M15.5 17.5a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z"></path><path d="m20.5 20.5 2 2"></path>',
      'coins' => '<path d="M12 6c0 1.66-3.13 3-7 3S-2 7.66-2 6"></path><path d="M5 9v8c0 1.66 3.13 3 7 3s7-1.34 7-3V9"></path><path d="M5 13c0 1.66 3.13 3 7 3s7-1.34 7-3"></path><path d="M5 9c0-1.66 3.13-3 7-3s7 1.34 7 3-3.13 3-7 3-7-1.34-7-3Z"></path>',
      'map-pin' => '<path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"></path><path d="M12 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"></path>',
      'search' => '<path d="M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"></path><path d="m16 16 5 5"></path>',
      'chart' => '<path d="M4 19V5"></path><path d="M4 19h17"></path><path d="M8 16v-5"></path><path d="M13 16V8"></path><path d="M18 16v-9"></path>',
      'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.08A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.08A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.08A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.15.37.38.7.68.96.3.25.68.4 1.08.4H21a2 2 0 1 1 0 4h-.08A1.7 1.7 0 0 0 19.4 15Z"></path>',
      'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="M9 12l2 2 4-5"></path>',
      'logout' => '<path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M21 3v18"></path>',
      'home' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path>',
      'user' => '<path d="M20 21a8 8 0 0 0-16 0"></path><path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>',
      'chevron' => '<path d="m8 10 4 4 4-4"></path>',
    ];

    $paths = $icons[$name] ?? $icons['folder'];

    return '<svg class="'.$class.'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
  };

  $pageIcon = match (true) {
    request()->routeIs('dashboard') => 'dashboard',
    request()->routeIs('registro-individual') => 'file-plus',
    request()->routeIs('registro-masivo') => 'upload',
    request()->routeIs('expediente') => 'file-search',
    request()->routeIs('valores') => 'coins',
    request()->routeIs('ubicacion') => 'map-pin',
    request()->routeIs('busqueda') => 'search',
    request()->routeIs('reportes') => 'chart',
    request()->routeIs('catalogos') => 'settings',
    request()->routeIs('seguridad') => 'shield',
    default => 'dashboard',
  };

  $m01Open = request()->routeIs('registro-individual') || request()->routeIs('registro-masivo') || request()->routeIs('expediente');
  $m02Open = request()->routeIs('valores') || request()->routeIs('ubicacion');
  $m03Open = request()->routeIs('busqueda') || request()->routeIs('reportes');
  $m04Open = request()->routeIs('catalogos') || request()->routeIs('seguridad');
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'SWAFI')</title>

  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}?v={{ filemtime(public_path('assets/swafi/css/swafi.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi-icons.css') }}?v={{ file_exists(public_path('assets/swafi/css/swafi-icons.css')) ? filemtime(public_path('assets/swafi/css/swafi-icons.css')) : time() }}">

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
        {!! $swafiIcon('dashboard', 'nav-icon') !!}
        <span>Dashboard</span>
      </a>

      <button
        type="button"
        class="nav-module-button {{ $m01Open ? 'is-open' : '' }}"
        data-nav-toggle="m01"
        aria-expanded="{{ $m01Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">
          {!! $swafiIcon('folder', 'nav-icon nav-icon-module') !!}
          <span>M01 Expedientes</span>
        </span>
        {!! $swafiIcon('chevron', 'nav-module-arrow-icon') !!}
      </button>

      <div class="nav-submenu {{ $m01Open ? 'is-open' : '' }}" data-nav-group="m01">
        <a class="nav-item {{ request()->routeIs('registro-individual') ? 'active' : '' }}" href="{{ route('registro-individual') }}">
          {!! $swafiIcon('file-plus', 'nav-icon') !!}
          <span>Registro individual</span>
        </a>
        <a class="nav-item {{ request()->routeIs('registro-masivo') ? 'active' : '' }}" href="{{ route('registro-masivo') }}">
          {!! $swafiIcon('upload', 'nav-icon') !!}
          <span>Registro masivo</span>
        </a>
        <a class="nav-item {{ request()->routeIs('expediente') ? 'active' : '' }}" href="{{ route('expediente') }}">
          {!! $swafiIcon('file-search', 'nav-icon') !!}
          <span>Detalle de expediente</span>
        </a>
      </div>

      <button
        type="button"
        class="nav-module-button {{ $m02Open ? 'is-open' : '' }}"
        data-nav-toggle="m02"
        aria-expanded="{{ $m02Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">
          {!! $swafiIcon('coins', 'nav-icon nav-icon-module') !!}
          <span>M02 Control activo</span>
        </span>
        {!! $swafiIcon('chevron', 'nav-module-arrow-icon') !!}
      </button>

      <div class="nav-submenu {{ $m02Open ? 'is-open' : '' }}" data-nav-group="m02">
        <a class="nav-item {{ request()->routeIs('valores') ? 'active' : '' }}" href="{{ route('valores') }}">
          {!! $swafiIcon('coins', 'nav-icon') !!}
          <span>Valores fiscales y financieros</span>
        </a>
        <a class="nav-item {{ request()->routeIs('ubicacion') ? 'active' : '' }}" href="{{ route('ubicacion') }}">
          {!! $swafiIcon('map-pin', 'nav-icon') !!}
          <span>Ubicación e inventario</span>
        </a>
      </div>

      <button
        type="button"
        class="nav-module-button {{ $m03Open ? 'is-open' : '' }}"
        data-nav-toggle="m03"
        aria-expanded="{{ $m03Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">
          {!! $swafiIcon('search', 'nav-icon nav-icon-module') !!}
          <span>M03 Consultas</span>
        </span>
        {!! $swafiIcon('chevron', 'nav-module-arrow-icon') !!}
      </button>

      <div class="nav-submenu {{ $m03Open ? 'is-open' : '' }}" data-nav-group="m03">
        <a class="nav-item {{ request()->routeIs('busqueda') ? 'active' : '' }}" href="{{ route('busqueda') }}">
          {!! $swafiIcon('search', 'nav-icon') !!}
          <span>Búsqueda avanzada</span>
        </a>
        <a class="nav-item {{ request()->routeIs('reportes') ? 'active' : '' }}" href="{{ route('reportes') }}">
          {!! $swafiIcon('chart', 'nav-icon') !!}
          <span>Reportes ad hoc</span>
        </a>
      </div>

      <button
        type="button"
        class="nav-module-button {{ $m04Open ? 'is-open' : '' }}"
        data-nav-toggle="m04"
        aria-expanded="{{ $m04Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">
          {!! $swafiIcon('shield', 'nav-icon nav-icon-module') !!}
          <span>M04 Administración</span>
        </span>
        {!! $swafiIcon('chevron', 'nav-module-arrow-icon') !!}
      </button>

      <div class="nav-submenu {{ $m04Open ? 'is-open' : '' }}" data-nav-group="m04">
        <a class="nav-item {{ request()->routeIs('catalogos') ? 'active' : '' }}" href="{{ route('catalogos') }}">
          {!! $swafiIcon('settings', 'nav-icon') !!}
          <span>Catálogos base</span>
        </a>
        <a class="nav-item {{ request()->routeIs('seguridad') ? 'active' : '' }}" href="{{ route('seguridad') }}">
          {!! $swafiIcon('shield', 'nav-icon') !!}
          <span>Seguridad y acceso</span>
        </a>
      </div>

      <a class="nav-item nav-item-logout" href="{{ route('login') }}">
        {!! $swafiIcon('logout', 'nav-icon') !!}
        <span>Cerrar sesión</span>
      </a>
    </nav>
  </aside>

  <main class="main">
    <header class="swafi-page-header">
      <div class="topbar">
        <div class="title-wrap title-wrap-with-icon">
          <div class="page-title-icon">
            {!! $swafiIcon($pageIcon, 'page-icon') !!}
          </div>
          <div>
            <h1>@yield('page_title', 'SWAFI')</h1>
            <p>@yield('page_subtitle', 'Sistema Web de Gestión de Facturas de Activo Fijo')</p>
          </div>
        </div>

        <div class="swafi-header-actions">
          <a class="global-dashboard-btn" href="{{ route('dashboard') }}">
            {!! $swafiIcon('home', 'button-icon') !!}
            <span>Dashboard</span>
          </a>

          <div class="userbar userbar-compact">
            <div class="avatar avatar-with-icon">
              {!! $swafiIcon('user', 'avatar-icon') !!}
            </div>
          </div>
        </div>
      </div>

      <div class="breadcrumb breadcrumb-with-icon">
        {!! $swafiIcon('home', 'breadcrumb-icon') !!}
        <span>Inicio / @yield('breadcrumb', 'Dashboard')</span>
      </div>
    </header>

    @yield('content')
  </main>
</div>

<script src="{{ asset('assets/swafi/js/swafi.js') }}?v={{ filemtime(public_path('assets/swafi/js/swafi.js')) }}"></script>

@yield('page_scripts')
</body>
</html>

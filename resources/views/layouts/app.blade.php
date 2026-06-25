@php
  $swafiIcon = function (string $name, string $class = 'swafi-icon') {
    $icons = [
      'dashboard' => '<path d="M3 13h8V3H3v10Z"></path><path d="M13 21h8V11h-8v10Z"></path><path d="M13 3v6h8V3h-8Z"></path><path d="M3 21h8v-6H3v6Z"></path>',
      'folder' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"></path>',
      'file-plus' => '<path d="M14 3v5h5"></path><path d="M6 21h12a2 2 0 0 0 2-2V8l-5-5H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"></path><path d="M12 12v6"></path><path d="M9 15h6"></path>',
      'upload' => '<path d="M12 16V4"></path><path d="M7 9l5-5 5 5"></path><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>',
      'file-search' => '<path d="M14 3v5h5"></path><path d="M6 21h7.5"></path><path d="M20 13.5V8l-5-5H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2"></path><path d="M15.5 17.5a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z"></path><path d="m20.5 20.5 2 2"></path>',
      'coins' => '<path d="M12 8c4.42 0 8-1.57 8-3.5S16.42 1 12 1 4 2.57 4 4.5 7.58 8 12 8Z"></path><path d="M4 4.5v7C4 13.43 7.58 15 12 15s8-1.57 8-3.5v-7"></path><path d="M4 11.5v7C4 20.43 7.58 22 12 22s8-1.57 8-3.5v-7"></path>',
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

  <style>
    .app-shell {
      min-height: 100vh;
    }

    .main {
      position: relative !important;
      min-height: 100vh;
      overflow: visible !important;
    }

    .swafi-page-header {
      position: sticky !important;
      top: 0 !important;
      z-index: 999 !important;
      background: rgba(243, 246, 251, 0.97) !important;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      padding: 16px 0 12px !important;
      margin: -18px 0 16px !important;
      border-bottom: 1px solid rgba(214, 226, 241, 0.95);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    }

    .swafi-page-header .topbar {
      display: grid !important;
      grid-template-columns: minmax(320px, 1fr) auto auto !important;
      align-items: center !important;
      column-gap: 24px !important;
      margin-bottom: 8px !important;
    }

    .title-wrap-with-icon {
      min-width: 0;
    }

    .swafi-dashboard-slot {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      min-width: 190px;
      padding-right: 30px;
    }

    .swafi-user-slot {
      display: flex;
      align-items: center;
      justify-content: flex-end;
    }

    .global-dashboard-btn {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 8px !important;
      min-height: 40px !important;
      padding: 10px 18px !important;
      border-radius: 14px !important;
      border: 1px solid #d7e4f4 !important;
      background: #ffffff !important;
      color: #174f9a !important;
      font-size: 14px !important;
      font-weight: 800 !important;
      line-height: 1 !important;
      text-decoration: none !important;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05) !important;
      transition: all .15s ease !important;
      white-space: nowrap !important;
    }

    .global-dashboard-btn:hover {
      background: #edf4ff !important;
      transform: translateY(-1px);
    }

    .swafi-page-header .breadcrumb {
      margin-bottom: 0 !important;
      font-size: 12px !important;
    }

    /*
      Ajuste final menú lateral:
      - Nombres de módulos visibles completos.
      - Texto permitido en dos líneas.
      - Flecha desplegable siempre visible.
      - No altera rutas, controladores, modelos ni vistas internas.
    */

    .nav-group {
      overflow-x: visible !important;
    }

    .sidebar {
      overflow-x: hidden !important;
    }

    .nav-module-button {
      width: calc(100% - 26px) !important;
      min-height: 48px !important;
      box-sizing: border-box !important;

      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;

      gap: 7px !important;
      padding: 8px 8px 8px 11px !important;

      margin: 8px 30px 6px -4px !important;

      border: 1px solid rgba(255, 255, 255, .42) !important;
      border-radius: 13px !important;

      background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(242,247,255,.94)) !important;
      color: #172033 !important;

      font-family: inherit !important;
      font-size: 13px !important;
      font-weight: 850 !important;
      line-height: 1.12 !important;
      text-align: left !important;

      cursor: pointer !important;

      box-shadow:
        0 8px 16px rgba(2, 20, 48, .12),
        inset 0 1px 0 rgba(255,255,255,.78) !important;

      transition:
        transform .16s ease,
        background .16s ease,
        box-shadow .16s ease,
        border-color .16s ease !important;
    }

    .nav-module-button:hover {
      transform: translateY(-1px);
      background: linear-gradient(135deg, #ffffff, #edf4ff) !important;
      border-color: rgba(255, 255, 255, .58) !important;

      box-shadow:
        0 10px 20px rgba(2, 20, 48, .16),
        inset 0 1px 0 rgba(255,255,255,.88) !important;
    }

    .nav-module-button.is-open {
      background: linear-gradient(135deg, #ffffff, #e9f2ff) !important;
      border-color: rgba(255, 255, 255, .68) !important;

      box-shadow:
        0 10px 22px rgba(2, 20, 48, .18),
        inset 4px 0 0 #2b74d6 !important;
    }

    .nav-module-label {
      display: grid !important;
      grid-template-columns: 18px minmax(0, 1fr) !important;
      align-items: center !important;
      column-gap: 8px !important;

      min-width: 0 !important;
      flex: 1 1 auto !important;
      overflow: visible !important;
    }

    .nav-module-label span {
      display: block !important;
      min-width: 0 !important;
      max-width: 132px !important;

      white-space: normal !important;
      overflow: visible !important;
      text-overflow: clip !important;

      line-height: 1.12 !important;
      word-break: normal !important;
      overflow-wrap: normal !important;
    }

    .nav-module-button .nav-icon-module {
      width: 17px !important;
      height: 17px !important;
      min-width: 17px !important;
      flex: 0 0 17px !important;
      color: #6f819d !important;
    }

    .nav-module-button.is-open .nav-icon-module,
    .nav-module-button:hover .nav-icon-module {
      color: #174f9a !important;
    }

    .nav-module-arrow-icon {
      width: 24px !important;
      height: 24px !important;
      min-width: 24px !important;
      flex: 0 0 24px !important;

      padding: 4px !important;
      box-sizing: border-box !important;

      border-radius: 999px !important;
      color: #ffffff !important;
      background: #174f9a !important;

      box-shadow: 0 4px 10px rgba(23, 79, 154, .26) !important;

      transform: rotate(-90deg) !important;

      transition:
        transform .18s ease,
        background .18s ease,
        box-shadow .18s ease !important;
    }

    .nav-module-button.is-open .nav-module-arrow-icon {
      transform: rotate(0deg) !important;
      background: #0f3f7c !important;
      box-shadow: 0 5px 12px rgba(15, 63, 124, .34) !important;
    }

    .nav-submenu {
      width: calc(100% - 26px) !important;
      box-sizing: border-box !important;

      display: grid !important;
      gap: 6px !important;

      padding-left: 0 !important;
      margin: 6px 30px 10px -4px !important;

      overflow: hidden !important;
      max-height: 0 !important;
      opacity: 0 !important;
      visibility: hidden !important;

      transition:
        max-height .22s ease,
        opacity .18s ease,
        visibility .18s ease !important;
    }

    .nav-submenu.is-open {
      max-height: 320px !important;
      opacity: 1 !important;
      visibility: visible !important;
    }

    .nav-submenu .nav-item {
      padding-left: 16px !important;
      padding-right: 12px !important;
      border-radius: 12px !important;
    }

    .nav-item {
      display: flex !important;
      align-items: center !important;
      gap: 10px !important;
    }

    .nav-item span:last-child {
      min-width: 0;
    }

    .nav-item-logout {
      margin-top: 8px !important;
    }

    @media (max-width: 1200px) {
      .swafi-page-header .topbar {
        grid-template-columns: minmax(260px, 1fr) auto auto !important;
        column-gap: 16px !important;
      }

      .swafi-dashboard-slot {
        padding-right: 8px;
        min-width: 160px;
      }
    }

    @media (max-width: 900px) {
      .swafi-page-header {
        position: sticky !important;
        top: 0 !important;
        margin-top: 0 !important;
      }

      .swafi-page-header .topbar {
        grid-template-columns: 1fr !important;
        row-gap: 10px !important;
        align-items: stretch !important;
      }

      .swafi-dashboard-slot {
        justify-content: flex-start;
        padding-right: 0;
        min-width: 0;
      }

      .global-dashboard-btn {
        width: 100%;
      }

      .swafi-user-slot {
        display: none;
      }
    }
  </style>

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

      <a class="nav-item nav-item-logout" href="{{ route('logout') }}">
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

        <div class="swafi-dashboard-slot">
          <a class="global-dashboard-btn" href="{{ route('dashboard') }}">
            {!! $swafiIcon('home', 'button-icon') !!}
            <span>Dashboard</span>
          </a>
        </div>

        <div class="swafi-user-slot">
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

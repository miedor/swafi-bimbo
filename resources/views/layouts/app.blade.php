<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'SWAFI')</title>

  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}?v={{ filemtime(public_path('assets/swafi/css/swafi.css')) }}">

  <style>
    /* =========================================================
       SWAFI - Ajustes globales solicitados por sinodales
       1) Módulos laterales desplegables
       2) Encabezado fijo en todas las páginas
       3) Botón Dashboard visible en todas las páginas
       ========================================================= */

    .main {
      position: relative;
      padding-top: 18px !important;
    }

    .swafi-page-header {
      position: sticky;
      top: 0;
      z-index: 40;
      background: rgba(243, 246, 251, 0.96);
      backdrop-filter: blur(10px);
      padding: 14px 0 10px;
      margin: -18px 0 14px;
      border-bottom: 1px solid rgba(219, 229, 241, 0.85);
    }

    .swafi-page-header .topbar {
      margin-bottom: 8px !important;
    }

    .swafi-page-header .title-wrap h1 {
      font-size: 28px !important;
      line-height: 1.1 !important;
      margin: 0 !important;
    }

    .swafi-page-header .title-wrap p {
      font-size: 13px !important;
      margin: 4px 0 0 !important;
    }

    .swafi-page-header .breadcrumb {
      margin-bottom: 0 !important;
      font-size: 12px !important;
    }

    .swafi-header-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .global-dashboard-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 40px;
      padding: 10px 18px;
      border-radius: 14px;
      border: 1px solid #d7e4f4;
      background: #ffffff;
      color: #174f9a;
      font-size: 14px;
      font-weight: 800;
      line-height: 1;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
      transition: all .15s ease;
    }

    .global-dashboard-btn:hover {
      background: #edf4ff;
      transform: translateY(-1px);
    }

    .userbar-compact {
      gap: 10px;
    }

    .nav-module-button {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 12px;
      border: 1px solid rgba(255,255,255,.13);
      border-radius: 12px;
      background: rgba(255,255,255,.05);
      color: #eef4ff;
      font-family: inherit;
      font-size: 15px;
      font-weight: 800;
      line-height: 1.2;
      text-align: left;
      cursor: pointer;
      transition: background .15s ease, border-color .15s ease;
    }

    .nav-module-button:hover,
    .nav-module-button.is-open {
      background: rgba(255,255,255,.11);
      border-color: rgba(255,255,255,.20);
    }

    .nav-module-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }

    .nav-module-arrow {
      width: 16px;
      min-width: 16px;
      text-align: center;
      font-size: 11px;
      transform: rotate(-90deg);
      transition: transform .18s ease;
      opacity: .95;
    }

    .nav-module-button.is-open .nav-module-arrow {
      transform: rotate(0deg);
    }

    .nav-submenu {
      display: grid;
      gap: 6px;
      padding-left: 10px;
      margin: 2px 0 4px;
      overflow: hidden;
      max-height: 0;
      opacity: 0;
      transition: max-height .22s ease, opacity .16s ease;
    }

    .nav-submenu.is-open {
      max-height: 260px;
      opacity: 1;
    }

    .nav-submenu .nav-item {
      padding: 11px 12px !important;
      border-radius: 13px !important;
      font-size: 14px !important;
    }

    .nav-submenu .nav-item::before {
      content: "";
      width: 5px;
      height: 5px;
      border-radius: 999px;
      background: rgba(220, 233, 255, .85);
      flex: 0 0 auto;
    }

    .nav-item.nav-item-logout {
      margin-top: 6px;
    }

    @media (max-width: 900px) {
      .swafi-page-header {
        position: static;
        margin-top: 0;
      }

      .swafi-page-header .topbar {
        align-items: stretch !important;
      }

      .swafi-header-actions {
        justify-content: flex-start;
      }

      .global-dashboard-btn {
        width: 100%;
      }
    }
  </style>

  @yield('page_styles')
</head>
<body>
@php
  $m01Open = request()->routeIs('registro-individual') || request()->routeIs('registro-masivo') || request()->routeIs('expediente');
  $m02Open = request()->routeIs('valores') || request()->routeIs('ubicacion');
  $m03Open = request()->routeIs('busqueda') || request()->routeIs('reportes');
  $m04Open = request()->routeIs('catalogos') || request()->routeIs('seguridad');
@endphp

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

      <button
        type="button"
        class="nav-module-button {{ $m01Open ? 'is-open' : '' }}"
        data-nav-toggle="m01"
        aria-expanded="{{ $m01Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">M01 Expedientes</span>
        <span class="nav-module-arrow">▼</span>
      </button>

      <div class="nav-submenu {{ $m01Open ? 'is-open' : '' }}" data-nav-group="m01">
        <a class="nav-item {{ request()->routeIs('registro-individual') ? 'active' : '' }}" href="{{ route('registro-individual') }}">
          Registro individual
        </a>
        <a class="nav-item {{ request()->routeIs('registro-masivo') ? 'active' : '' }}" href="{{ route('registro-masivo') }}">
          Registro masivo
        </a>
        <a class="nav-item {{ request()->routeIs('expediente') ? 'active' : '' }}" href="{{ route('expediente') }}">
          Detalle de expediente
        </a>
      </div>

      <button
        type="button"
        class="nav-module-button {{ $m02Open ? 'is-open' : '' }}"
        data-nav-toggle="m02"
        aria-expanded="{{ $m02Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">M02 Control activo</span>
        <span class="nav-module-arrow">▼</span>
      </button>

      <div class="nav-submenu {{ $m02Open ? 'is-open' : '' }}" data-nav-group="m02">
        <a class="nav-item {{ request()->routeIs('valores') ? 'active' : '' }}" href="{{ route('valores') }}">
          Valores fiscales y financieros
        </a>
        <a class="nav-item {{ request()->routeIs('ubicacion') ? 'active' : '' }}" href="{{ route('ubicacion') }}">
          Ubicación e inventario
        </a>
      </div>

      <button
        type="button"
        class="nav-module-button {{ $m03Open ? 'is-open' : '' }}"
        data-nav-toggle="m03"
        aria-expanded="{{ $m03Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">M03 Consultas</span>
        <span class="nav-module-arrow">▼</span>
      </button>

      <div class="nav-submenu {{ $m03Open ? 'is-open' : '' }}" data-nav-group="m03">
        <a class="nav-item {{ request()->routeIs('busqueda') ? 'active' : '' }}" href="{{ route('busqueda') }}">
          Búsqueda avanzada
        </a>
        <a class="nav-item {{ request()->routeIs('reportes') ? 'active' : '' }}" href="{{ route('reportes') }}">
          Reportes ad hoc
        </a>
      </div>

      <button
        type="button"
        class="nav-module-button {{ $m04Open ? 'is-open' : '' }}"
        data-nav-toggle="m04"
        aria-expanded="{{ $m04Open ? 'true' : 'false' }}"
      >
        <span class="nav-module-label">M04 Administración</span>
        <span class="nav-module-arrow">▼</span>
      </button>

      <div class="nav-submenu {{ $m04Open ? 'is-open' : '' }}" data-nav-group="m04">
        <a class="nav-item {{ request()->routeIs('catalogos') ? 'active' : '' }}" href="{{ route('catalogos') }}">
          Catálogos base
        </a>
        <a class="nav-item {{ request()->routeIs('seguridad') ? 'active' : '' }}" href="{{ route('seguridad') }}">
          Seguridad y acceso
        </a>
      </div>

      <a class="nav-item nav-item-logout" href="{{ route('login') }}">
        <span class="nav-dot"></span>Cerrar sesión
      </a>
    </nav>
  </aside>

  <main class="main">
    <header class="swafi-page-header">
      <div class="topbar">
        <div class="title-wrap">
          <h1>@yield('page_title', 'SWAFI')</h1>
          <p>@yield('page_subtitle', 'Sistema Web de Gestión de Facturas de Activo Fijo')</p>
        </div>

        <div class="swafi-header-actions">
          <a class="global-dashboard-btn" href="{{ route('dashboard') }}">Dashboard</a>

          <div class="userbar userbar-compact">
            <div class="avatar">ED</div>
          </div>
        </div>
      </div>

      <div class="breadcrumb">Inicio / @yield('breadcrumb', 'Dashboard')</div>
    </header>

    @yield('content')
  </main>
</div>

<script src="{{ asset('assets/swafi/js/swafi.js') }}?v={{ filemtime(public_path('assets/swafi/js/swafi.js')) }}"></script>

@yield('page_scripts')
</body>
</html>

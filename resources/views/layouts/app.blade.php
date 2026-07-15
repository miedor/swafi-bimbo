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

  $swafiRoles = collect(session('swafi_roles', []))
    ->map(fn ($role) => trim((string) $role))
    ->filter()
    ->unique()
    ->values()
    ->all();

  $swafiPermissions = collect(session('swafi_permissions', []))
    ->map(fn ($permission) => trim((string) $permission))
    ->filter()
    ->unique()
    ->values()
    ->all();
  $swafiNombre = session('swafi_nombre', 'Usuario SWAFI');
  $swafiUsuario = session('swafi_usuario', 'usuario');
  $swafiAvatarPath = session('swafi_avatar_path');
  $swafiAvatarVersion = session('swafi_avatar_version', time());

  $swafiCan = function (string $permission) use ($swafiRoles, $swafiPermissions): bool {
    if (in_array('Administrador SWAFI', $swafiRoles, true)) {
      return true;
    }

    return in_array($permission, $swafiPermissions, true);
  };

  $showM01 = $swafiCan('expedientes.ver') || $swafiCan('expedientes.crear');
  $showM02 = $swafiCan('valores.ver') || $swafiCan('valores.administrar') || $swafiCan('ubicaciones.administrar');
  $showM03 = $swafiCan('expedientes.ver') || $swafiCan('reportes.exportar');
  $showM04 = $swafiCan('catalogos.administrar') || $swafiCan('seguridad.administrar') || $swafiCan('bitacora.ver');

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
    request()->routeIs('seguridad') && request('tab') === 'bitacora' => 'file-search',
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
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'SWAFI')</title>

  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}?v={{ filemtime(public_path('assets/swafi/css/swafi.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi-icons.css') }}?v={{ file_exists(public_path('assets/swafi/css/swafi-icons.css')) ? filemtime(public_path('assets/swafi/css/swafi-icons.css')) : time() }}">

  <style>
    html,
    body {
      width: 100%;
      max-width: 100%;
      /*
       * `clip` evita el desplazamiento horizontal sin crear un contenedor
       * de scroll. Esto es indispensable para que el encabezado sticky se
       * mantenga anclado al viewport durante el desplazamiento vertical.
       */
      overflow-x: clip;
    }

    .app-shell {
      width: 100%;
      max-width: 100%;
      min-height: 100vh;
      grid-template-columns: 230px minmax(0, 1fr) !important;
      overflow: visible;
      overflow-x: clip;
    }

    .main {
      position: relative !important;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      min-height: 100vh;
      overflow: visible !important;
      overflow-x: clip !important;
    }

    .main > * {
      min-width: 0;
      max-width: 100%;
    }

    .swafi-page-header {
      position: -webkit-sticky !important;
      position: sticky !important;
      top: 0 !important;
      z-index: 999 !important;
      width: 100%;
      align-self: start;
      isolation: isolate;
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
      grid-template-columns: minmax(0, 1fr) auto auto !important;
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
      flex: 0 0 auto;
      align-items: center;
      justify-content: flex-end;
      min-width: 54px;
      position: relative;
      z-index: 1001;
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

    .nav-item-logout-form {
      width: 100%;
      margin: 8px 0 0;
      padding: 0;
    }

    button.nav-item-logout {
      width: 100%;
      min-height: 44px;
      margin-top: 0 !important;
      padding: 10px 12px !important;
      appearance: none;
      -webkit-appearance: none;
      border: 1px solid rgba(255, 255, 255, .20) !important;
      border-radius: 12px !important;
      background: rgba(255, 255, 255, .13) !important;
      color: #ffffff !important;
      font: inherit;
      font-weight: 800 !important;
      line-height: 1.2;
      text-align: left;
      opacity: 1 !important;
      cursor: pointer;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .08);
      transition:
        background .16s ease,
        border-color .16s ease,
        transform .16s ease,
        box-shadow .16s ease;
    }

    button.nav-item-logout .nav-icon,
    button.nav-item-logout span {
      color: #ffffff !important;
      opacity: 1 !important;
    }

    button.nav-item-logout:hover {
      background: rgba(255, 255, 255, .23) !important;
      border-color: rgba(255, 255, 255, .34) !important;
      color: #ffffff !important;
      transform: translateY(-1px);
      box-shadow:
        0 8px 18px rgba(3, 22, 52, .16),
        inset 0 1px 0 rgba(255, 255, 255, .12);
    }

    button.nav-item-logout:focus-visible {
      outline: 3px solid rgba(255, 255, 255, .78);
      outline-offset: 2px;
      background: rgba(255, 255, 255, .23) !important;
      color: #ffffff !important;
    }

    .swafi-profile-logout-form {
      margin: 0;
    }

    button.swafi-profile-link {
      width: 100%;
      font-family: inherit;
      cursor: pointer;
      text-align: left;
    }

    .swafi-session-warning {
      position: fixed;
      right: 20px;
      bottom: 20px;
      z-index: 5000;
      width: min(390px, calc(100vw - 40px));
      display: none;
      padding: 16px 18px;
      border: 1px solid #f5c36a;
      border-radius: 16px;
      background: #fff8e8;
      color: #6f4300;
      box-shadow: 0 20px 46px rgba(15, 23, 42, .22);
      font-size: 13px;
      line-height: 1.45;
    }

    .swafi-session-warning.is-visible {
      display: block;
    }

    .swafi-session-warning strong {
      display: block;
      margin-bottom: 3px;
      color: #9a5b00;
      font-size: 14px;
    }

    .swafi-profile-menu {
      position: relative;
    }

    .swafi-profile-toggle {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 54px;
      height: 54px;
      border: 1px solid #d8e5f5;
      border-radius: 18px;
      background: #eef6ff;
      color: #174f9a;
      cursor: pointer;
      box-shadow: 0 10px 22px rgba(15, 23, 42, .06);
      transition: all .15s ease;
      overflow: hidden;
    }

    .swafi-profile-toggle:hover {
      background: #e6f1ff;
      transform: translateY(-1px);
    }

    .swafi-profile-toggle img,
    .swafi-profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .swafi-profile-dropdown {
      position: absolute;
      right: 0;
      top: calc(100% + 10px);
      z-index: 1500;
      width: 292px;
      display: none;
      padding: 14px;
      border: 1px solid #dbe7f6;
      border-radius: 20px;
      background: #ffffff;
      box-shadow: 0 24px 48px rgba(15, 23, 42, .18);
    }

    .swafi-profile-menu.is-open .swafi-profile-dropdown {
      display: block;
    }

    .swafi-profile-head {
      display: grid;
      grid-template-columns: 50px 1fr;
      gap: 11px;
      align-items: center;
      padding-bottom: 12px;
      border-bottom: 1px solid #e6edf7;
    }

    .swafi-profile-avatar {
      width: 50px;
      height: 50px;
      display: grid;
      place-items: center;
      overflow: hidden;
      border-radius: 16px;
      background: #eef6ff;
      color: #174f9a;
    }

    .swafi-profile-name {
      color: #12345c;
      font-size: 14px;
      font-weight: 950;
      line-height: 1.15;
    }

    .swafi-profile-user {
      margin-top: 3px;
      color: #64748b;
      font-size: 12px;
      font-weight: 800;
      word-break: break-word;
    }

    .swafi-profile-links {
      display: grid;
      gap: 8px;
      margin-top: 12px;
    }

    .swafi-profile-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      min-height: 38px;
      padding: 9px 11px;
      border: 1px solid #dbe7f6;
      border-radius: 13px;
      background: #f8fbff;
      color: #174f9a;
      font-size: 13px;
      font-weight: 900;
      text-decoration: none;
    }

    .swafi-profile-link:hover {
      background: #eef5ff;
    }

    .swafi-session-note {
      margin-top: 10px;
      color: #64748b;
      font-size: 11px;
      font-weight: 750;
      line-height: 1.35;
    }

    @media (max-width: 1200px) {
      .swafi-page-header .topbar {
        grid-template-columns: minmax(0, 1fr) auto auto !important;
        column-gap: 16px !important;
      }

      .swafi-dashboard-slot {
        padding-right: 8px;
        min-width: 160px;
      }
    }

    @media (max-width: 900px) {
      .app-shell {
        grid-template-columns: minmax(0, 1fr) !important;
      }

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

<body
  data-swafi-protected="true"
  data-swafi-login-url="{{ route('login') }}"
  data-swafi-logout-url="{{ route('logout') }}"
  data-swafi-heartbeat-url="{{ route('session.heartbeat') }}"
  data-swafi-inactivity-ms="{{ (int) config('session.swafi_inactivity_seconds', 600) * 1000 }}"
  data-swafi-warning-ms="{{ (int) config('session.swafi_warning_seconds', 60) * 1000 }}"
  data-swafi-heartbeat-ms="{{ (int) config('session.swafi_heartbeat_seconds', 60) * 1000 }}"
>
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
      @if ($swafiCan('dashboard.ver'))
        <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
          {!! $swafiIcon('dashboard', 'nav-icon') !!}
          <span>Dashboard</span>
        </a>
      @endif

      @if ($showM01)
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
          @if ($swafiCan('expedientes.crear'))
            <a class="nav-item {{ request()->routeIs('registro-individual') ? 'active' : '' }}" href="{{ route('registro-individual') }}">
              {!! $swafiIcon('file-plus', 'nav-icon') !!}
              <span>Registro individual</span>
            </a>

            <a class="nav-item {{ request()->routeIs('registro-masivo') ? 'active' : '' }}" href="{{ route('registro-masivo') }}">
              {!! $swafiIcon('upload', 'nav-icon') !!}
              <span>Registro masivo</span>
            </a>
          @endif

          @if ($swafiCan('expedientes.ver'))
            <a class="nav-item {{ request()->routeIs('expediente') ? 'active' : '' }}" href="{{ route('expediente') }}">
              {!! $swafiIcon('file-search', 'nav-icon') !!}
              <span>Detalle de expediente</span>
            </a>
          @endif
        </div>
      @endif

      @if ($showM02)
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
          @if ($swafiCan('valores.ver') || $swafiCan('valores.administrar'))
            <a class="nav-item {{ request()->routeIs('valores') ? 'active' : '' }}" href="{{ route('valores') }}">
              {!! $swafiIcon('coins', 'nav-icon') !!}
              <span>Valores fiscales y financieros</span>
            </a>
          @endif

          @if ($swafiCan('ubicaciones.administrar'))
            <a class="nav-item {{ request()->routeIs('ubicacion') ? 'active' : '' }}" href="{{ route('ubicacion') }}">
              {!! $swafiIcon('map-pin', 'nav-icon') !!}
              <span>Ubicación e inventario</span>
            </a>
          @endif
        </div>
      @endif

      @if ($showM03)
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
          @if ($swafiCan('expedientes.ver'))
            <a class="nav-item {{ request()->routeIs('busqueda') ? 'active' : '' }}" href="{{ route('busqueda') }}">
              {!! $swafiIcon('search', 'nav-icon') !!}
              <span>Búsqueda avanzada</span>
            </a>
          @endif

          @if ($swafiCan('reportes.exportar'))
            <a class="nav-item {{ request()->routeIs('reportes') ? 'active' : '' }}" href="{{ route('reportes') }}">
              {!! $swafiIcon('chart', 'nav-icon') !!}
              <span>Reportes ad hoc</span>
            </a>
          @endif
        </div>
      @endif

      @if ($showM04)
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
          @if ($swafiCan('catalogos.administrar'))
            <a class="nav-item {{ request()->routeIs('catalogos') ? 'active' : '' }}" href="{{ route('catalogos') }}">
              {!! $swafiIcon('settings', 'nav-icon') !!}
              <span>Catálogos base</span>
            </a>
          @endif

          @if ($swafiCan('seguridad.administrar'))
            <a class="nav-item {{ request()->routeIs('seguridad') && request('tab', 'usuarios') !== 'bitacora' ? 'active' : '' }}" href="{{ route('seguridad', ['tab' => 'usuarios']) }}">
              {!! $swafiIcon('shield', 'nav-icon') !!}
              <span>Seguridad y acceso</span>
            </a>
          @endif

          @if ($swafiCan('bitacora.ver'))
            <a class="nav-item {{ request()->routeIs('seguridad') && request('tab') === 'bitacora' ? 'active' : '' }}" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">
              {!! $swafiIcon('file-search', 'nav-icon') !!}
              <span>Bitácora</span>
            </a>
          @endif
        </div>
      @endif

      <form class="nav-item-logout-form" action="{{ route('logout') }}" method="POST">
        @csrf
        <input type="hidden" name="motivo" value="manual">
        <button class="nav-item nav-item-logout" type="submit">
          {!! $swafiIcon('logout', 'nav-icon') !!}
          <span>Cerrar sesión</span>
        </button>
      </form>
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
          @if ($swafiCan('dashboard.ver'))
            <a class="global-dashboard-btn" href="{{ route('dashboard') }}">
              {!! $swafiIcon('home', 'button-icon') !!}
              <span>Dashboard</span>
            </a>
          @endif
        </div>

        <div class="swafi-user-slot">
          <div class="swafi-profile-menu" data-profile-menu>
            <button type="button" class="swafi-profile-toggle" data-profile-toggle aria-label="Perfil de usuario" aria-expanded="false">
              @if ($swafiAvatarPath)
                <img src="{{ route('perfil.avatar', ['v' => $swafiAvatarVersion]) }}" alt="Avatar de {{ $swafiNombre }}">
              @else
                {!! $swafiIcon('user', 'avatar-icon') !!}
              @endif
            </button>

            <div class="swafi-profile-dropdown" data-profile-dropdown>
              <div class="swafi-profile-head">
                <div class="swafi-profile-avatar">
                  @if ($swafiAvatarPath)
                    <img src="{{ route('perfil.avatar', ['v' => $swafiAvatarVersion]) }}" alt="Avatar de {{ $swafiNombre }}">
                  @else
                    {!! $swafiIcon('user', 'avatar-icon') !!}
                  @endif
                </div>

                <div>
                  <div class="swafi-profile-name">{{ $swafiNombre }}</div>
                  <div class="swafi-profile-user">{{ $swafiUsuario }}</div>
                </div>
              </div>

              <div class="swafi-profile-links">
                <a class="swafi-profile-link" href="{{ route('perfil') }}">
                  <span>Mi perfil y avatar</span>
                  <span>→</span>
                </a>

                <form class="swafi-profile-logout-form" action="{{ route('logout') }}" method="POST">
                  @csrf
                  <input type="hidden" name="motivo" value="manual">
                  <button class="swafi-profile-link" type="submit">
                    <span>Cerrar sesión</span>
                    <span>→</span>
                  </button>
                </form>
              </div>

              <div class="swafi-session-note">
                Por seguridad, SWAFI cierra la sesión después de 10 minutos sin actividad y al utilizar Atrás en el navegador.
              </div>
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

<div id="swafiSessionWarning" class="swafi-session-warning" role="status" aria-live="polite">
  <strong>La sesión está por finalizar</strong>
  <span id="swafiSessionWarningText">La sesión se cerrará por inactividad.</span>
</div>

<script src="{{ asset('assets/swafi/js/swafi.js') }}?v={{ filemtime(public_path('assets/swafi/js/swafi.js')) }}"></script>
<script src="{{ asset('assets/swafi/js/swafi-session.js') }}?v={{ filemtime(public_path('assets/swafi/js/swafi-session.js')) }}"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const profileMenu = document.querySelector('[data-profile-menu]');
    const profileToggle = document.querySelector('[data-profile-toggle]');

    if (profileMenu && profileToggle) {
      profileToggle.addEventListener('click', function (event) {
        event.stopPropagation();
        const isOpen = profileMenu.classList.toggle('is-open');
        profileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      document.addEventListener('click', function (event) {
        if (!profileMenu.contains(event.target)) {
          profileMenu.classList.remove('is-open');
          profileToggle.setAttribute('aria-expanded', 'false');
        }
      });
    }
  });
</script>

@yield('page_scripts')
</body>
</html>

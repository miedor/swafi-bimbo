<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SWAFI | Acceso</title>
  <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}">
</head>
<body class="login-body">
<div class="login-wrap">
  <section class="login-card">
    <div class="login-hero">
      <div class="login-brand-row">
        <div class="login-logo-box">
          <img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Grupo Bimbo" class="login-logo">
        </div>
        <div>
          <span class="brand-kicker">Bimbo S.A. de C.V.</span>
          <h1>SWAFI</h1>
          <p>Sistema Web de Gestión de Facturas de Activo Fijo</p>
        </div>
      </div>
    </div>
    <div class="login-panel">
      <div>
        <h2>Acceso al sistema</h2>
        <p>Plataforma para resguardo, control, consulta y trazabilidad documental de activo fijo. Esta maqueta muestra la navegación principal del sistema, con enfoque administrativo y corporativo para Bimbo.</p>
      </div>
      <div>
        <div class="form-grid">
          <label><span>Usuario</span><input type="text" value="admin.swafi"></label>
          <label><span>Contraseña</span><input type="password" value="12345678"></label>
        </div>
        <div class="login-actions-row">
          <label class="checkline"><input type="checkbox" checked> Recordar sesión</label>
          <a href="#">¿Olvidaste tu contraseña?</a>
        </div>
        <a class="btn btn-primary btn-block" href="{{ route('dashboard') }}">Iniciar sesión</a>
      </div>
    </div>
  </section>
</div>
</body>
</html>

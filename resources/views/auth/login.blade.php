<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWAFI | Acceso</title>
    <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}">
</head>
<body class="login-page-v1">
    <div class="login-shell-v1">
        <section class="login-card-v1">
            <header class="login-hero-v1">
                <div class="login-brand-v1">
                    <div class="login-brand-logo-v1">
                        <img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Grupo Bimbo">
                    </div>
                    <div class="login-brand-copy-v1">
                        <span class="login-brand-company-v1">BIMBO S.A. DE C.V.</span>
                        <h1>SWAFI</h1>
                        <p>Sistema Web de Gestión de Facturas de Activo Fijo</p>
                    </div>
                </div>
            </header>

            <div class="login-body-v1">
                <div class="login-intro-v1">
                    <h2>Acceso al sistema</h2>
                    <p>
                        Plataforma para resguardo, control, consulta y trazabilidad
                        documental de activo fijo.
                    </p>
                </div>

                <form class="login-form-v1" action="{{ url('/dashboard') }}" method="GET">
                    <div class="form-group-v1">
                        <label for="usuario">Usuario</label>
                        <input id="usuario" name="usuario" type="text" value="admin.swafi" autocomplete="username">
                    </div>

                    <div class="form-group-v1">
                        <label for="password">Contraseña</label>
                        <input id="password" name="password" type="password" value="12345678" autocomplete="current-password">
                    </div>

                    <div class="login-options-v1">
                        <label class="check-v1">
                            <input type="checkbox" checked>
                            <span>Recordar sesión</span>
                        </label>
                        <a href="#">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn-login-v1">Iniciar sesión</button>
                </form>
            </div>
        </section>
    </div>
</body>
</html>

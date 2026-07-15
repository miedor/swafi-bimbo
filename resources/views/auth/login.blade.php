@php
  $loginIcon = function (string $name, string $class = 'login-icon') {
    $icons = [
      'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="M9 12l2 2 4-5"></path>',
      'folder' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"></path>',
      'file' => '<path d="M14 3v5h5"></path><path d="M6 21h12a2 2 0 0 0 2-2V8l-5-5H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"></path>',
      'user' => '<path d="M20 21a8 8 0 0 0-16 0"></path><path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>',
      'lock' => '<path d="M7 11V8a5 5 0 0 1 10 0v3"></path><path d="M6 11h12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z"></path>',
      'login' => '<path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M21 3v18"></path>',
      'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>',
      'eye-off' => '<path d="m3 3 18 18"></path><path d="M10.6 5.2A9.8 9.8 0 0 1 12 5c6.5 0 10 7 10 7a17.8 17.8 0 0 1-2.1 3.2"></path><path d="M6.2 6.2C3.5 8.1 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 4.1-.9"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>',
      'chart' => '<path d="M4 19V5"></path><path d="M4 19h17"></path><path d="M8 16v-5"></path><path d="M13 16V8"></path><path d="M18 16v-9"></path>',
      'search' => '<path d="M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"></path><path d="m16 16 5 5"></path>',
      'map' => '<path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"></path><path d="M12 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"></path>',
      'database' => '<path d="M12 8c4.42 0 8-1.57 8-3.5S16.42 1 12 1 4 2.57 4 4.5 7.58 8 12 8Z"></path><path d="M4 4.5v7C4 13.43 7.58 15 12 15s8-1.57 8-3.5v-7"></path><path d="M4 11.5v7C4 20.43 7.58 22 12 22s8-1.57 8-3.5v-7"></path>',
    ];

    $paths = $icons[$name] ?? $icons['file'];

    return '<svg class="'.$class.'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
  };
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWAFI | Acceso</title>

    <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}?v={{ filemtime(public_path('assets/swafi/css/swafi.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi-icons.css') }}?v={{ file_exists(public_path('assets/swafi/css/swafi-icons.css')) ? filemtime(public_path('assets/swafi/css/swafi-icons.css')) : time() }}">

    <script>
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>

    @if(config('services.recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
    @endif

    <style>
        .login-alert-v1 {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            font-size: 14px;
            font-weight: 700;
        }

        .login-alert-v1 p {
            margin: 0;
        }

        .login-alert-v1 p + p {
            margin-top: 6px;
        }

        .password-field-wrap {
            position: relative;
        }

        .password-field-wrap input {
            padding-right: 52px !important;
        }

        .password-toggle-v1 {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #526f93;
            cursor: pointer;
            transition: background .16s ease, color .16s ease;
        }

        .password-toggle-v1:hover,
        .password-toggle-v1:focus-visible {
            background: #eef5ff;
            color: #174f9a;
            outline: none;
        }

        .password-toggle-icon-v1 {
            width: 20px;
            height: 20px;
        }

        .password-toggle-v1 .is-hidden {
            display: none;
        }

        .recaptcha-note-v1 {
            margin-top: 12px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
        }

        .recaptcha-note-v1 strong {
            color: #174f9a;
        }
    </style>
</head>

<body class="login-page-v1 swafi-login-executive">
    <div class="login-shell-v1">
        <section class="login-card-v1 login-card-executive">
            <header class="login-hero-v1 login-hero-executive">
                <div class="login-brand-v1">
                    <div class="login-brand-logo-v1 login-brand-logo-executive">
                        <img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Grupo Bimbo">
                    </div>

                    <div class="login-brand-copy-v1">
                        <span class="login-brand-company-v1">BIMBO S.A. DE C.V.</span>
                        <h1>SWAFI</h1>
                        <p>Sistema Web de Gestión de Facturas de Activo Fijo</p>
                    </div>
                </div>

                <div class="login-security-badge">
                    {!! $loginIcon('shield', 'login-badge-icon') !!}
                    <span>Acceso seguro</span>
                </div>
            </header>

            <div class="login-body-v1 login-body-executive">
                <div class="login-intro-v1 login-intro-executive">
                    <div class="login-title-row">
                        <div class="login-title-icon">
                            {!! $loginIcon('folder', 'login-title-svg') !!}
                        </div>
                        <div>
                            <h2>Acceso al sistema</h2>
                            <p>
                                Plataforma para resguardo, control, consulta y trazabilidad
                                documental de activo fijo.
                            </p>
                        </div>
                    </div>

                    <div class="login-benefit-grid">
                        <div class="login-benefit">
                            {!! $loginIcon('file', 'login-benefit-icon') !!}
                            <span>Expedientes PDF/XML</span>
                        </div>
                        <div class="login-benefit">
                            {!! $loginIcon('chart', 'login-benefit-icon') !!}
                            <span>Valores fiscales</span>
                        </div>
                        <div class="login-benefit">
                            {!! $loginIcon('map', 'login-benefit-icon') !!}
                            <span>Ubicación física</span>
                        </div>
                        <div class="login-benefit">
                            {!! $loginIcon('database', 'login-benefit-icon') !!}
                            <span>Trazabilidad</span>
                        </div>
                    </div>
                </div>

                <div>
                   @if (!empty($sessionNotice))
    <div class="login-alert-v1" style="background:#fff8e8;border-color:#f5c36a;color:#6f4300;">
        <p>{{ $sessionNotice }}</p>
    </div>
@endif

@if (session('status'))
    <div class="login-alert-v1" style="background:#e8f7ea;border-color:#b9e5bf;color:#1f6b2a;">
        <p>{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="login-alert-v1">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

                    <form id="loginForm" class="login-form-v1 login-form-executive" action="{{ route('login.post') }}" method="POST">
                        @csrf

                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                        <div class="form-group-v1 form-group-icon">
                            <label for="usuario">
                                {!! $loginIcon('user', 'field-label-icon') !!}
                                <span>Usuario</span>
                            </label>
                            <div class="input-with-icon">
                                {!! $loginIcon('user', 'input-icon') !!}
                                <input
                                    id="usuario"
                                    name="usuario"
                                    type="text"
                                    value="{{ old('usuario') }}"
                                    autocomplete="username"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-group-v1 form-group-icon">
                            <label for="password">
                                {!! $loginIcon('lock', 'field-label-icon') !!}
                                <span>Contraseña</span>
                            </label>
                            <div class="input-with-icon password-field-wrap">
                                {!! $loginIcon('lock', 'input-icon') !!}
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    id="togglePassword"
                                    class="password-toggle-v1"
                                    aria-label="Mostrar contraseña"
                                    aria-pressed="false"
                                    title="Mostrar contraseña"
                                >
                                    <span id="passwordEyeVisible">
                                        {!! $loginIcon('eye', 'password-toggle-icon-v1') !!}
                                    </span>
                                    <span id="passwordEyeHidden" class="is-hidden">
                                        {!! $loginIcon('eye-off', 'password-toggle-icon-v1') !!}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="login-options-v1">
                            <span style="color:#64748b;font-size:12px;font-weight:750;">
                                Sesión no persistente por seguridad
                            </span>
                            <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                        </div>

                        <button type="submit" class="btn-login-v1 btn-login-executive">
                            {!! $loginIcon('login', 'login-button-icon') !!}
                            <span>Iniciar sesión</span>
                        </button>

                        <p class="recaptcha-note-v1">
                            <strong>Protección activa:</strong> este acceso utiliza reCAPTCHA v3 para reducir intentos automatizados sin afectar la experiencia del usuario.
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');
            const eyeVisible = document.getElementById('passwordEyeVisible');
            const eyeHidden = document.getElementById('passwordEyeHidden');

            if (!passwordInput || !toggleButton) {
                return;
            }

            toggleButton.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';

                passwordInput.type = isPassword ? 'text' : 'password';
                toggleButton.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                toggleButton.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
                toggleButton.setAttribute('title', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');

                if (eyeVisible && eyeHidden) {
                    eyeVisible.classList.toggle('is-hidden', isPassword);
                    eyeHidden.classList.toggle('is-hidden', !isPassword);
                }

                passwordInput.focus();
                passwordInput.setSelectionRange(passwordInput.value.length, passwordInput.value.length);
            });
        });
    </script>

    @if(config('services.recaptcha.site_key'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const loginForm = document.getElementById('loginForm');
                const recaptchaInput = document.getElementById('g-recaptcha-response');

                if (!loginForm || !recaptchaInput || typeof grecaptcha === 'undefined') {
                    return;
                }

                loginForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                    grecaptcha.ready(function () {
                        grecaptcha
                            .execute('{{ config('services.recaptcha.site_key') }}', { action: 'login' })
                            .then(function (token) {
                                recaptchaInput.value = token;
                                loginForm.submit();
                            });
                    });
                });
            });
        </script>
    @endif
</body>
</html>

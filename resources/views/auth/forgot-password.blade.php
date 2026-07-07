@php
  $loginIcon = function ($name, $class = 'login-icon') {
    $icons = [
      'mail' => '<path d="M4 6h16v12H4z"/><path d="m22 7-10 6L2 7"/>',
      'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
      'arrow' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
      'back' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
      'folder' => '<path d="M3 7h5l2 2h11v10H3z"/>',
    ];

    $paths = $icons[$name] ?? $icons['mail'];

    return '<svg class="'.$class.'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
  };
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWAFI | Recuperar contraseña</title>

    <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi.css') }}?v={{ filemtime(public_path('assets/swafi/css/swafi.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/swafi/css/swafi-icons.css') }}?v={{ file_exists(public_path('assets/swafi/css/swafi-icons.css')) ? filemtime(public_path('assets/swafi/css/swafi-icons.css')) : time() }}">

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

        .login-alert-success-v1 {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #e8f7ea;
            border: 1px solid #b9e5bf;
            color: #1f6b2a;
            font-size: 14px;
            font-weight: 700;
        }

        .login-alert-v1 p,
        .login-alert-success-v1 p {
            margin: 0;
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
                    <span>Recuperación segura</span>
                </div>
            </header>

            <div class="login-body-v1 login-body-executive">
                <div class="login-intro-v1 login-intro-executive">
                    <div class="login-title-row">
                        <div class="login-title-icon">
                            {!! $loginIcon('folder', 'login-title-svg') !!}
                        </div>
                        <div>
                            <h2>Recuperar contraseña</h2>
                            <p>
                                Captura el correo asociado a tu usuario. Si existe una cuenta activa,
                                enviaremos un enlace para restablecer tu contraseña.
                            </p>
                        </div>
                    </div>

                    <div class="login-benefit-grid">
                        <div class="login-benefit">
                            {!! $loginIcon('mail', 'login-benefit-icon') !!}
                            <span>Enlace por correo</span>
                        </div>
                        <div class="login-benefit">
                            {!! $loginIcon('shield', 'login-benefit-icon') !!}
                            <span>Token temporal</span>
                        </div>
                    </div>
                </div>

                <div>
                    @if (session('status'))
                        <div class="login-alert-success-v1">
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

                    <form id="forgotPasswordForm" class="login-form-v1 login-form-executive" action="{{ route('password.email') }}" method="POST">
                        @csrf

                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                        <div class="form-group-v1 form-group-icon">
                            <label for="email">
                                {!! $loginIcon('mail', 'field-label-icon') !!}
                                <span>Correo electrónico</span>
                            </label>

                            <div class="input-with-icon">
                                {!! $loginIcon('mail', 'input-icon') !!}
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    autocomplete="email"
                                    placeholder="usuario@empresa.com"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn-login-v1 btn-login-executive">
                            {!! $loginIcon('arrow', 'login-button-icon') !!}
                            <span>Enviar enlace</span>
                        </button>

                        <div class="login-options-v1" style="margin-top:16px">
                            <a href="{{ route('login') }}">
                                {!! $loginIcon('back', 'field-label-icon') !!}
                                Volver al inicio de sesión
                            </a>
                        </div>

                        <p class="recaptcha-note-v1">
                            <strong>Protección activa:</strong> la recuperación utiliza reCAPTCHA v3 y token temporal para reducir intentos automatizados.
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </div>

    @if(config('services.recaptcha.site_key'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('forgotPasswordForm');
                const recaptchaInput = document.getElementById('g-recaptcha-response');

                if (!form || !recaptchaInput || typeof grecaptcha === 'undefined') {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    grecaptcha.ready(function () {
                        grecaptcha
                            .execute('{{ config('services.recaptcha.site_key') }}', { action: 'forgot_password' })
                            .then(function (token) {
                                recaptchaInput.value = token;
                                form.submit();
                            });
                    });
                });
            });
        </script>
    @endif
</body>
</html>

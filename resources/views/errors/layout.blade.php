@php
  $errorCode = trim($__env->yieldContent('code')) ?: '500';
  $errorTitle = trim($__env->yieldContent('title')) ?: 'Situación no disponible';
  $errorHeading = trim($__env->yieldContent('heading')) ?: 'No fue posible completar la operación';
  $errorMessage = trim($__env->yieldContent('message')) ?: 'SWAFI no pudo completar la solicitud en este momento.';
  $errorDetail = trim($__env->yieldContent('detail'));
  $primaryLabel = trim($__env->yieldContent('primary_label')) ?: 'Ir al inicio';
  $primaryUrl = trim($__env->yieldContent('primary_url')) ?: url('/');
  $secondaryLabel = trim($__env->yieldContent('secondary_label'));
  $secondaryUrl = trim($__env->yieldContent('secondary_url'));
  $requestId = trim((string) request()->attributes->get('swafi_request_id', ''));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>{{ $errorTitle }} | SWAFI</title>
  <style>
    :root {
      color-scheme: light;
      --swafi-blue: #0f4f98;
      --swafi-blue-dark: #082f67;
      --swafi-blue-soft: #eaf3ff;
      --swafi-text: #153456;
      --swafi-muted: #64748b;
      --swafi-line: #d9e6f5;
      --swafi-bg: #f3f6fb;
      --swafi-white: #ffffff;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      min-height: 100%;
      margin: 0;
    }

    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      overflow-x: clip;
      background:
        radial-gradient(circle at 14% 12%, rgba(77, 145, 227, .17), transparent 32%),
        radial-gradient(circle at 88% 84%, rgba(15, 79, 152, .13), transparent 30%),
        var(--swafi-bg);
      color: var(--swafi-text);
      font-family: Inter, Montserrat, Arial, sans-serif;
    }

    .error-shell {
      width: min(760px, 100%);
    }

    .error-card {
      position: relative;
      overflow: hidden;
      padding: clamp(26px, 5vw, 54px);
      border: 1px solid rgba(195, 213, 235, .92);
      border-radius: 30px;
      background: rgba(255, 255, 255, .96);
      box-shadow: 0 28px 70px rgba(20, 52, 91, .16);
    }

    .error-card::before {
      content: "";
      position: absolute;
      top: -110px;
      right: -80px;
      width: 260px;
      height: 260px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(45, 116, 204, .16), rgba(15, 79, 152, .03));
      pointer-events: none;
    }

    .brand {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      gap: 13px;
      margin-bottom: 34px;
    }

    .brand-logo {
      width: 58px;
      height: 58px;
      display: grid;
      place-items: center;
      overflow: hidden;
      border: 1px solid var(--swafi-line);
      border-radius: 18px;
      background: var(--swafi-white);
      box-shadow: 0 10px 24px rgba(15, 79, 152, .10);
    }

    .brand-logo img {
      width: 47px;
      height: 47px;
      object-fit: contain;
    }

    .brand-copy span,
    .brand-copy strong {
      display: block;
    }

    .brand-copy span {
      color: var(--swafi-muted);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .12em;
      text-transform: uppercase;
    }

    .brand-copy strong {
      margin-top: 2px;
      color: var(--swafi-blue-dark);
      font-size: 24px;
      line-height: 1;
    }

    .error-code {
      position: relative;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      min-height: 31px;
      padding: 6px 12px;
      border: 1px solid #cfe0f5;
      border-radius: 999px;
      background: var(--swafi-blue-soft);
      color: var(--swafi-blue);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
    }

    h1 {
      position: relative;
      z-index: 1;
      max-width: 620px;
      margin: 18px 0 12px;
      color: #11355f;
      font-size: clamp(30px, 5vw, 48px);
      line-height: 1.05;
      letter-spacing: -.035em;
    }

    .error-message,
    .error-detail {
      position: relative;
      z-index: 1;
      max-width: 630px;
      margin: 0;
      color: #566b84;
      font-size: 16px;
      line-height: 1.65;
    }

    .error-detail {
      margin-top: 10px;
      font-size: 14px;
    }

    .error-actions {
      position: relative;
      z-index: 1;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 30px;
    }

    .error-button {
      min-height: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 18px;
      border: 1px solid var(--swafi-blue);
      border-radius: 14px;
      background: var(--swafi-blue);
      color: var(--swafi-white);
      font-size: 14px;
      font-weight: 850;
      text-decoration: none;
      box-shadow: 0 10px 22px rgba(15, 79, 152, .18);
      transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
    }

    .error-button:hover,
    .error-button:focus-visible {
      background: var(--swafi-blue-dark);
      transform: translateY(-1px);
      box-shadow: 0 12px 26px rgba(8, 47, 103, .23);
    }

    .error-button.secondary {
      border-color: var(--swafi-line);
      background: var(--swafi-white);
      color: var(--swafi-blue);
      box-shadow: none;
    }

    .error-button.secondary:hover,
    .error-button.secondary:focus-visible {
      background: var(--swafi-blue-soft);
    }

    .error-reference {
      position: relative;
      z-index: 1;
      margin-top: 30px;
      padding-top: 17px;
      border-top: 1px solid #e4edf8;
      color: #71839a;
      font-size: 12px;
      line-height: 1.5;
    }

    .error-reference code {
      display: inline-block;
      max-width: 100%;
      margin-left: 4px;
      color: #314e70;
      font-family: Consolas, Monaco, monospace;
      font-size: 11px;
      overflow-wrap: anywhere;
    }

    @media (max-width: 560px) {
      body {
        padding: 14px;
      }

      .error-card {
        border-radius: 22px;
      }

      .brand {
        margin-bottom: 26px;
      }

      .error-actions {
        display: grid;
      }

      .error-button {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <main class="error-shell">
    <section class="error-card" aria-labelledby="swafi-error-title">
      <div class="brand">
        <div class="brand-logo">
          <img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Bimbo">
        </div>
        <div class="brand-copy">
          <span>Bimbo S.A. de C.V.</span>
          <strong>SWAFI</strong>
        </div>
      </div>

      <span class="error-code">Código {{ $errorCode }}</span>
      <h1 id="swafi-error-title">{{ $errorHeading }}</h1>
      <p class="error-message">{{ $errorMessage }}</p>

      @if ($errorDetail !== '')
        <p class="error-detail">{{ $errorDetail }}</p>
      @endif

      <div class="error-actions">
        <a class="error-button" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>

        @if ($secondaryLabel !== '' && $secondaryUrl !== '')
          <a class="error-button secondary" href="{{ $secondaryUrl }}">{{ $secondaryLabel }}</a>
        @endif
      </div>

      @if ($requestId !== '')
        <div class="error-reference">
          Referencia para soporte:<code>{{ $requestId }}</code>
        </div>
      @endif
    </section>
  </main>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SWAFI | Resolución de traslado</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#16304d;">
@php
    $approved = $status === 'aprobado';
    $accent = $approved ? '#1f7a3d' : '#b42318';
    $softBackground = $approved ? '#eaf8ee' : '#fff0f0';
    $softBorder = $approved ? '#b8e0c2' : '#f1b8b8';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:28px 0;">
    <tr>
        <td align="center">
            <table width="660" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dce7f5;">
                <tr>
                    <td style="background:#1f559b;color:#ffffff;padding:24px 28px;">
                        <h1 style="margin:0;font-size:24px;">SWAFI</h1>
                        <p style="margin:6px 0 0;font-size:14px;">Sistema Web de Gestión de Facturas de Activo Fijo</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <div style="margin:0 0 18px;padding:15px 17px;border-radius:14px;background:{{ $softBackground }};border:1px solid {{ $softBorder }};color:{{ $accent }};font-size:18px;font-weight:bold;">
                            Solicitud de traslado {{ strtolower($statusLabel) }}
                        </div>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 14px;">
                            Hola, <strong>{{ $requesterName }}</strong>.
                        </p>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 18px;">
                            El Usuario Captura responsable resolvió la solicitud de cambio de ubicación entre plantas que registraste en SWAFI.
                            @if($movementApplied)
                                La nueva ubicación ya fue aplicada al activo y el movimiento quedó registrado en la bitácora de auditoría.
                            @else
                                La ubicación vigente del activo no fue modificada.
                            @endif
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #dce7f5;">
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;width:195px;">Solicitud</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $requestUuid }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Activo</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $numeroActivo }} · {{ $descripcionActivo }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Resultado</td>
                                <td style="padding:10px 12px;font-size:13px;color:{{ $accent }};font-weight:bold;">{{ $statusLabel }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Ubicación de origen</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $originLocation }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Ubicación de destino</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $destinationLocation }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Resolvió</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $resolvedBy }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Fecha de resolución</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $resolvedAt }}</td>
                            </tr>
                        </table>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 8px;"><strong>Comentario de resolución:</strong></p>
                        <p style="font-size:14px;line-height:1.55;margin:0 0 20px;padding:14px;border-radius:12px;background:#f8fbff;border:1px solid #dce7f5;">
                            {{ $resolutionComment }}
                        </p>

                        <p style="text-align:center;margin:26px 0;">
                            <a href="{{ $reviewUrl }}" style="display:inline-block;background:#1f559b;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:bold;">
                                Consultar solicitud en SWAFI
                            </a>
                        </p>

                        <p style="font-size:12px;line-height:1.45;margin:22px 0 0;color:#64748b;">
                            Este correo fue generado automáticamente. La resolución, el cambio de ubicación cuando fue aprobado y el resultado de la notificación quedan registrados con trazabilidad en SWAFI.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="background:#f8fbff;padding:16px 28px;color:#64748b;font-size:12px;">
                        Bimbo S.A. de C.V. · SWAFI · Mensaje automático
                    </td>
                </tr>
            </table>

            <p style="max-width:660px;margin:16px auto 0;color:#64748b;font-size:12px;line-height:1.45;">
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>{{ $reviewUrl }}
            </p>
        </td>
    </tr>
</table>
</body>
</html>

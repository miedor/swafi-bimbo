<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SWAFI | Traslado pendiente de aprobación</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#16304d;">
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
                        <h2 style="margin:0 0 12px;font-size:22px;color:#12345a;">Traslado entre plantas pendiente de tu aprobación</h2>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 14px;">
                            Hola, <strong>{{ $approverName }}</strong>.
                        </p>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 18px;">
                            <strong>{{ $requestedBy }}</strong> te asignó como responsable de revisar una solicitud de traslado entre plantas. La ubicación actual del activo permanecerá sin cambios hasta que apruebes o rechaces la solicitud dentro de SWAFI.
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
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Ubicación de origen</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $originLocation }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Ubicación de destino</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $destinationLocation }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Fecha propuesta</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $movementDate }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Responsable destino</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $destinationResponsible }}</td>
                            </tr>
                        </table>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 8px;"><strong>Motivo del traslado:</strong></p>
                        <p style="font-size:14px;line-height:1.55;margin:0 0 20px;padding:14px;border-radius:12px;background:#fff7db;border:1px solid #f9d36a;">
                            {{ $reason }}
                        </p>

                        <p style="text-align:center;margin:26px 0;">
                            <a href="{{ $reviewUrl }}" style="display:inline-block;background:#1f559b;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:bold;">
                                Revisar solicitud en SWAFI
                            </a>
                        </p>

                        <p style="font-size:12px;line-height:1.45;margin:22px 0 0;color:#64748b;">
                            Este correo fue generado automáticamente. La persona asignada, los intentos de notificación y la resolución quedarán registrados en la bitácora de auditoría de SWAFI.
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

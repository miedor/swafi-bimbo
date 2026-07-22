<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SWAFI | Resolución de observación</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#16304d;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:28px 0;">
        <tr>
            <td align="center">
                <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dce7f5;">
                    <tr>
                        <td style="background:#1f559b;color:#ffffff;padding:24px 28px;">
                            <h1 style="margin:0;font-size:24px;">SWAFI</h1>
                            <p style="margin:6px 0 0;font-size:14px;">
                                Seguimiento de observaciones de expedientes
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <h2 style="margin:0 0 12px;font-size:22px;color:#12345a;">
                                Resolución de observación: {{ $decision }}
                            </h2>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 14px;">
                                Hola, <strong>{{ $assignedName }}</strong>.
                            </p>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 18px;">
                                Consulta / Auditoría revisó la corrección registrada y emitió una resolución en SWAFI.
                            </p>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 18px;padding:12px 14px;border-radius:12px;background:{{ $decision === 'Cerrada' ? '#eef8f1' : '#fff0ee' }};border:1px solid {{ $decision === 'Cerrada' ? '#b9e5bf' : '#fecaca' }};color:{{ $decision === 'Cerrada' ? '#1f5f2b' : '#9f1c13' }};font-weight:bold;">
                                Resultado: {{ $decision }}
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #dce7f5;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;width:180px;">Activo</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $numeroActivo }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;">Factura</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $folioFactura }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;">Tipo</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $tipoObservacion }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;">Validó</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $validatedBy }}</td>
                                </tr>
                            </table>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 8px;">
                                <strong>Observación:</strong>
                            </p>
                            <p style="font-size:14px;line-height:1.55;margin:0 0 16px;padding:14px;border-radius:12px;background:#f8fbff;border:1px solid #dce7f5;">
                                {{ $descripcion }}
                            </p>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 8px;">
                                <strong>Respuesta que se revisó:</strong>
                            </p>
                            <p style="font-size:14px;line-height:1.55;margin:0 0 16px;padding:14px;border-radius:12px;background:#f8fbff;border:1px solid #dce7f5;">
                                {{ $respuestaAtencion }}
                            </p>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 8px;">
                                <strong>Comentario de validación:</strong>
                            </p>
                            <p style="font-size:14px;line-height:1.55;margin:0 0 20px;padding:14px;border-radius:12px;background:#fff7db;border:1px solid #f9d36a;color:#7a4b00;">
                                {{ $comentarioValidacion }}
                            </p>

                            <p style="text-align:center;margin:26px 0;">
                                <a href="{{ $urlExpediente }}"
                                   style="display:inline-block;background:#1f559b;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:bold;">
                                    Consultar observación en SWAFI
                                </a>
                            </p>

                            <p style="font-size:12px;line-height:1.45;margin:22px 0 0;color:#64748b;">
                                Si la corrección fue rechazada, la observación regresa al flujo de atención del usuario asignado. Toda acción queda registrada en la bitácora.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fbff;padding:16px 28px;color:#64748b;font-size:12px;">
                            Bimbo S.A. de C.V. · SWAFI · Mensaje automático
                        </td>
                    </tr>
                </table>

                <p style="max-width:640px;margin:16px auto 0;color:#64748b;font-size:12px;line-height:1.45;">
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    {{ $urlExpediente }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

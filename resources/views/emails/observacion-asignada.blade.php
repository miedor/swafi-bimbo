<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SWAFI | Nueva observación asignada</title>
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
                                Sistema Web de Gestión de Facturas de Activo Fijo
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <h2 style="margin:0 0 12px;font-size:22px;color:#12345a;">
                                Nueva observación asignada
                            </h2>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 14px;">
                                Hola, <strong>{{ $assignedName }}</strong>.
                            </p>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 18px;">
                                Consulta / Auditoría registró una observación que requiere tu atención en SWAFI.
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
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;">Prioridad</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $prioridad }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;">Rol responsable</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $rolDestino }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;color:#12345a;">Registró</td>
                                    <td style="padding:10px 12px;font-size:13px;">{{ $creatorName }}</td>
                                </tr>
                            </table>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 8px;">
                                <strong>Descripción de la observación:</strong>
                            </p>

                            <p style="font-size:14px;line-height:1.55;margin:0 0 20px;padding:14px;border-radius:12px;background:#f8fbff;border:1px solid #dce7f5;">
                                {{ $descripcion }}
                            </p>

                            <p style="text-align:center;margin:26px 0;">
                                <a href="{{ $urlExpediente }}"
                                   style="display:inline-block;background:#1f559b;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:bold;">
                                    Revisar expediente en SWAFI
                                </a>
                            </p>

                            <p style="font-size:12px;line-height:1.45;margin:22px 0 0;color:#64748b;">
                                Este mensaje fue generado automáticamente. La atención, cambios y validación quedarán registrados en la bitácora de auditoría de SWAFI.
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SWAFI | Discrepancia de inventario</title>
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
                        <h2 style="margin:0 0 12px;font-size:22px;color:#12345a;">Discrepancia de inventario registrada</h2>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 14px;">
                            Hola, <strong>{{ $recipientName }}</strong>.
                        </p>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 18px;">
                            Se registró una verificación física que requiere revisión de Contabilidad o Auditoría.
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #dce7f5;">
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;width:190px;">Activo</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $numeroActivo }} · {{ $descripcionActivo }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Fecha de inventario</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $fechaInventario }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Estatus</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $estatusLocalizacion }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Ubicación registrada</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $ubicacionRegistrada }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Ubicación verificada</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $ubicacionVerificada }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Evidencias</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $evidenceCount }} archivo(s)</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 12px;background:#f8fbff;font-size:13px;font-weight:bold;">Registró</td>
                                <td style="padding:10px 12px;font-size:13px;">{{ $reportedBy }}</td>
                            </tr>
                        </table>

                        <p style="font-size:15px;line-height:1.55;margin:0 0 8px;"><strong>Observaciones:</strong></p>
                        <p style="font-size:14px;line-height:1.55;margin:0 0 20px;padding:14px;border-radius:12px;background:#fff7db;border:1px solid #f9d36a;">
                            {{ $observaciones }}
                        </p>

                        <p style="text-align:center;margin:26px 0;">
                            <a href="{{ $detailUrl }}" style="display:inline-block;background:#1f559b;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:bold;">
                                Revisar activo en SWAFI
                            </a>
                        </p>

                        <p style="font-size:12px;line-height:1.45;margin:22px 0 0;color:#64748b;">
                            Este correo fue generado automáticamente. El inventario, las evidencias y la notificación quedan registrados en la bitácora de auditoría de SWAFI.
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
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>{{ $detailUrl }}
            </p>
        </td>
    </tr>
</table>
</body>
</html>

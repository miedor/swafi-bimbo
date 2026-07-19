<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte programado SWAFI</title>
</head>
<body style="margin:0;padding:24px;background:#f3f6fb;color:#16304d;font-family:Arial,sans-serif;">
    <table role="presentation" style="width:100%;max-width:680px;margin:0 auto;border-collapse:collapse;background:#ffffff;border:1px solid #dbe7f6;border-radius:18px;overflow:hidden;">
        <tr>
            <td style="padding:24px;background:#174f9a;color:#ffffff;">
                <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Sistema SWAFI</div>
                <h1 style="margin:8px 0 0;font-size:22px;line-height:1.25;">Reporte programado disponible</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px;line-height:1.6;">Se generó automáticamente el reporte configurado en SWAFI.</p>

                <table role="presentation" style="width:100%;border-collapse:collapse;background:#f8fbff;border:1px solid #dfe9f7;border-radius:12px;">
                    <tr>
                        <td style="padding:10px 12px;font-weight:700;width:180px;">Nombre</td>
                        <td style="padding:10px 12px;">{{ $reportName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;font-weight:700;">Tipo</td>
                        <td style="padding:10px 12px;">{{ $reportType }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;font-weight:700;">Fecha de generación</td>
                        <td style="padding:10px 12px;">{{ $generatedAt }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;font-weight:700;">Registros incluidos</td>
                        <td style="padding:10px 12px;">{{ number_format($rowCount) }}</td>
                    </tr>
                </table>

                <p style="margin:18px 0 0;line-height:1.6;">El archivo se encuentra adjunto a este mensaje. Su contenido conserva los filtros y las columnas definidos en la plantilla guardada.</p>
                <p style="margin:14px 0 0;color:#64748b;font-size:12px;line-height:1.5;">Este correo fue generado automáticamente. La programación puede activarse, suspenderse o actualizarse desde el Centro de reportes de SWAFI.</p>
            </td>
        </tr>
    </table>
</body>
</html>

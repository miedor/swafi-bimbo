<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SWAFI | Restablecimiento de contraseña</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#16304d;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:28px 0;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dce7f5;">
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
                                Restablecimiento de contraseña
                            </h2>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 14px;">
                                Hola, <strong>{{ $userName }}</strong>.
                            </p>

                            <p style="font-size:15px;line-height:1.55;margin:0 0 18px;">
                                Recibimos una solicitud para restablecer la contraseña de tu cuenta en SWAFI.
                                Para continuar, da clic en el siguiente botón:
                            </p>

                            <p style="text-align:center;margin:26px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block;background:#1f559b;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:bold;">
                                    Restablecer contraseña
                                </a>
                            </p>

                            <p style="font-size:14px;line-height:1.55;margin:0 0 12px;">
                                Este enlace estará disponible durante <strong>{{ $minutes }} minutos</strong>.
                            </p>

                            <p style="font-size:14px;line-height:1.55;margin:0 0 12px;">
                                Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña actual seguirá siendo válida.
                            </p>

                            <p style="font-size:12px;line-height:1.45;margin:22px 0 0;color:#64748b;">
                                Por seguridad, no compartas este enlace. El acceso al sistema queda registrado en bitácora de auditoría.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fbff;padding:16px 28px;color:#64748b;font-size:12px;">
                            Bimbo S.A. de C.V. · SWAFI · Mensaje automático
                        </td>
                    </tr>
                </table>

                <p style="max-width:620px;margin:16px auto 0;color:#64748b;font-size:12px;line-height:1.45;">
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    {{ $resetUrl }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

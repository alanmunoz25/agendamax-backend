<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de recuperación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .code-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border: 2px solid #4F46E5;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #4F46E5;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        .warning {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 12px;
            margin-top: 20px;
            font-size: 14px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Recuperación de contraseña</h1>
    </div>
    <div class="content">
        <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>
        <p>Tu código de recuperación es:</p>
        <div class="code-box">
            <span class="code">{{ $code }}</span>
        </div>
        <p>Este código expira en <strong>15 minutos</strong>.</p>
        <div class="warning">
            Si no solicitaste restablecer tu contraseña, puedes ignorar este mensaje. Tu cuenta permanece segura.
        </div>
    </div>
    <div class="footer">
        <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
    </div>
</body>
</html>

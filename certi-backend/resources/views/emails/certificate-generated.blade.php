<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tu certificado está listo</title>
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
            text-align: center;
            margin-bottom: 30px;
        }
        .header img {
            max-width: 150px;
        }
        h1 {
            color: #2563eb;
            margin-bottom: 20px;
        }
        .content {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 15px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Tu certificado está listo!</h1>
    </div>

    <div class="content">
        <p>Hola <strong>{{ $userName }}</strong>,</p>
        
        <p>Nos complace informarte que tu certificado para <strong>{{ $activityName }}</strong> ha sido generado exitosamente.</p>
        
        <p>Detalles del certificado:</p>
        <ul>
            <li><strong>Nombre:</strong> {{ $certificateName }}</li>
            <li><strong>Fecha de emisión:</strong> {{ $issueDate }}</li>
        </ul>
        
        <p>Puedes verificar la autenticidad de tu certificado en cualquier momento utilizando el enlace a continuación:</p>
        
        <p style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verificar Certificado</a>
        </p>
        
        <p>También hemos adjuntado una copia de tu certificado a este correo electrónico para que puedas descargarlo directamente.</p>
    </div>

    <div class="footer">
        <p>Este es un correo automático, por favor no responda a este mensaje.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
    </div>
</body>
</html>
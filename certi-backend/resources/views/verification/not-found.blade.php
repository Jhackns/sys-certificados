<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado No Encontrado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header-section {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }
        .content-section {
            padding: 3rem 2rem;
            text-align: center;
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .verification-code {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            margin: 1.5rem 0;
            border: 2px dashed #dee2e6;
        }
        .suggestions {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 0 10px 10px 0;
        }
        .suggestions h6 {
            color: #1976d2;
            margin-bottom: 1rem;
        }
        .suggestions ul {
            margin-bottom: 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="error-card">
                    <!-- Header -->
                    <div class="header-section">
                        <i class="fas fa-exclamation-triangle error-icon"></i>
                        <h1 class="h2 mb-2">Certificado No Encontrado</h1>
                        <p class="mb-0">El código de verificación no es válido</p>
                    </div>

                    <!-- Content -->
                    <div class="content-section">
                        <p class="lead text-muted mb-4">
                            No se pudo encontrar un certificado con el código de verificación proporcionado.
                        </p>

                        <div class="verification-code">
                            <strong>Código buscado:</strong><br>
                            {{ $verification_code }}
                        </div>

                        <div class="suggestions">
                            <h6><i class="fas fa-lightbulb me-2"></i>Posibles causas:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check me-2 text-primary"></i>El código fue escrito incorrectamente</li>
                                <li><i class="fas fa-check me-2 text-primary"></i>El certificado ha sido revocado o anulado</li>
                                <li><i class="fas fa-check me-2 text-primary"></i>El código QR está dañado o es ilegible</li>
                                <li><i class="fas fa-check me-2 text-primary"></i>El certificado no existe en el sistema</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2 d-md-block">
                            <button onclick="history.back()" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>Volver
                            </button>
                            <button onclick="window.location.reload()" class="btn btn-primary btn-lg">
                                <i class="fas fa-redo me-2"></i>Intentar de Nuevo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4">
                    <p class="text-white-50">
                        <i class="fas fa-shield-alt me-2"></i>
                        Sistema de Certificados Digitales
                    </p>
                    <small class="text-white-50">
                        Si crees que esto es un error, contacta al administrador del sistema
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
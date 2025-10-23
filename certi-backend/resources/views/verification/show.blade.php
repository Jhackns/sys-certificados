<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Certificado - {{ $validation_data['verification_code'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header-section {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: bold;
            margin-top: 1rem;
        }
        .status-valid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-row {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .info-value {
            color: #333;
            font-size: 1.1rem;
        }
        .download-section {
            background: #f8f9fa;
            padding: 2rem;
            text-align: center;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="verification-card">
                    <!-- Header -->
                    <div class="header-section">
                        <i class="fas fa-certificate fa-3x mb-3"></i>
                        <h1 class="h2 mb-2">Certificado Verificado</h1>
                        <p class="mb-0">Código: {{ $validation_data['verification_code'] }}</p>
                        <div class="status-badge {{ $validation_data['is_valid'] ? 'status-valid' : 'status-invalid' }}">
                            <i class="fas {{ $validation_data['is_valid'] ? 'fa-check-circle' : 'fa-times-circle' }} me-2"></i>
                            {{ $validation_data['is_valid'] ? 'VÁLIDO' : 'INVÁLIDO' }}
                        </div>
                    </div>

                    <!-- Certificate Information -->
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-user me-2"></i>Titular del Certificado
                                    </div>
                                    <div class="info-value">{{ $validation_data['holder_name'] }}</div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-award me-2"></i>Título del Certificado
                                    </div>
                                    <div class="info-value">{{ $validation_data['certificate_title'] }}</div>
                                </div>

                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-calendar-alt me-2"></i>Fecha de Emisión
                                    </div>
                                    <div class="info-value">{{ $validation_data['issue_date'] }}</div>
                                </div>

                                @if($validation_data['expiry_date'])
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-calendar-times me-2"></i>Fecha de Vencimiento
                                    </div>
                                    <div class="info-value">{{ $validation_data['expiry_date'] }}</div>
                                </div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-tasks me-2"></i>Actividad
                                    </div>
                                    <div class="info-value">{{ $validation_data['activity'] }}</div>
                                </div>

                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-file-alt me-2"></i>Plantilla
                                    </div>
                                    <div class="info-value">{{ $validation_data['template'] }}</div>
                                </div>

                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-signature me-2"></i>Firmado por
                                    </div>
                                    <div class="info-value">{{ $validation_data['signer'] }}</div>
                                </div>

                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="fas fa-eye me-2"></i>Verificaciones
                                    </div>
                                    <div class="info-value">{{ $validation_data['verification_count'] }} veces</div>
                                </div>
                            </div>
                        </div>

                        @if($validation_data['description'])
                        <div class="info-row mt-3">
                            <div class="info-label">
                                <i class="fas fa-info-circle me-2"></i>Descripción
                            </div>
                            <div class="info-value">{{ $validation_data['description'] }}</div>
                        </div>
                        @endif
                    </div>

                    <!-- Download Section -->
                    <div class="download-section">
                        <h5 class="mb-3">
                            <i class="fas fa-download me-2"></i>Descargar Certificado
                        </h5>
                        <p class="text-muted mb-3">Descarga una copia del certificado en formato PDF</p>
                        <a href="{{ route('certificate.verify.download', $validation_data['verification_code']) }}" 
                           class="btn btn-primary btn-lg">
                            <i class="fas fa-file-pdf me-2"></i>Descargar PDF
                        </a>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4">
                    <p class="text-white-50">
                        <i class="fas fa-shield-alt me-2"></i>
                        Sistema de Certificados Digitales - Verificación Segura
                    </p>
                    @if($validation_data['last_verified'])
                    <small class="text-white-50">
                        Última verificación: {{ $validation_data['last_verified'] }}
                    </small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
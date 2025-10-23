<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CertificateVerificationController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Sistema de Certificados Digitales API',
        'version' => '1.0.0',
        'endpoints' => [
            'login' => '/api/auth/login',
            'register' => '/api/auth/register',
            'public_companies' => '/api/public/companies',
            'validate_certificate' => '/api/public/validate-certificate',
            'verify_certificate' => '/verify/{code}'
        ]
    ]);
});

// Ruta de login para redirecciones (evita el error de ruta no encontrada)
Route::get('/login', function () {
    return response()->json([
        'error' => 'Unauthorized',
        'message' => 'Please use /api/auth/login for authentication',
        'login_endpoint' => '/api/auth/login'
    ], 401);
})->name('login');

// Rutas públicas de verificación de certificados
Route::prefix('verify')->group(function () {
    Route::get('/{verificationCode}', [CertificateVerificationController::class, 'show'])
        ->name('certificate.verify.show');
    
    Route::get('/{verificationCode}/download', [CertificateVerificationController::class, 'downloadPdf'])
        ->name('certificate.verify.download');
});

// API de verificación
Route::prefix('api/verify')->group(function () {
    Route::get('/{verificationCode}', [CertificateVerificationController::class, 'verify'])
        ->name('api.certificate.verify');
});

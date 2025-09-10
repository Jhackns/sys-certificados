<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Sistema de Certificados Digitales API',
        'version' => '1.0.0',
        'endpoints' => [
            'login' => '/api/auth/login',
            'register' => '/api/auth/register',
            'public_companies' => '/api/public/companies',
            'validate_certificate' => '/api/public/validate-certificate'
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

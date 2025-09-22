<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importar controladores organizados por módulos
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\AccessControl\UserController;
use App\Http\Controllers\API\AccessControl\RoleController;
use App\Http\Controllers\API\AccessControl\PermissionController;
use App\Http\Controllers\API\Activities\ActivityController;
use App\Http\Controllers\API\Certificates\CertificateController;
use App\Http\Controllers\API\Certificates\ValidationController;
use App\Http\Controllers\API\Certificates\CertificateTemplateController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran las rutas API para la aplicación. Estas rutas
| son cargadas por el RouteServiceProvider y todas serán asignadas
| al grupo de middleware "api".
|
*/

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
| Estas rutas no requieren autenticación y están disponibles públicamente
*/

// === AUTENTICACIÓN PÚBLICA ===
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// === VALIDACIÓN PÚBLICA DE CERTIFICADOS ===
Route::prefix('public')->group(function () {
    Route::post('/validate-certificate', [ValidationController::class, 'validateCertificate']);
    Route::get('/validation/{code}', [ValidationController::class, 'byCode']);
    Route::get('/certificate/{code}', [CertificateController::class, 'byCode']);
});

// === CONSULTAS PÚBLICAS ===
/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS CON AUTENTICACIÓN
|--------------------------------------------------------------------------
| Todas estas rutas requieren autenticación con Sanctum
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTENTICACIÓN Y PERFIL
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });

    /*
    |--------------------------------------------------------------------------
    | CONTROL DE ACCESO
    |--------------------------------------------------------------------------
    | Gestión de usuarios, roles y permisos con middleware de permisos
    */

    // === GESTIÓN DE USUARIOS ===
    Route::prefix('users')->group(function () {
        // Endpoint para obtener roles disponibles (tiene su propia verificación de permisos)
        Route::get('/available-roles', [UserController::class, 'availableRoles']);

        // Rutas que requieren permission:users.read
        Route::middleware('permission:users.read')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/list', [UserController::class, 'list']); // Para dropdowns
            Route::get('/{id}', [UserController::class, 'show']);
            Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
            Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
            Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

            // Gestión de roles de usuario
            Route::post('/{id}/assign-roles', [UserController::class, 'assignRoles'])->middleware('permission:users.assign_roles');
        });
    });

    // === GESTIÓN DE ROLES ===
    Route::prefix('roles')->middleware('permission:roles.read')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

        // Gestión de permisos de rol
        Route::post('/{id}/assign-permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:permissions.assign');
        Route::delete('/{id}/remove-permissions', [RoleController::class, 'removePermissions'])->middleware('permission:permissions.assign');
        Route::get('/available-permissions', [RoleController::class, 'availablePermissions']);
        Route::post('/{id}/clone', [RoleController::class, 'clone'])->middleware('permission:roles.create');
    });

    // === GESTIÓN DE PERMISOS ===
    Route::prefix('permissions')->middleware('permission:permissions.read')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::post('/', [PermissionController::class, 'store'])->middleware('permission:permissions.create');
        Route::put('/{id}', [PermissionController::class, 'update'])->middleware('permission:permissions.update');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete');
    });

    // === GESTIÓN DE ACTIVIDADES ===
    Route::prefix('activities')->middleware('permission:activities.read')->group(function () {
        Route::get('/', [ActivityController::class, 'index']);
        Route::get('/list', [ActivityController::class, 'list']); // Para dropdowns
        Route::get('/{id}', [ActivityController::class, 'show']);
        Route::post('/', [ActivityController::class, 'store'])->middleware('permission:activities.create');
        Route::put('/{id}', [ActivityController::class, 'update'])->middleware('permission:activities.update');
        Route::delete('/{id}', [ActivityController::class, 'destroy'])->middleware('permission:activities.delete');

        // Funcionalidades especiales
        Route::patch('/{id}/toggle-status', [ActivityController::class, 'toggleStatus'])->middleware('permission:activities.update');
        Route::get('/{id}/certificates', [ActivityController::class, 'certificates']);
    });

    // === GESTIÓN DE PLANTILLAS DE CERTIFICADOS ===
    Route::prefix('certificate-templates')->group(function () {
        Route::get('/', [CertificateTemplateController::class, 'index'])->middleware('permission:templates.read');
        Route::get('/list', [CertificateTemplateController::class, 'list'])->middleware('permission:templates.read'); // Para dropdowns
        Route::get('/{id}', [CertificateTemplateController::class, 'show'])->middleware('permission:templates.read');
        Route::get('/{id}/preview', [CertificateTemplateController::class, 'preview'])->middleware('permission:templates.read'); // Para vista previa
        Route::post('/', [CertificateTemplateController::class, 'store']); // Temporalmente sin middleware
        Route::put('/{id}', [CertificateTemplateController::class, 'update'])->middleware('permission:templates.update');
        Route::delete('/{id}', [CertificateTemplateController::class, 'destroy'])->middleware('permission:templates.delete');

        // Funcionalidades especiales
        Route::patch('/{id}/toggle-status', [CertificateTemplateController::class, 'toggleStatus'])->middleware('permission:templates.update');
        Route::post('/{id}/clone', [CertificateTemplateController::class, 'clone'])->middleware('permission:templates.create');
    });

    // === GESTIÓN DE CERTIFICADOS ===
    Route::prefix('certificates')->middleware('permission:certificates.read')->group(function () {
        Route::get('/', [CertificateController::class, 'index']);
        Route::get('/{id}', [CertificateController::class, 'show']);
        Route::post('/', [CertificateController::class, 'store'])->middleware('permission:certificates.create');
        Route::put('/{id}', [CertificateController::class, 'update'])->middleware('permission:certificates.update');
        Route::delete('/{id}', [CertificateController::class, 'destroy'])->middleware('permission:certificates.delete');

        // Funcionalidades especiales de certificados
        Route::patch('/{id}/change-status', [CertificateController::class, 'changeStatus'])->middleware('permission:certificates.issue');
        Route::post('/{id}/generate-document', [CertificateController::class, 'generateDocument'])->middleware('permission:documents.upload');
        Route::get('/{id}/download', [CertificateController::class, 'download'])->middleware('permission:certificates.download');
        Route::get('/{id}/preview', [CertificateController::class, 'preview'])->middleware('permission:certificates.read');
        Route::post('/{id}/send-email', [CertificateController::class, 'sendEmail'])->middleware('permission:emails.send');

        // Estadísticas y reportes
        Route::get('/statistics/overview', [CertificateController::class, 'statisticsOverview'])->middleware('permission:reports.certificates');
        Route::get('/statistics/by-activity', [CertificateController::class, 'statisticsByActivity'])->middleware('permission:reports.certificates');

        // Búsquedas y filtros
        Route::get('/search', [CertificateController::class, 'search']);
        Route::get('/activity/{activityId}', [CertificateController::class, 'byActivity']);
    });

    // === GESTIÓN DE VALIDACIONES ===
    Route::prefix('validations')->middleware('permission:validations.read')->group(function () {
        Route::get('/', [ValidationController::class, 'index']);
        Route::get('/{id}', [ValidationController::class, 'show']);
        Route::post('/', [ValidationController::class, 'store'])->middleware('permission:validations.create');

        // Estadísticas
        Route::get('/statistics/overview', [ValidationController::class, 'statisticsOverview'])->middleware('permission:reports.validations');
        Route::get('/certificate/{certificateId}', [ValidationController::class, 'byCertificate']);
    });


    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Ruta no encontrada',
        'data' => null
    ], 404);
});

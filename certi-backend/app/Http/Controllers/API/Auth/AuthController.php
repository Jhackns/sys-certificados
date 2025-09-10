<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponseTrait;
    
    protected $authService;
    
    /**
     * Constructor
     * 
     * @param AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    
    /**
     * Registrar un nuevo usuario
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_id' => 'nullable|exists:companies,id',
            'role' => 'nullable|string|in:super_admin,administrador,emisor,validador,usuario_final',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $user = $this->authService->register($validator->validated());
            
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return $this->successResponse([
                'user' => new UserResource($user->load('company')),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => $user->hasVerifiedEmail(),
            ], 'Usuario registrado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al registrar usuario: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Iniciar sesión
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $result = $this->authService->login(
                $request->email,
                $request->password
            );
            
            if (!$result) {
                return $this->unauthorizedResponse('Credenciales inválidas');
            }
            
            if (isset($result['error']) && $result['error'] === 'inactive_user') {
                return $this->forbiddenResponse('Usuario inactivo');
            }

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'roles' => $result['roles'],
                'permissions' => $result['permissions'],
                'company' => $result['company'],
                'access_token' => $result['access_token'],
                'token_type' => $result['token_type'],
                'email_verified' => $result['email_verified'],
            ], 'Inicio de sesión exitoso');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al iniciar sesión: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener información del usuario autenticado
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load('company');
            
            return $this->successResponse([
                'user' => new UserResource($user),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'company' => $user->company,
            ], 'Información del usuario obtenida correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener información del usuario: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Cerrar sesión
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());
            
            return $this->successResponse(null, 'Sesión cerrada correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cerrar sesión: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cerrar todas las sesiones
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $this->authService->logoutAll($request->user());
            
            return $this->successResponse(null, 'Todas las sesiones han sido cerradas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cerrar todas las sesiones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar perfil
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $request->user()->id,
            'password' => 'sometimes|required|string|min:8|confirmed',
            'current_password' => 'required_with:password|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $user = $request->user();

            // Verificar contraseña actual si se quiere cambiar la contraseña
            if ($request->has('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return $this->errorResponse('La contraseña actual es incorrecta', 400);
                }
            }

            $updatedUser = $this->authService->updateProfile($user, $validator->validated());
            
            return $this->successResponse([
                'user' => new UserResource($updatedUser->load('company')),
            ], 'Perfil actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar perfil: ' . $e->getMessage(), 500);
        }
    }
}
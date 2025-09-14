<?php

namespace App\Http\Controllers\API\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Listar usuarios con filtros y paginación
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Solo el super_admin puede listar usuarios
        if (!$request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede listar usuarios');
        }

        try {
            $query = User::with(['company', 'roles']);

            // Filtros
            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            // Comentamos temporalmente el filtro de rol que puede estar causando problemas
            // if ($request->has('role')) {
            //     $query->role($request->role);
            // }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            return $this->successResponse([
                'users' => UserResource::collection($users->items()),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ], 'Usuarios obtenidos correctamente');
        } catch (\Exception $e) {
            \Log::error('Error en UserController@index: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error al obtener usuarios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar un usuario específico
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = User::with(['company', 'roles', 'permissions'])->findOrFail($id);

            return $this->successResponse([
                'user' => new UserResource($user)
            ], 'Usuario obtenido correctamente');
        } catch (\Exception $e) {
            return $this->notFoundResponse('Usuario no encontrado');
        }
    }

    /**
     * Crear un nuevo usuario
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Solo el super_admin puede crear usuarios
        if (!$request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede crear usuarios');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_id' => 'nullable|exists:companies,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $request->company_id,
            ]);

            // Asignar roles
            $user->assignRole($request->roles);

            return $this->successResponse([
                'user' => new UserResource($user->load(['company', 'roles']))
            ], 'Usuario creado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar un usuario
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Solo el super_admin puede actualizar usuarios
        if (!$request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede actualizar usuarios');
        }

        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|required|string|min:8|confirmed',
                'company_id' => 'sometimes|nullable|exists:companies,id',
                'roles' => 'sometimes|required|array',
                'roles.*' => 'exists:roles,name',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $data = $validator->validated();
            
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            // Actualizar roles si se proporcionan
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            return $this->successResponse([
                'user' => new UserResource($user->load(['company', 'roles']))
            ], 'Usuario actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar un usuario
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // Solo el super_admin puede eliminar usuarios
        if (!auth()->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede eliminar usuarios');
        }

        try {
            $user = User::findOrFail($id);
            
            // Verificar que no sea el usuario autenticado
            if (auth()->id() === $user->id) {
                return $this->errorResponse('No puedes eliminar tu propia cuenta', 400);
            }

            $user->delete();

            return $this->successResponse(null, 'Usuario eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Asignar roles a un usuario
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function assignRoles(Request $request, int $id): JsonResponse
    {
        // Solo el super_admin puede asignar roles
        if (!$request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede asignar roles');
        }

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $user = User::findOrFail($id);
            $user->syncRoles($request->roles);

            return $this->successResponse([
                'user' => new UserResource($user->load(['company', 'roles']))
            ], 'Roles asignados correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al asignar roles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener roles disponibles
     *
     * @return JsonResponse
     */
    public function availableRoles(): JsonResponse
    {
        // Solo el super_admin puede ver roles disponibles
        if (!auth()->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede ver roles disponibles');
        }

        try {
            $roles = Role::all(['id', 'name']);

            return $this->successResponse([
                'roles' => $roles
            ], 'Roles disponibles obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener roles: ' . $e->getMessage(), 500);
        }
    }
}
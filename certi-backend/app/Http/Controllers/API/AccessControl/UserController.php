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
use Illuminate\Support\Facades\Log;

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
            // incluir roles explícitamente
            $query = User::with(['roles', 'permissions']);

            // Filtros
            // Elimina filtro por company_id
            // if ($request->has('company_id')) {
            //     $query->where('company_id', $request->company_id);
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
                // pasar modelos cargados al recurso para que 'roles' aparezca
                'users' => UserResource::collection($users->items()),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ], 'Usuarios obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error en UserController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return $this->errorResponse('Error al obtener usuarios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener lista simple de usuarios para dropdowns
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $users = User::select('id', 'name', 'email')
                ->where('activo', true)
                ->orderBy('name')
                ->get();

            return $this->successResponse([
                'users' => $users
            ], 'Lista de usuarios obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error en UserController@list: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener lista de usuarios: ' . $e->getMessage(), 500);
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
            $user = User::with(['roles', 'permissions'])->findOrFail($id); // Elimina 'company'

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
            'fecha_nacimiento' => 'nullable|date',
            'pais' => 'nullable|string|max:100',
            'genero' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'activo' => 'nullable|boolean',
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
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'pais' => $request->pais,
                'genero' => $request->genero,
                'telefono' => $request->telefono,
                'activo' => $request->has('activo') ? $request->activo : true,
                'last_login' => null,
            ]);

            // Asignar roles
            $user->assignRole($request->roles);

            return $this->successResponse([
                'user' => new UserResource($user->load(['roles'])) // Elimina 'company'
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
                'fecha_nacimiento' => 'sometimes|nullable|date',
                'pais' => 'sometimes|nullable|string|max:100',
                'genero' => 'sometimes|nullable|string|max:20',
                'telefono' => 'sometimes|nullable|string|max:20',
                'activo' => 'sometimes|nullable|boolean',
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

            // Elimina 'company_id' de $data si existe
            unset($data['company_id']);

            return $this->successResponse([
                'user' => new UserResource($user->load(['roles'])) // Elimina 'company'
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
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Solo el super_admin puede eliminar usuarios
        if (!$request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede eliminar usuarios');
        }

        try {
            $user = User::findOrFail($id);

            // Verificar que no sea el usuario autenticado
            if ($request->user()->id === $user->id) {
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
                'user' => new UserResource($user->load(['roles'])) // Elimina 'company'
            ], 'Roles asignados correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al asignar roles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener roles disponibles
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function availableRoles(Request $request): JsonResponse
    {
        // Solo el super_admin puede ver roles disponibles
        if (!$request->user()->hasRole('super_admin')) {
            return $this->forbiddenResponse('Solo el super admin puede ver roles disponibles');
        }

        try {
            $roles = Role::all(['id', 'name']);

            return $this->successResponse([
                'roles' => $roles
            ], 'Roles disponibles obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error en availableRoles: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener roles: ' . $e->getMessage(), 500);
        }
    }
}

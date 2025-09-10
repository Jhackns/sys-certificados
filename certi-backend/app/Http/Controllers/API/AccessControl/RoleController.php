<?php

namespace App\Http\Controllers\API\AccessControl;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    use ApiResponseTrait;

    /**
     * Listar todos los roles
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Role::with('permissions');

            // Filtro por nombre
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            // Incluir conteo de usuarios
            if ($request->has('with_users_count') && $request->with_users_count) {
                $query->withCount('users');
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $roles = $query->paginate($perPage);

            $rolesData = $roles->items();
            $formattedRoles = [];

            foreach ($rolesData as $role) {
                $roleData = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->pluck('name'),
                    'permissions_count' => $role->permissions->count(),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];

                if (isset($role->users_count)) {
                    $roleData['users_count'] = $role->users_count;
                }

                $formattedRoles[] = $roleData;
            }

            return $this->successResponse([
                'roles' => $formattedRoles,
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                ]
            ], 'Roles obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener roles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar un rol específico
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $role = Role::with(['permissions', 'users'])->findOrFail($id);

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    }),
                    'users' => $role->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ];
                    }),
                    'permissions_count' => $role->permissions->count(),
                    'users_count' => $role->users->count(),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ]
            ], 'Rol obtenido correctamente');
        } catch (\Exception $e) {
            return $this->notFoundResponse('Rol no encontrado');
        }
    }

    /**
     * Crear un nuevo rol
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? 'web',
            ]);

            // Asignar permisos si se proporcionan
            if ($request->has('permissions')) {
                $role->givePermissionTo($request->permissions);
            }

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->pluck('name'),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ]
            ], 'Rol creado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar un rol
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $id,
                'guard_name' => 'sometimes|string|max:255',
                'permissions' => 'sometimes|array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // Actualizar datos básicos del rol
            $role->update($validator->validated());

            // Sincronizar permisos si se proporcionan
            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->pluck('name'),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ]
            ], 'Rol actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar un rol
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);
            
            // Verificar si el rol está siendo usado por usuarios
            if ($role->users()->count() > 0) {
                return $this->errorResponse('No se puede eliminar el rol porque está asignado a uno o más usuarios', 400);
            }

            // Verificar que no sea un rol del sistema
            $systemRoles = ['super_admin', 'administrador', 'emisor', 'validador', 'usuario_final'];
            if (in_array($role->name, $systemRoles)) {
                return $this->errorResponse('No se puede eliminar un rol del sistema', 400);
            }

            $role->delete();

            return $this->successResponse(null, 'Rol eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Asignar permisos a un rol
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function assignPermissions(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $role = Role::findOrFail($id);
            $role->syncPermissions($request->permissions);

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ]
            ], 'Permisos asignados correctamente al rol');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al asignar permisos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remover permisos de un rol
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function removePermissions(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $role = Role::findOrFail($id);
            $role->revokePermissionTo($request->permissions);

            return $this->successResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ]
            ], 'Permisos removidos correctamente del rol');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al remover permisos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener permisos disponibles para asignar
     *
     * @return JsonResponse
     */
    public function availablePermissions(): JsonResponse
    {
        try {
            $permissions = Permission::all(['id', 'name']);
            
            // Agrupar por módulo
            $grouped = [];
            foreach ($permissions as $permission) {
                $parts = explode('.', $permission->name);
                $module = $parts[0] ?? 'general';
                
                if (!isset($grouped[$module])) {
                    $grouped[$module] = [];
                }
                
                $grouped[$module][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'action' => $parts[1] ?? 'general',
                ];
            }

            return $this->successResponse([
                'permissions' => $permissions,
                'permissions_grouped' => $grouped
            ], 'Permisos disponibles obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener permisos disponibles: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clonar un rol
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function clone(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $originalRole = Role::with('permissions')->findOrFail($id);
            
            $newRole = Role::create([
                'name' => $request->name,
                'guard_name' => $originalRole->guard_name,
            ]);

            // Copiar permisos
            $permissions = $originalRole->permissions->pluck('name')->toArray();
            $newRole->givePermissionTo($permissions);

            return $this->successResponse([
                'role' => [
                    'id' => $newRole->id,
                    'name' => $newRole->name,
                    'guard_name' => $newRole->guard_name,
                    'permissions' => $newRole->permissions->pluck('name'),
                    'created_at' => $newRole->created_at,
                ]
            ], 'Rol clonado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al clonar rol: ' . $e->getMessage(), 500);
        }
    }
}
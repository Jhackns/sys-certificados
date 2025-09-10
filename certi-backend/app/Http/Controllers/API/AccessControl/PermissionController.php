<?php

namespace App\Http\Controllers\API\AccessControl;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Listar todos los permisos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Permission::query();

            // Filtro por nombre
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            // Agrupar por módulo (basado en el prefijo del permiso)
            if ($request->has('grouped') && $request->grouped) {
                $permissions = $query->get();
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
                        'guard_name' => $permission->guard_name,
                        'created_at' => $permission->created_at,
                        'updated_at' => $permission->updated_at,
                    ];
                }

                return $this->successResponse([
                    'permissions_grouped' => $grouped
                ], 'Permisos agrupados obtenidos correctamente');
            }

            // Paginación
            $perPage = $request->get('per_page', 50);
            $permissions = $query->paginate($perPage);

            return $this->successResponse([
                'permissions' => $permissions->items(),
                'pagination' => [
                    'current_page' => $permissions->currentPage(),
                    'last_page' => $permissions->lastPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                ]
            ], 'Permisos obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener permisos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar un permiso específico
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $permission = Permission::with('roles')->findOrFail($id);

            return $this->successResponse([
                'permission' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'roles' => $permission->roles->pluck('name'),
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]
            ], 'Permiso obtenido correctamente');
        } catch (\Exception $e) {
            return $this->notFoundResponse('Permiso no encontrado');
        }
    }

    /**
     * Crear un nuevo permiso
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? 'web',
            ]);

            return $this->successResponse([
                'permission' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]
            ], 'Permiso creado correctamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar un permiso
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:permissions,name,' . $id,
                'guard_name' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $permission->update($validator->validated());

            return $this->successResponse([
                'permission' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]
            ], 'Permiso actualizado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar un permiso
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);
            
            // Verificar si el permiso está siendo usado por roles
            if ($permission->roles()->count() > 0) {
                return $this->errorResponse('No se puede eliminar el permiso porque está asignado a uno o más roles', 400);
            }

            $permission->delete();

            return $this->successResponse(null, 'Permiso eliminado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener permisos por módulo
     *
     * @return JsonResponse
     */
    public function byModule(): JsonResponse
    {
        try {
            $permissions = Permission::all();
            $modules = [];

            foreach ($permissions as $permission) {
                $parts = explode('.', $permission->name);
                $module = $parts[0] ?? 'general';
                
                if (!isset($modules[$module])) {
                    $modules[$module] = [
                        'name' => ucfirst($module),
                        'permissions' => []
                    ];
                }
                
                $modules[$module]['permissions'][] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'action' => $parts[1] ?? 'general',
                ];
            }

            return $this->successResponse([
                'modules' => $modules
            ], 'Permisos por módulo obtenidos correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener permisos por módulo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear permisos en lote
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*.name' => 'required|string|max:255|unique:permissions,name',
            'permissions.*.guard_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            $createdPermissions = [];
            
            foreach ($request->permissions as $permissionData) {
                $permission = Permission::create([
                    'name' => $permissionData['name'],
                    'guard_name' => $permissionData['guard_name'] ?? 'web',
                ]);
                
                $createdPermissions[] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ];
            }

            return $this->successResponse([
                'permissions' => $createdPermissions,
                'count' => count($createdPermissions)
            ], 'Permisos creados correctamente en lote', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear permisos en lote: ' . $e->getMessage(), 500);
        }
    }
}
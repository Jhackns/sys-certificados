<?php

namespace App\Http\Controllers\API\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    use ApiResponseTrait;

    protected $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    /**
     * Mostrar todas las empresas
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            
            // Si hay criterios de búsqueda, usar el método search
            if ($request->hasAny(['search', 'is_active', 'ruc'])) {
                $criteria = $request->only(['search', 'is_active', 'ruc']);
                $companies = $this->companyService->search($criteria, $perPage);
            } else {
                $companies = $this->companyService->getAll($perPage);
            }

            return $this->successResponse([
                'companies' => CompanyResource::collection($companies->items()),
                'pagination' => [
                    'current_page' => $companies->currentPage(),
                    'last_page' => $companies->lastPage(),
                    'per_page' => $companies->perPage(),
                    'total' => $companies->total(),
                ]
            ], 'Empresas obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener empresas: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener empresas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenar una nueva empresa
     *
     * @param CompanyRequest $request
     * @return JsonResponse
     */
    public function store(CompanyRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Crear la empresa
            $company = $this->companyService->create($data);
            
            // Log de la creación
            if (Auth::check()) {
                $user = Auth::user();
                Log::info('Empresa creada por usuario', [
                    'company_id' => $company->id,
                    'user_id' => $user->id
                ]);
            }
            
            return $this->successResponse([
                'company' => new CompanyResource($company)
            ], 'Empresa creada exitosamente', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear empresa: ' . $e->getMessage());
            
            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mostrar una empresa específica
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $company = $this->companyService->getById($id);
        
            if (!$company) {
                return $this->notFoundResponse('Empresa no encontrada');
            }

            return $this->successResponse([
                'company' => new CompanyResource($company)
            ], 'Empresa obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar una empresa
     *
     * @param CompanyRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(CompanyRequest $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $company = Company::find($id);

            if (!$company) {
                return $this->notFoundResponse('Empresa no encontrada');
            }

            $data = $request->validated();
            $updatedCompany = $this->companyService->update($company, $data);

            return $this->successResponse([
                'company' => new CompanyResource($updatedCompany)
            ], 'Empresa actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar una empresa
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $company = Company::find($id);

            if (!$company) {
                return $this->notFoundResponse('Empresa no encontrada');
            }

            $this->companyService->delete($company);

            return $this->successResponse(null, 'Empresa eliminada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activar/Desactivar empresa
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $company = Company::find($id);

            if (!$company) {
                return $this->notFoundResponse('Empresa no encontrada');
            }

            $status = $request->input('is_active', !$company->is_active);
            $updatedCompany = $this->companyService->toggleStatus($company, $status);

            $message = $status ? 'Empresa activada exitosamente' : 'Empresa desactivada exitosamente';

            return $this->successResponse([
                'company' => new CompanyResource($updatedCompany)
            ], $message);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al cambiar estado de empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas de una empresa
     *
     * @param int $id
     * @return JsonResponse
     */
    public function statistics($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $company = Company::find($id);

            if (!$company) {
                return $this->notFoundResponse('Empresa no encontrada');
            }

            $statistics = $this->companyService->getStatistics($company);

            return $this->successResponse([
                'statistics' => $statistics
            ], 'Estadísticas obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener usuarios de una empresa
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function users(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $company = Company::find($id);

            if (!$company) {
                return $this->notFoundResponse('Empresa no encontrada');
            }

            $perPage = $request->query('per_page', 15);
            $users = $this->companyService->getUsers($company, $perPage);

            return $this->successResponse([
                'users' => $users->items()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->roles->pluck('name'),
                        'created_at' => $user->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ], 'Usuarios de la empresa obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios de empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener usuarios: ' . $e->getMessage(), 500);
        }
    }
}
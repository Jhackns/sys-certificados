<?php

namespace App\Http\Controllers\API\Activities;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\ActivityService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    use ApiResponseTrait;

    protected $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Mostrar todas las actividades
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);

            // Si hay criterios de búsqueda, usar el método search
            if ($request->hasAny(['search', 'company_id', 'is_active'])) {
                $criteria = $request->only(['search', 'company_id', 'is_active']);
                $activities = $this->activityService->search($criteria, $perPage);
            } else {
                $activities = $this->activityService->getAll($perPage);
            }

            return $this->successResponse([
                'activities' => ActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                ]
            ], 'Actividades obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener actividades: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener actividades: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenar una nueva actividad
     *
     * @param ActivityRequest $request
     * @return JsonResponse
     */
    public function store(ActivityRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Crear la actividad
            $activity = $this->activityService->create($data);

            // Log de la creación
            if (Auth::check()) {
                $user = Auth::user();
                Log::info('Actividad creada por usuario', [
                    'activity_id' => $activity->id,
                    'user_id' => $user->id
                ]);
            }

            return $this->successResponse([
                'activity' => new ActivityResource($activity)
            ], 'Actividad creada exitosamente', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear actividad: ' . $e->getMessage());

            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mostrar una actividad específica
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $activity = $this->activityService->getById($id);

            if (!$activity) {
                return $this->notFoundResponse('Actividad no encontrada');
            }

            return $this->successResponse([
                'activity' => new ActivityResource($activity)
            ], 'Actividad obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener actividad: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener actividad: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar una actividad
     *
     * @param ActivityRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(ActivityRequest $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $activity = Activity::find($id);

            if (!$activity) {
                return $this->notFoundResponse('Actividad no encontrada');
            }

            $data = $request->validated();
            $updatedActivity = $this->activityService->update($activity, $data);

            return $this->successResponse([
                'activity' => new ActivityResource($updatedActivity)
            ], 'Actividad actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar actividad: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar actividad: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar una actividad
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $activity = Activity::find($id);

            if (!$activity) {
                return $this->notFoundResponse('Actividad no encontrada');
            }

            $this->activityService->delete($activity);

            return $this->successResponse(null, 'Actividad eliminada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar actividad: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar actividad: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activar/Desactivar actividad
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $activity = Activity::find($id);

            if (!$activity) {
                return $this->notFoundResponse('Actividad no encontrada');
            }

            $status = $request->input('is_active', !$activity->is_active);
            $updatedActivity = $this->activityService->toggleStatus($activity, $status);

            $message = $status ? 'Actividad activada exitosamente' : 'Actividad desactivada exitosamente';

            return $this->successResponse([
                'activity' => new ActivityResource($updatedActivity)
            ], $message);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de actividad: ' . $e->getMessage());
            return $this->errorResponse('Error al cambiar estado de actividad: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener actividades por empresa
     *
     * @param Request $request
     * @param int $companyId
     * @return JsonResponse
     */
    public function byCompany(Request $request, $companyId): JsonResponse
    {
        try {
            $companyId = (int) $companyId;
            $perPage = $request->query('per_page', 15);

            $activities = $this->activityService->getByCompany($companyId, $perPage);

            return $this->successResponse([
                'activities' => ActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                ]
            ], 'Actividades de la empresa obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener actividades por empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener actividades: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener certificados de una actividad
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function certificates(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $activity = Activity::find($id);

            if (!$activity) {
                return $this->notFoundResponse('Actividad no encontrada');
            }

            $perPage = $request->query('per_page', 15);
            $certificates = $this->activityService->getCertificates($activity, $perPage);

            return $this->successResponse([
                'certificates' => $certificates->items()->map(function ($certificate) {
                    return [
                        'id' => $certificate->id,
                        'unique_code' => $certificate->unique_code,
                        'status' => $certificate->status,
                        'issued_at' => $certificate->issued_at,
                        'created_at' => $certificate->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $certificates->currentPage(),
                    'last_page' => $certificates->lastPage(),
                    'per_page' => $certificates->perPage(),
                    'total' => $certificates->total(),
                ]
            ], 'Certificados de la actividad obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener certificados de actividad: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener certificados: ' . $e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Services\CertificateService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CertificateController extends Controller
{
    use ApiResponseTrait;

    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    /**
     * Mostrar todos los certificados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);

            // Si hay criterios de búsqueda, usar el método search
            if ($request->hasAny(['search', 'activity_id', 'company_id', 'status', 'issue_date_from', 'issue_date_to'])) {
                $criteria = $request->only(['search', 'activity_id', 'company_id', 'status', 'issue_date_from', 'issue_date_to']);
                $certificates = $this->certificateService->search($criteria, $perPage);
            } else {
                $certificates = $this->certificateService->getAll($perPage);
            }

            return $this->successResponse([
                'certificates' => CertificateResource::collection($certificates->items()),
                'pagination' => [
                    'current_page' => $certificates->currentPage(),
                    'last_page' => $certificates->lastPage(),
                    'per_page' => $certificates->perPage(),
                    'total' => $certificates->total(),
                ]
            ], 'Certificados obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener certificados: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener certificados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenar un nuevo certificado
     *
     * @param CertificateRequest $request
     * @return JsonResponse
     */
    public function store(CertificateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Establecer estado por defecto si no se proporciona
            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }

            // Crear el certificado
            $certificate = $this->certificateService->create($data);

            // Log de la creación
            if (Auth::check()) {
                $user = Auth::user();
                Log::info('Certificado creado por usuario', [
                    'certificate_id' => $certificate->id,
                    'user_id' => $user->id
                ]);
            }

            return $this->successResponse([
                'certificate' => new CertificateResource($certificate->load(['activity.company']))
            ], 'Certificado creado exitosamente', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear certificado: ' . $e->getMessage());

            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mostrar un certificado específico
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            // Convertir explícitamente a entero
            $id = (int) $id;

            $certificate = $this->certificateService->getById($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            return $this->successResponse([
                'certificate' => new CertificateResource($certificate)
            ], 'Certificado obtenido correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar un certificado
     *
     * @param CertificateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(CertificateRequest $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            $data = $request->validated();
            $updatedCertificate = $this->certificateService->update($certificate, $data);

            return $this->successResponse([
                'certificate' => new CertificateResource($updatedCertificate->load(['activity.company']))
            ], 'Certificado actualizado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar un certificado
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            $this->certificateService->delete($certificate);

            return $this->successResponse(null, 'Certificado eliminado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cambiar estado del certificado
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function changeStatus(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:active,revoked,expired'
            ]);

            $id = (int) $id;
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            $status = $request->input('status');
            $updatedCertificate = $this->certificateService->changeStatus($certificate, $status);

            return $this->successResponse([
                'certificate' => new CertificateResource($updatedCertificate->load(['activity.company']))
            ], 'Estado del certificado actualizado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado del certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al cambiar estado del certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener certificados por actividad
     *
     * @param Request $request
     * @param int $activityId
     * @return JsonResponse
     */
    public function byActivity(Request $request, $activityId): JsonResponse
    {
        try {
            $activityId = (int) $activityId;
            $perPage = $request->query('per_page', 15);

            $certificates = $this->certificateService->getByActivity($activityId, $perPage);

            return $this->successResponse([
                'certificates' => CertificateResource::collection($certificates->items()),
                'pagination' => [
                    'current_page' => $certificates->currentPage(),
                    'last_page' => $certificates->lastPage(),
                    'per_page' => $certificates->perPage(),
                    'total' => $certificates->total(),
                ]
            ], 'Certificados de la actividad obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener certificados por actividad: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener certificados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener certificado por código
     *
     * @param string $code
     * @return JsonResponse
     */
    public function byCode($code): JsonResponse
    {
        try {
            $certificate = $this->certificateService->getByCode($code);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            return $this->successResponse([
                'certificate' => new CertificateResource($certificate)
            ], 'Certificado obtenido correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener certificado por código: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generar documento del certificado
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function generateDocument(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'file_name' => 'required|string|max:255',
                'file_path' => 'required|string|max:500',
                'file_type' => 'required|string|max:50',
                'file_size' => 'required|integer|min:1',
            ]);

            $id = (int) $id;
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            $documentData = $request->only(['file_name', 'file_path', 'file_type', 'file_size']);
            $document = $this->certificateService->generateDocument($certificate, $documentData);

            return $this->successResponse([
                'document' => [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'file_type' => $document->file_type,
                    'file_size' => $document->file_size,
                    'created_at' => $document->created_at,
                ]
            ], 'Documento del certificado generado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al generar documento del certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al generar documento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas de certificados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['company_id', 'activity_id']);
            $statistics = $this->certificateService->getStatistics($filters);

            return $this->successResponse([
                'statistics' => $statistics
            ], 'Estadísticas obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de certificados: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }
}

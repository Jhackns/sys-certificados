<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\CertificateTemplateService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CertificateTemplateController extends Controller
{
    use ApiResponseTrait;

    protected $templateService;

    public function __construct(CertificateTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Mostrar todas las plantillas
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
                $templates = $this->templateService->search($criteria, $perPage);
            } else {
                $templates = $this->templateService->getAll($perPage);
            }

            return $this->successResponse([
                'templates' => $templates->items()->map(function ($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'is_active' => $template->is_active,
                        'company_id' => $template->company_id,
                        'certificates_count' => $template->certificates_count,
                        'company' => $template->company ? [
                            'id' => $template->company->id,
                            'name' => $template->company->name,
                            'ruc' => $template->company->ruc,
                        ] : null,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ]
            ], 'Plantillas obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener plantillas: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener plantillas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenar una nueva plantilla
     *
     * @param CertificateTemplateRequest $request
     * @return JsonResponse
     */
    public function store(CertificateTemplateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Crear la plantilla
            $template = $this->templateService->create($data);
            
            // Log de la creación
            if (Auth::check()) {
                $user = Auth::user();
                Log::info('Plantilla creada por usuario', [
                    'template_id' => $template->id,
                    'user_id' => $user->id
                ]);
            }
            
            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'template_content' => $template->template_content,
                    'template_styles' => $template->template_styles,
                    'is_active' => $template->is_active,
                    'company_id' => $template->company_id,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ]
            ], 'Plantilla creada exitosamente', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al crear plantilla: ' . $e->getMessage());
            
            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mostrar una plantilla específica
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $template = $this->templateService->getById($id);
        
            if (!$template) {
                return $this->notFoundResponse('Plantilla no encontrada');
            }

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'template_content' => $template->template_content,
                    'template_styles' => $template->template_styles,
                    'is_active' => $template->is_active,
                    'company_id' => $template->company_id,
                    'certificates_count' => $template->certificates_count,
                    'company' => $template->company ? [
                        'id' => $template->company->id,
                        'name' => $template->company->name,
                        'ruc' => $template->company->ruc,
                    ] : null,
                    'certificates' => $template->certificates->map(function ($certificate) {
                        return [
                            'id' => $certificate->id,
                            'certificate_code' => $certificate->certificate_code,
                            'participant_name' => $certificate->participant_name,
                            'participant_email' => $certificate->participant_email,
                            'status' => $certificate->status,
                        ];
                    }),
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ]
            ], 'Plantilla obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener plantilla: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar una plantilla
     *
     * @param CertificateTemplateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(CertificateTemplateRequest $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $template = CertificateTemplate::find($id);

            if (!$template) {
                return $this->notFoundResponse('Plantilla no encontrada');
            }

            $data = $request->validated();
            $updatedTemplate = $this->templateService->update($template, $data);

            return $this->successResponse([
                'template' => [
                    'id' => $updatedTemplate->id,
                    'name' => $updatedTemplate->name,
                    'description' => $updatedTemplate->description,
                    'template_content' => $updatedTemplate->template_content,
                    'template_styles' => $updatedTemplate->template_styles,
                    'is_active' => $updatedTemplate->is_active,
                    'company_id' => $updatedTemplate->company_id,
                    'created_at' => $updatedTemplate->created_at,
                    'updated_at' => $updatedTemplate->updated_at,
                ]
            ], 'Plantilla actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar plantilla: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar una plantilla
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $template = CertificateTemplate::find($id);

            if (!$template) {
                return $this->notFoundResponse('Plantilla no encontrada');
            }

            $this->templateService->delete($template);

            return $this->successResponse(null, 'Plantilla eliminada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar plantilla: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activar/Desactivar plantilla
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $template = CertificateTemplate::find($id);

            if (!$template) {
                return $this->notFoundResponse('Plantilla no encontrada');
            }

            $status = $request->input('is_active', !$template->is_active);
            $updatedTemplate = $this->templateService->toggleStatus($template, $status);

            $message = $status ? 'Plantilla activada exitosamente' : 'Plantilla desactivada exitosamente';

            return $this->successResponse([
                'template' => [
                    'id' => $updatedTemplate->id,
                    'name' => $updatedTemplate->name,
                    'is_active' => $updatedTemplate->is_active,
                ]
            ], $message);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al cambiar estado de plantilla: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener plantillas por empresa
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
            
            $templates = $this->templateService->getByCompany($companyId, $perPage);

            return $this->successResponse([
                'templates' => $templates->items()->map(function ($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'is_active' => $template->is_active,
                        'certificates_count' => $template->certificates_count,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ]
            ], 'Plantillas de la empresa obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener plantillas por empresa: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener plantillas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clonar una plantilla
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function clone(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $id = (int) $id;
            $template = CertificateTemplate::find($id);

            if (!$template) {
                return $this->notFoundResponse('Plantilla no encontrada');
            }

            $newData = $request->only(['name', 'description']);
            $clonedTemplate = $this->templateService->clone($template, $newData);

            return $this->successResponse([
                'template' => [
                    'id' => $clonedTemplate->id,
                    'name' => $clonedTemplate->name,
                    'description' => $clonedTemplate->description,
                    'template_content' => $clonedTemplate->template_content,
                    'template_styles' => $clonedTemplate->template_styles,
                    'is_active' => $clonedTemplate->is_active,
                    'company_id' => $clonedTemplate->company_id,
                    'created_at' => $clonedTemplate->created_at,
                    'updated_at' => $clonedTemplate->updated_at,
                ]
            ], 'Plantilla clonada exitosamente', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error al clonar plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al clonar plantilla: ' . $e->getMessage(), 500);
        }
    }
}
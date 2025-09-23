<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\CertificateTemplateService;
use App\Services\CertificateFileService;
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
    protected $fileService;

    public function __construct(CertificateTemplateService $templateService, CertificateFileService $fileService)
    {
        $this->templateService = $templateService;
        $this->fileService = $fileService;
    }

    /**
     * Obtener lista simple de plantillas para dropdowns
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $templates = CertificateTemplate::select('id', 'name', 'description')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return $this->successResponse([
                'templates' => $templates
            ], 'Lista de plantillas obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error en CertificateTemplateController@list: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener lista de plantillas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener vista previa de una plantilla
     *
     * @param int $id
     * @return JsonResponse
     */
    public function preview(int $id): JsonResponse
    {
        try {
            $template = CertificateTemplate::with(['certificates'])->findOrFail($id);

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                    'html_content' => $template->html_content,
                    'css_styles' => $template->template_styles,
                ]
            ], 'Vista previa de plantilla obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error en CertificateTemplateController@preview: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener vista previa de plantilla: ' . $e->getMessage(), 500);
        }
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
            if ($request->hasAny(['search', 'activity_type', 'status', 'is_active'])) {
                $criteria = $request->only(['search', 'activity_type', 'status', 'is_active']);
                $templates = $this->templateService->search($criteria, $perPage);
            } else {
                $templates = $this->templateService->getAll($perPage);
            }

            return $this->successResponse([
                'templates' => collect($templates->items())->map(function ($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'file_path' => $template->file_path,
                        'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                        'activity_type' => $template->activity_type,
                        'status' => $template->status,
                        'is_active' => $template->is_active,
                        'certificates_count' => $template->certificates_count,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ];
                })->toArray(),
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
            // Log para debugging
            Log::info('Request data received:', $request->all());
            Log::info('Request files:', $request->allFiles());
            
            $data = $request->validated();

            // Manejar la subida de archivo si existe
            if ($request->hasFile('template_file')) {
                $file = $request->file('template_file');
                $templateName = $data['name'] ?? 'template';
                $filePath = $this->fileService->uploadGlobalTemplate($file, $templateName);
                $data['file_path'] = $filePath;
            }

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
                    'file_path' => $template->file_path,
                    'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                    'template_content' => $template->template_content,
                    'template_styles' => $template->template_styles,
                    'is_active' => $template->is_active,
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
                    'certificates_count' => $template->certificates_count,
                    'certificates' => $template->certificates->map(function ($certificate) {
                        return [
                            'id' => $certificate->id,
                            'certificate_code' => $certificate->unique_code,
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

            // Log para debugging - INICIO DE PETICIÓN
            Log::info('=== INICIO UPDATE TEMPLATE ===');
            Log::info('Template ID:', ['id' => $id]);
            Log::info('Template encontrada:', [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'activity_type' => $template->activity_type,
                'status' => $template->status
            ]);
            Log::info('Update request method:', ['method' => $request->method()]);
            Log::info('Update request content type:', ['content_type' => $request->header('Content-Type')]);
            Log::info('Update request raw input:', ['raw_input' => $request->getContent()]);
            Log::info('Update request data:', ['data' => $request->all()]);
            Log::info('Update request files:', ['files' => $request->allFiles()]);

            $data = $request->validated();
            Log::info('Validated data for update:', $data);

            // Manejar la subida de archivo si existe
            if ($request->hasFile('template_file')) {
                $file = $request->file('template_file');
                $templateName = $data['name'] ?? $template->name ?? 'template';
                $filePath = $this->fileService->uploadGlobalTemplate($file, $templateName);
                $data['file_path'] = $filePath;
                Log::info('Archivo subido:', ['file_path' => $filePath]);
            }

            Log::info('Datos antes de actualizar:', $data);
            $updatedTemplate = $this->templateService->update($template, $data);
            Log::info('Template actualizada en servicio');

            // Verificar que los datos se guardaron
            $freshTemplate = CertificateTemplate::find($id);
            Log::info('Template después de actualizar (fresh):', [
                'id' => $freshTemplate->id,
                'name' => $freshTemplate->name,
                'description' => $freshTemplate->description,
                'activity_type' => $freshTemplate->activity_type,
                'status' => $freshTemplate->status
            ]);

            return $this->successResponse([
                'template' => [
                    'id' => $updatedTemplate->id,
                    'name' => $updatedTemplate->name,
                    'description' => $updatedTemplate->description,
                    'file_path' => $updatedTemplate->file_path,
                    'file_url' => $updatedTemplate->file_path ? $this->fileService->getPublicUrl($updatedTemplate->file_path) : null,
                    'template_content' => $updatedTemplate->template_content,
                    'template_styles' => $updatedTemplate->template_styles,
                    'activity_type' => $updatedTemplate->activity_type,
                    'status' => $updatedTemplate->status,
                    'is_active' => $updatedTemplate->is_active,
                    'created_at' => $updatedTemplate->created_at,
                    'updated_at' => $updatedTemplate->updated_at,
                ]
            ], 'Plantilla actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar plantilla: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
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

            $currentStatus = $template->status === 'active';
            $status = $request->input('status') === 'active' ? true : !$currentStatus;
            $updatedTemplate = $this->templateService->toggleStatus($template, $status);

            $message = $status ? 'Plantilla activada exitosamente' : 'Plantilla desactivada exitosamente';

            return $this->successResponse([
                'template' => [
                    'id' => $updatedTemplate->id,
                    'name' => $updatedTemplate->name,
                    'status' => $updatedTemplate->status,
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
                'templates' => collect($templates->items())->map(function ($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'is_active' => $template->is_active,
                        'certificates_count' => $template->certificates_count,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ];
                })->toArray(),
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

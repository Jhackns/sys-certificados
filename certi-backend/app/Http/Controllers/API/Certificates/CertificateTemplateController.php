<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificateTemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\CertificateTemplateService;
use App\Services\CertificateFileService;
use App\Services\CanvaValidationService;
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
    protected $canvaValidationService;

    public function __construct(
        CertificateTemplateService $templateService,
        CertificateFileService $fileService,
        CanvaValidationService $canvaValidationService
    ) {
        $this->templateService = $templateService;
        $this->fileService = $fileService;
        $this->canvaValidationService = $canvaValidationService;
    }

    /**
     * Listar todas las plantillas de certificados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');
            $activityType = $request->get('activity_type');
            $status = $request->get('status');

            $query = CertificateTemplate::with(['certificates'])
                ->withCount('certificates');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($activityType) {
                $query->where('activity_type', $activityType);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $templates = $query->paginate($perPage);

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
                        'canva_design_id' => $template->canva_design_id,
                        'created_at' => $template->created_at,
                        'updated_at' => $template->updated_at,
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                    'from' => $templates->firstItem(),
                    'to' => $templates->lastItem(),
                ]
            ], 'Plantillas obtenidas exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener plantillas: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener plantillas', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener vista previa de una plantilla
     *
     * @param int $id
     * @return JsonResponse
     */
    public function preview($id): JsonResponse
    {
        try {
            $template = CertificateTemplate::findOrFail($id);

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                    'activity_type' => $template->activity_type,
                    'status' => $template->status,
                    'canva_design_id' => $template->canva_design_id,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ]
            ], 'Vista previa de plantilla obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener vista previa de plantilla: ' . $e->getMessage());
            return $this->notFoundResponse('Plantilla no encontrada');
        }
    }

    /**
     * Crear una nueva plantilla de certificado
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

            // Validar design ID de Canva si se proporciona
            if (!empty($data['canva_design_id'])) {
                $validation = $this->canvaValidationService->validateDesignId($data['canva_design_id']);

                if (!$validation['valid']) {
                    return $this->errorResponse(
                        'Design ID de Canva inválido: ' . $validation['message'],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                Log::info('Design ID de Canva validado exitosamente', [
                    'design_id' => $data['canva_design_id'],
                    'validation_data' => $validation['data'] ?? []
                ]);
            }

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
                    'user_id' => $user->id,
                    'canva_design_id' => $template->canva_design_id ?? null
                ]);

                // Crear el registro en la tabla pivote
                // DB::table('certificate_template_user')->insert([
                //     'template_id' => $template->id,
                //     'user_id' => $user->id,
                //     'canva_design_id' => $template->canva_design_id ?? null
                // ]);

                // El usuario creador se guarda automáticamente en el campo user_id si existe
                // No necesitamos la relación owners por ahora
            }

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                    'activity_type' => $template->activity_type,
                    'status' => $template->status,
                    'canva_design_id' => $template->canva_design_id,
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
            $template = CertificateTemplate::with(['certificates'])
                ->withCount('certificates')
                ->findOrFail($id);

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                    'activity_type' => $template->activity_type,
                    'status' => $template->status,
                    'is_active' => $template->is_active,
                    'certificates_count' => $template->certificates_count,
                    'canva_design_id' => $template->canva_design_id,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                    // Eliminada la información de owners por ahora
                ]
            ], 'Plantilla obtenida exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener plantilla: ' . $e->getMessage());
            return $this->notFoundResponse('Plantilla no encontrada');
        }
    }

    /**
     * Actualizar una plantilla existente
     *
     * @param CertificateTemplateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(CertificateTemplateRequest $request, $id): JsonResponse
    {
        try {
            $template = CertificateTemplate::findOrFail($id);
            $data = $request->validated();

            // Validar design ID de Canva si se proporciona
            if (!empty($data['canva_design_id'])) {
                $validation = $this->canvaValidationService->validateDesignId($data['canva_design_id']);

                if (!$validation['valid']) {
                    return $this->errorResponse(
                        'Design ID de Canva inválido: ' . $validation['message'],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            // Manejar la subida de archivo si existe
            if ($request->hasFile('template_file')) {
                // Eliminar archivo anterior si existe
                if ($template->file_path) {
                    $this->fileService->deleteFile($template->file_path);
                }

                $file = $request->file('template_file');
                $templateName = $data['name'] ?? $template->name;
                $filePath = $this->fileService->uploadGlobalTemplate($file, $templateName);
                $data['file_path'] = $filePath;
            }

            $template = $this->templateService->update($template, $data);

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null,
                    'activity_type' => $template->activity_type,
                    'status' => $template->status,
                    'canva_design_id' => $template->canva_design_id,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ]
            ], 'Plantilla actualizada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar plantilla', Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $template = CertificateTemplate::findOrFail($id);

            // Verificar si la plantilla tiene certificados asociados
            if ($template->certificates()->exists()) {
                return $this->errorResponse(
                    'No se puede eliminar una plantilla que tiene certificados asociados',
                    Response::HTTP_CONFLICT
                );
            }

            // Eliminar archivo si existe
            if ($template->file_path) {
                $this->fileService->deleteFile($template->file_path);
            }

            $this->templateService->delete($template);

            return $this->successResponse([], 'Plantilla eliminada exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar plantilla: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar plantilla', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validar un diseño de Canva
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateCanvaDesign(Request $request): JsonResponse
    {
        try {
            Log::info('Validación de diseño de Canva solicitada', $request->all());

            $request->validate([
                'canva_design_id' => 'required|string'
            ]);

            $designId = $request->input('canva_design_id');
            Log::info('Procesando diseño', ['design_id' => $designId]);

            // Primero intentar extraer el ID del diseño si es una URL
            $extractedId = $this->canvaValidationService->extractDesignIdFromUrl($designId);

            if ($extractedId) {
                Log::info('ID extraído de URL', ['original' => $designId, 'extracted_id' => $extractedId]);
                // Si se extrajo un ID de la URL, usar ese ID
                $validation = $this->canvaValidationService->validateDesignId($extractedId);
            } else {
                Log::info('Usando ID directo', ['design_id' => $designId]);
                // Si no es una URL, asumir que es un ID directo
                $validation = $this->canvaValidationService->validateDesignId($designId);
            }

            Log::info('Resultado de validación', $validation);

            if ($validation['valid']) {
                return $this->successResponse([
                    'valid' => true,
                    'status' => $validation['status'] ?? 'valid',
                    'message' => $validation['message'],
                    'data' => $validation['data'] ?? null
                ], 'Diseño validado exitosamente');
            } else {
                return $this->errorResponse($validation['message'], Response::HTTP_BAD_REQUEST, [
                    'status' => $validation['status'] ?? 'invalid',
                    'details' => $validation['data'] ?? null
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error al validar diseño de Canva: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Error al validar diseño de Canva', Response::HTTP_INTERNAL_SERVER_ERROR, [
                'status' => 'server_error',
                'details' => $e->getMessage()
            ]);
        }
    }
}
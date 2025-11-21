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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CertificateTemplateController extends Controller
{
    use ApiResponseTrait;

    protected $templateService;
    protected $fileService;

    public function __construct(
        CertificateTemplateService $templateService,
        CertificateFileService $fileService,
    ) {
        $this->templateService = $templateService;
        $this->fileService = $fileService;
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
            Log::error('Error al obtener lista de plantillas: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener lista de plantillas', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Listar fuentes disponibles desde storage/app/fonts
     */
    public function fonts(): JsonResponse
    {
        try {
            $dir1 = storage_path('app/fonts');
            $dir2 = storage_path('app/public/fonts');
            $fonts = [];
            foreach ([$dir1, $dir2] as $dir) {
                if (is_dir($dir)) {
                    $files = array_merge(glob($dir . '/*.ttf') ?: [], glob($dir . '/*.otf') ?: []);
                    foreach ($files as $file) {
                        $base = pathinfo($file, PATHINFO_FILENAME);
                        $name = trim(preg_replace('/[_-]+/', ' ', $base));
                        $name = preg_replace('/\s+/', ' ', $name);
                        $fonts[] = $name;
                    }
                }
            }
            sort($fonts, SORT_NATURAL | SORT_FLAG_CASE);
            $fonts = array_values(array_unique($fonts));
            return $this->successResponse([ 'fonts' => $fonts ], 'Fuentes disponibles obtenidas correctamente');
        } catch (\Throwable $e) {
            Log::error('Error al listar fuentes: ' . $e->getMessage());
            return $this->errorResponse('Error al listar fuentes', Response::HTTP_INTERNAL_SERVER_ERROR);
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

            $computedUrl = $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null;
            Log::info('Plantilla::preview', [
                'id' => $template->id,
                'file_path' => $template->file_path,
                'file_url' => $computedUrl,
                'exists_certificates' => $template->file_path ? Storage::disk('certificates')->exists($template->file_path) : false,
                'exists_public' => $template->file_path ? Storage::disk('public')->exists($template->file_path) : false,
            ]);

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $computedUrl,
                    'activity_type' => $template->activity_type,
                    'status' => $template->status,
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

            // Eliminada validación de design ID de Canva

            // Manejar la subida de archivo si existe (guardar en disco público)
            if ($request->hasFile('template_file')) {
                $file = $request->file('template_file');
                $filePath = $file->store('templates', 'public');
                $data['file_path'] = $filePath;
                // Calcular tamaño natural de la imagen
                $abs = storage_path('app/public/' . ltrim($filePath, '/'));
                $size = @getimagesize($abs);
                if ($size) {
                    $data['background_image_size'] = [ 'width' => $size[0], 'height' => $size[1] ];
                }
            }

            // Crear la plantilla
            $template = $this->templateService->create($data);

            // Log de la creación
            if (Auth::check()) {
                $user = Auth::user();
                Log::info('Plantilla creada por usuario', [
                    'template_id' => $template->id,
                    'user_id' => $user->id,
                ]);
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

            $computedUrl = $template->file_path ? $this->fileService->getPublicUrl($template->file_path) : null;
            Log::info('Plantilla::show', [
                'id' => $template->id,
                'file_path' => $template->file_path,
                'file_url' => $computedUrl,
                'exists_certificates' => $template->file_path ? Storage::disk('certificates')->exists($template->file_path) : false,
                'exists_public' => $template->file_path ? Storage::disk('public')->exists($template->file_path) : false,
            ]);

            return $this->successResponse([
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'file_path' => $template->file_path,
                    'file_url' => $computedUrl,
                    'activity_type' => $template->activity_type,
                    'status' => $template->status,
                    'is_active' => $template->is_active,
                    'certificates_count' => $template->certificates_count,
                    'name_position' => $template->name_position,
                    'date_position' => $template->date_position,
                    'qr_position' => $template->qr_position,
                    'template_styles' => $template->template_styles,
                    'background_image_size' => $template->background_image_size,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
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

            // Eliminada validación de design ID de Canva

            // Manejar la subida de archivo si existe (guardar en disco público)
            if ($request->hasFile('template_file')) {
                // Eliminar archivo anterior si existe en disco público
                if ($template->file_path) {
                    Storage::disk('public')->delete($template->file_path);
                }

                $file = $request->file('template_file');
                $filePath = $file->store('templates', 'public');
                $data['file_path'] = $filePath;
                // Calcular tamaño natural de la imagen
                $abs = storage_path('app/public/' . ltrim($filePath, '/'));
                $size = @getimagesize($abs);
                if ($size) {
                    $data['background_image_size'] = [ 'width' => $size[0], 'height' => $size[1] ];
                }
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
                    'name_position' => $template->name_position,
                    'qr_position' => $template->qr_position,
                    'background_image_size' => $template->background_image_size,
                    'template_styles' => $template->template_styles,
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
    // Método de validación de Canva eliminado
}

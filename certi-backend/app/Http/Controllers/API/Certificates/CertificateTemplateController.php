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
    /**
     * Listar fuentes disponibles
     * Escanea public/fonts y storage/app/public/fonts buscando carpetas y archivos.
     */
    /**
     * Proxy para servir fuentes con cabeceras CORS correctas
     */
    public function proxyFont(\Illuminate\Http\Request $request)
    {
        $path = $request->query('path');
        if (!$path) return response()->json(['error' => 'Path required'], 400);

        // Security check: prevent directory traversal
        if (str_contains($path, '..')) return response()->json(['error' => 'Invalid path'], 403);

        $absPath = null;
        
        // 1. Check inside public/fonts
        if (str_starts_with($path, 'fonts/')) {
            $candidate = public_path($path);
            if (str_starts_with(realpath($candidate), realpath(public_path('fonts'))) && file_exists($candidate)) {
                $absPath = $candidate;
            }
        }
        
        // 2. Check inside storage/app/public/fonts (mapped as storage/fonts/...)
        if (!$absPath && str_starts_with($path, 'storage/fonts/')) {
            $realRel = str_replace('storage/fonts/', '', $path);
            $candidate = storage_path('app/public/fonts/' . $realRel);
            // Verify it is inside storage/app/public/fonts
            $baseStoragePos = realpath(storage_path('app/public/fonts'));
            if ($baseStoragePos && str_starts_with(realpath($candidate), $baseStoragePos) && file_exists($candidate)) {
                $absPath = $candidate;
            }
        }

        if ($absPath && file_exists($absPath)) {
            $mimeType = mime_content_type($absPath);
            if (!$mimeType) $mimeType = 'font/ttf'; // Fallback
            
            return response()->file($absPath, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
                'Content-Type' => $mimeType
            ]);
        }

        return response()->json(['error' => 'Font not found'], 404);
    }

    /**
     * Proxy para servir imágenes de plantillas con CORS
     */
    public function proxyImage(\Illuminate\Http\Request $request)
    {
        $path = $request->query('path');
        if (!$path) return response()->json(['error' => 'Path required'], 400);

        // Security: prevent traversal
        if (str_contains($path, '..')) return response()->json(['error' => 'Invalid path'], 403);

        // Normalize path: ignore 'storage/' prefix if sent
        $relPath = $path;
        if (str_starts_with($path, 'storage/')) {
            $relPath = substr($path, 8);
        }

        // Check in public disk (where templates are stored)
        if (Storage::disk('public')->exists($relPath)) {
            $absPath = Storage::disk('public')->path($relPath);
            $mimeType = mime_content_type($absPath);
            
            return response()->file($absPath, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
                'Content-Type' => $mimeType
            ]);
        }
        
        // Check certificates disk
        if (Storage::disk('certificates')->exists($relPath)) {
             $absPath = Storage::disk('certificates')->path($relPath);
             $mimeType = mime_content_type($absPath);
             return response()->file($absPath, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization',
                'Content-Type' => $mimeType
            ]);
        }

        return response()->json(['error' => 'Image not found'], 404);
    }


    public function fonts(): JsonResponse
    {
        try {
            $fontsMap = []; // Key: Name, Value: URL
            
            // Helper para buscar archivo Regular/Normal para la preview
            $findRegularFile = function ($dir) {
                // Patrones prioritarios
                $patterns = [
                    '/*Regular.ttf', '/*-Regular.ttf', '/*_Regular.ttf', 
                    '/*Normal.ttf', '/*-Normal.ttf',
                    '/*.ttf' // Fallback a cualquiera
                ];
                foreach ($patterns as $pattern) {
                    $matches = glob($dir . $pattern);
                    if ($matches) return $matches[0];
                    // Probar carpeta static si existe
                    $matchesStatic = glob($dir . '/static' . $pattern);
                    if ($matchesStatic) return $matchesStatic[0];
                }
                return null;
            };

            // 1. Escanear public/fonts (Prioridad 1)
            $publicFontsDir = public_path('fonts');
            if (is_dir($publicFontsDir)) {
                $subdirs = glob($publicFontsDir . '/*', GLOB_ONLYDIR);
                foreach ($subdirs as $subdir) {
                    $dirname = basename($subdir);
                    $name = trim(str_replace(['_', '-'], ' ', $dirname));
                    if (!$name) continue;

                    // Buscar archivo para URL
                    $file = $findRegularFile($subdir);
                    if ($file) {
                        // Generar URL PROXY
                        $relativePath = str_replace(public_path() . DIRECTORY_SEPARATOR, '', $file);
                        $relativePath = str_replace('\\', '/', $relativePath); // Fix Windows paths
                        // URL: /api/public/fonts/proxy?path=fonts/Unna/Unna-Regular.ttf
                        // Usamos url() helper apuntando a la ruta pública
                        $url = url('api/public/fonts/proxy') . '?path=' . urlencode($relativePath);
                        $fontsMap[$name] = $url;
                    } else {
                        $fontsMap[$name] = null;
                    }
                }
            }

            // 2. Escanear storage/app/public/fonts (Prioridad 2)
            $storagePublicDir = storage_path('app/public/fonts');
            if (is_dir($storagePublicDir)) {
                // Archivos sueltos (.ttf)
                $files = array_merge(glob($storagePublicDir . '/*.ttf') ?: [], glob($storagePublicDir . '/*.otf') ?: []);
                foreach ($files as $file) {
                    $base = pathinfo($file, PATHINFO_FILENAME);
                    $name = trim(preg_replace('/[_-]+/', ' ', $base));
                    $name = preg_replace('/\s*(Regular|Bold|Italic|Medium|Light|Thin|SemiBold|ExtraBold|Black)\s*/i', '', $name);
                    
                    if ($name && !isset($fontsMap[$name])) {
                        $relativePath = 'storage/fonts/' . basename($file);
                        $url = url('api/public/fonts/proxy') . '?path=' . urlencode($relativePath);
                        $fontsMap[$name] = $url; 
                    }
                }
            }

            // Formatear respuesta
            $fontsList = [];
            ksort($fontsMap, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($fontsMap as $name => $url) {
                $fontsList[] = [
                    'name' => $name,
                    'url' => $url
                ];
            }

            return $this->successResponse([ 'fonts' => $fontsList ], 'Fuentes disponibles obtenidas correctamente');
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
                if ($size) {
                    $data['background_image_size'] = [ 'width' => $size[0], 'height' => $size[1] ];
                }
            }
            
            Log::info('Datos preparados para crear plantilla', [
                'name' => $data['name'],
                'template_styles_keys' => isset($data['template_styles']) ? array_keys($data['template_styles']) : [],
                'components' => $data['template_styles']['components'] ?? 'MISSING'
            ]);

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

            // Use the proxy URL instead of the direct asset URL to avoid CORS issues in editor
            $computedUrl = null;
            if ($template->file_path) {
                // Generate proxy URL: /api/public/images/proxy?path=...
                // Using 'images/proxy' to match the method proxyImage
                $computedUrl = url('api/public/images/proxy') . '?path=' . urlencode($template->file_path);
            }

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

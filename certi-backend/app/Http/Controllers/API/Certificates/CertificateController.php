<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Models\Activity;
use App\Services\CertificateService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    use ApiResponseTrait;

    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    private function resolvePdfFont(string $fontFamily, ?string $fontWeight, ?string $fontStyle): array
    {
        $familyRaw = strtolower(trim($fontFamily));
        $styleKey = '';
        $fw = strtolower((string)($fontWeight ?? ''));
        $fs = strtolower((string)($fontStyle ?? ''));
        if ($fw === 'bold' || $fw === '700') $styleKey .= 'bold';
        if ($fs === 'italic') $styleKey .= ($styleKey ? 'italic' : 'italic');

        // Core fonts mapping
        $coreMap = [
            'arial' => 'helvetica',
            'helvetica' => 'helvetica',
            'verdana' => 'helvetica',
            'roboto' => 'helvetica',
            'times' => 'times',
            'times new roman' => 'times',
            'courier' => 'courier',
            'dejavusans' => 'dejavusans',
            'dejavuserif' => 'dejavuserif',
        ];
        $core = $coreMap[$familyRaw] ?? null;
        $style = '';
        if ($fw === 'bold' || $fw === '700') $style .= 'B';
        if ($fs === 'italic') $style .= 'I';

        // Try to embed custom TTF
          // Preferir fuentes en storage/fonts
          $key = ($fw === 'bold' || $fw === '700') && ($fs === 'italic') ? 'bolditalic' : (($fw === 'bold' || $fw === '700') ? 'bold' : ($fs === 'italic' ? 'italic' : 'normal'));
          $fname = $familyRaw . ($key === 'normal' ? '' : '-' . $key) . '.ttf';
          $p1 = storage_path('app/fonts/' . $fname);
          $p2 = storage_path('app/public/fonts/' . $fname);
          $p = file_exists($p1) ? $p1 : (file_exists($p2) ? $p2 : null);
          if ($p) {
            try {
              $fontname = \TCPDF_FONTS::addTTFfont($p, 'TrueTypeUnicode', '', 96);
              if ($fontname) {
                return [$fontname, $style];
              }
            } catch (\Throwable $e) {}
          }

          $variants = [
            'arial' => [
                'normal' => 'C:\\Windows\\Fonts\\arial.ttf',
                'bold' => 'C:\\Windows\\Fonts\\arialbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\ariali.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\arialbi.ttf',
            ],
            'comic sans ms' => [
                'normal' => 'C:\\Windows\\Fonts\\comic.ttf',
                'bold' => 'C:\\Windows\\Fonts\\comicbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\comici.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\comicbi.ttf',
            ],
            'courier new' => [
                'normal' => 'C:\\Windows\\Fonts\\cour.ttf',
                'bold' => 'C:\\Windows\\Fonts\\courbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\couri.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\courbi.ttf',
            ],
            'calibri' => [
                'normal' => 'C:\\Windows\\Fonts\\calibri.ttf',
                'bold' => 'C:\\Windows\\Fonts\\calibrib.ttf',
                'italic' => 'C:\\Windows\\Fonts\\calibrii.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\calibriz.ttf',
            ],
            'georgia' => [
                'normal' => 'C:\\Windows\\Fonts\\georgia.ttf',
                'bold' => 'C:\\Windows\\Fonts\\georgiab.ttf',
                'italic' => 'C:\\Windows\\Fonts\\georgiai.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\georgiaz.ttf',
            ],
            'times new roman' => [
                'normal' => 'C:\\Windows\\Fonts\\times.ttf',
                'bold' => 'C:\\Windows\\Fonts\\timesbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\timesi.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\timesbi.ttf',
            ],
            'times' => [
                'normal' => 'C:\\Windows\\Fonts\\times.ttf',
                'bold' => 'C:\\Windows\\Fonts\\timesbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\timesi.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\timesbi.ttf',
            ],
            'courier' => [
                'normal' => 'C:\\Windows\\Fonts\\cour.ttf',
                'bold' => 'C:\\Windows\\Fonts\\courbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\couri.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\courbi.ttf',
            ],
            'verdana' => [
                'normal' => 'C:\\Windows\\Fonts\\verdana.ttf',
                'bold' => 'C:\\Windows\\Fonts\\verdanab.ttf',
                'italic' => 'C:\\Windows\\Fonts\\verdanai.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\verdanaz.ttf',
            ],
        ];
        $key = ($fw === 'bold' || $fw === '700') && ($fs === 'italic') ? 'bolditalic' : (($fw === 'bold' || $fw === '700') ? 'bold' : ($fs === 'italic' ? 'italic' : 'normal'));
        $path = null;
        if (isset($variants[$familyRaw][$key]) && file_exists($variants[$familyRaw][$key])) {
            $path = $variants[$familyRaw][$key];
        }
        if (!$path) {
            // storage/fonts fallback
            $fname = $familyRaw . ($key === 'normal' ? '' : '-' . $key) . '.ttf';
            $p = storage_path('app/fonts/' . $fname);
            if (file_exists($p)) $path = $p;
        }
        if ($path) {
            try {
                $fontname = \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 96);
                if ($fontname) {
                    return [$fontname, $style];
                }
            } catch (\Throwable $e) {
                // fallthrough to core
            }
        }
        return [$core ?? 'helvetica', $style];
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
            Log::info('Iniciando obtención de certificados', [
                'user_id' => Auth::id(),
                'query_params' => $request->query()
            ]);

            $perPage = $request->query('per_page', 15);

            // Si hay criterios de búsqueda, usar el método search
            if ($request->hasAny(['search', 'activity_id', 'template_id', 'user_id', 'status', 'fecha_emision', 'fecha_vencimiento'])) {
                $criteria = $request->only(['search', 'activity_id', 'template_id', 'user_id', 'status', 'fecha_emision', 'fecha_vencimiento']);
                Log::info('Usando búsqueda con criterios', ['criteria' => $criteria]);
                $certificates = $this->certificateService->search($criteria, $perPage);
            } else {
                Log::info('Obteniendo todos los certificados');
                $certificates = $this->certificateService->getAll($perPage);
            }

            Log::info('Certificados obtenidos', [
                'total' => $certificates->total(),
                'current_page' => $certificates->currentPage(),
                'items_count' => count($certificates->items())
            ]);

            // Verificar si hay certificados nulos
            $nullCertificates = collect($certificates->items())->filter(function ($cert) {
                return is_null($cert);
            });

            if ($nullCertificates->count() > 0) {
                Log::error('Se encontraron certificados nulos', [
                    'null_count' => $nullCertificates->count(),
                    'total_items' => count($certificates->items())
                ]);
            }

            // Verificar certificados con relaciones nulas
            foreach ($certificates->items() as $index => $certificate) {
                if ($certificate) {
                    Log::debug("Certificado {$index}", [
                        'id' => $certificate->id,
                        'user_loaded' => $certificate->relationLoaded('user'),
                        'user_exists' => $certificate->user ? true : false,
                        'activity_loaded' => $certificate->relationLoaded('activity'),
                        'activity_exists' => $certificate->activity ? true : false,
                        'template_loaded' => $certificate->relationLoaded('template'),
                        'template_exists' => $certificate->template ? true : false,
                        'signer_loaded' => $certificate->relationLoaded('signer'),
                        'signer_exists' => $certificate->signer ? true : false,
                    ]);
                }
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
            Log::error('Error al obtener certificados', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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
            Log::info('=== INICIO STORE CERTIFICADO ===');
            Log::info('Request recibida', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'raw_input' => $request->all()
            ]);

            $data = $request->validated();
            Log::info('Datos validados correctamente', ['validated_data' => $data]);

            // Establecer estado por defecto si no se proporciona
            if (!isset($data['status'])) {
                $data['status'] = 'issued';
                Log::info('Estado establecido por defecto', ['status' => 'issued']);
            }

            Log::info('Llamando a certificateService->create()');
            // Crear el certificado
            $certificate = $this->certificateService->create($data);
            Log::info('certificateService->create() completado', [
                'certificate_returned' => $certificate ? 'SÍ' : 'NO',
                'certificate_id' => $certificate ? $certificate->id : 'NULL'
            ]);

            // Log de la creación
            if (Auth::check()) {
                $user = Auth::user();
                Log::info('Certificado creado por usuario autenticado', [
                    'certificate_id' => $certificate->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
            } else {
                Log::info('Certificado creado sin usuario autenticado');
            }

            Log::info('Cargando relaciones del certificado');
            $certificateWithRelations = $certificate->load(['user', 'activity', 'template', 'signer']);
            Log::info('Relaciones cargadas', [
                'user_loaded' => $certificateWithRelations->user ? 'SÍ' : 'NO',
                'activity_loaded' => $certificateWithRelations->activity ? 'SÍ' : 'NO',
                'template_loaded' => $certificateWithRelations->template ? 'SÍ' : 'NO',
                'signer_loaded' => $certificateWithRelations->signer ? 'SÍ' : 'NO'
            ]);

            Log::info('Creando CertificateResource');
            $resource = new CertificateResource($certificateWithRelations);
            Log::info('CertificateResource creado exitosamente');

            Log::info('=== FIN STORE CERTIFICADO EXITOSO ===');
            return $this->successResponse([
                'certificate' => $resource
            ], 'Certificado creado exitosamente', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('=== ERROR EN STORE CERTIFICADO ===', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Pre-check de creación de certificado: valida datos clave antes de enviar al store
     */
    public function precheck(Request $request): JsonResponse
    {
        try {
            $data = $request->only([
                'user_id', 'id_template', 'nombre', 'descripcion', 'fecha_emision', 'fecha_vencimiento', 'activity_id', 'signed_by', 'status'
            ]);

            $errors = [];

            // Validaciones de presencia
            foreach (['user_id','id_template','nombre','fecha_emision','activity_id'] as $field) {
                if (empty($data[$field])) {
                    $errors[] = "Falta el campo requerido: {$field}";
                }
            }

            // Validación de fechas
            if (!empty($data['fecha_emision']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['fecha_emision'])) {
                $errors[] = 'fecha_emision debe estar en formato YYYY-MM-DD';
            }
            if (!empty($data['fecha_vencimiento'])) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['fecha_vencimiento'])) {
                    $errors[] = 'fecha_vencimiento debe estar en formato YYYY-MM-DD';
                } else if (!empty($data['fecha_emision']) && strtotime($data['fecha_vencimiento']) <= strtotime($data['fecha_emision'])) {
                    $errors[] = 'fecha_vencimiento debe ser posterior a fecha_emision';
                }
            }

            // Validación de existencia de entidades
            if (!empty($data['user_id']) && !User::find($data['user_id'])) {
                $errors[] = 'Usuario no existe';
            }
            if (!empty($data['activity_id']) && !Activity::find($data['activity_id'])) {
                $errors[] = 'Actividad no existe';
            }
            $template = null;
            if (!empty($data['id_template'])) {
                $template = CertificateTemplate::find($data['id_template']);
                if (!$template) {
                    $errors[] = 'Plantilla no existe';
                } else if (method_exists($template, 'getAttribute') && $template->getAttribute('status') === 'inactive') {
                    $errors[] = 'La plantilla está inactiva';
                }
                // Verificar imagen base disponible
                if ($template && empty($template->file_path) && empty($template->file_url)) {
                    $errors[] = 'La plantilla no tiene imagen de fondo configurada';
                }
            }

            $ready = count($errors) === 0;

            return $this->successResponse([
                'ready' => $ready,
                'details' => [
                    'errors' => $errors,
                    'template' => $template ? [
                        'id' => $template->id,
                        'has_positions' => !!($template->qr_position || $template->name_position || $template->date_position),
                        'status' => $template->status ?? null
                    ] : null
                ]
            ], $ready ? 'Pre-check OK' : 'Pre-check con observaciones');
        } catch (\Exception $e) {
            Log::error('Error en precheck de certificado', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->errorResponse('Error en pre-check: ' . $e->getMessage(), 500);
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
                'certificate' => new CertificateResource($updatedCertificate->load(['user', 'activity', 'template', 'signer']))
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
                'certificate' => new CertificateResource($updatedCertificate->load(['user', 'activity', 'template', 'signer']))
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
            $filters = $request->only(['activity_id']);
            $statistics = $this->certificateService->getStatistics($filters);

            return $this->successResponse([
                'statistics' => $statistics
            ], 'Estadísticas obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de certificados: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Descargar certificado
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, $id)
    {
        try {
            $id = (int) $id;
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            $format = $request->get('format', 'pdf'); // Por defecto PDF

            // Si el certificado ya tiene un documento PDF generado (Canva o local), descargarlo directamente
            $document = CertificateDocument::where('certificate_id', $certificate->id)
                ->where('document_type', 'pdf')
                ->orderByDesc('uploaded_at')
                ->first();
            if ($document && Storage::exists($document->file_path)) {
                $content = Storage::get($document->file_path);
                $filename = 'certificado-' . $certificate->id . '.pdf';
                return response($content)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }

            // Fallback: usar imagen final generada si está disponible
            if ($certificate->final_image_path && Storage::disk('public')->exists($certificate->final_image_path)) {
                $finalImageAbsolute = storage_path('app/public/' . $certificate->final_image_path);
                if ($format === 'pdf') {
                    // Empaquetar la imagen final en un PDF ajustando orientación y tamaño según la imagen
                    $imgSize = @getimagesize($finalImageAbsolute);
                    $imgW = $imgSize ? $imgSize[0] : 2100; // px
                    $imgH = $imgSize ? $imgSize[1] : 1480; // px
                    $orientation = ($imgW >= $imgH) ? 'L' : 'P';
                    $dpi = 96; // suposición razonable; si hay metadata DPI, podría leerse
                    $widthMm = $imgW * 25.4 / $dpi;
                    $heightMm = $imgH * 25.4 / $dpi;
                    $pdf = new \TCPDF($orientation, 'mm', [$widthMm, $heightMm]);
                    // Sin márgenes ni saltos para evitar borde blanco
                    $pdf->SetMargins(0, 0, 0);
                    $pdf->SetAutoPageBreak(false, 0);
                    $pdf->AddPage();
                    $pdf->Image($finalImageAbsolute, 0, 0, $widthMm, $heightMm, '', '', '', false, 300, '', false, false, 0);
                    $filename = 'certificado-' . $certificate->id . '.pdf';
                    return response($pdf->Output($filename, 'S'))
                        ->header('Content-Type', 'application/pdf')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                } else {
                    // Descargar la imagen final tal cual
                    $content = file_get_contents($finalImageAbsolute);
                    $filename = 'certificado-' . $certificate->id . '.jpg';
                    return response($content)
                        ->header('Content-Type', 'image/jpeg')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                }
            }

            // Obtener la plantilla del certificado
            $template = $certificate->template;
            if (!$template) {
                return $this->errorResponse('Plantilla del certificado no encontrada', 404);
            }

            // Ruta del archivo de la plantilla (imagen/pdf base)
            $templatePath = $template->file_path ? storage_path('app/' . $template->file_path) : null;

            if (!$templatePath || !file_exists($templatePath)) {
                return $this->errorResponse('Archivo de plantilla no encontrado', 404);
            }

            if ($format === 'pdf') {
                return $this->downloadAsPdf($certificate, $templatePath);
            } else {
                return $this->downloadAsImage($certificate, $templatePath);
            }

        } catch (\Exception $e) {
            Log::error('Error al descargar certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al descargar certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Descargar certificado como PDF
     */
    private function downloadAsPdf($certificate, $templatePath)
    {
        // Crear PDF ajustando orientación y tamaño al de la imagen (plantilla o imagen final)
        $imgSize = @getimagesize($templatePath);
        $imgW = $imgSize ? $imgSize[0] : 2100; // px
        $imgH = $imgSize ? $imgSize[1] : 2970; // px
        $orientation = ($imgW >= $imgH) ? 'L' : 'P';
        $dpi = 96; // suposición razonable
        $widthMm = $imgW * 25.4 / $dpi;
        $heightMm = $imgH * 25.4 / $dpi;

        $pdf = new \TCPDF($orientation, 'mm', [$widthMm, $heightMm]);
        // Eliminar márgenes y saltos automáticos para evitar borde blanco
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Agregar la imagen de fondo a tamaño completo de página
        $pdf->Image($templatePath, 0, 0, $widthMm, $heightMm, '', '', '', false, 300, '', false, false, 0);

        // Overlays desde plantilla si existen (nombre del usuario, fecha, QR), escalados
        $template = $certificate->template;
        if ($template) {
            $editorCanvas = is_array($template->template_styles ?? null) ? ($template->template_styles['editor_canvas_size'] ?? null) : null;
            $bgSize = is_array($template->background_image_size ?? null) ? $template->background_image_size : null;
            $bgW = (int)($bgSize['width'] ?? 0);
            $bgH = (int)($bgSize['height'] ?? 0);
            $editorW = (int)($editorCanvas['width'] ?? 0);
            $editorH = (int)($editorCanvas['height'] ?? 0);
            $baseW = $editorW ?: ($bgW ?: $imgW);
            $baseH = $editorH ?: ($bgH ?: $imgH);
            $scaleX = $baseW > 0 ? ($imgW / $baseW) : 1.0;
            $scaleY = $baseH > 0 ? ($imgH / $baseH) : 1.0;
            $offsetX = (int)($template->template_styles['background_offset']['x'] ?? 0);
            $offsetY = (int)($template->template_styles['background_offset']['y'] ?? 0);
            $oxMm = ($baseW > 0) ? (($offsetX / $baseW) * $widthMm) : 0;
            $oyMm = ($baseH > 0) ? (($offsetY / $baseH) * $heightMm) : 0;
            $origin = strtolower((string)($template->template_styles['coords_origin'] ?? ''));
            $isCenter = $origin === 'center';
            $centerX = $baseW / 2.0;
            $centerY = $baseH / 2.0;
            Log::info('PDF overlay contexto', [
                'certificate_id' => $certificate->id,
                'template_id' => $template->id ?? null,
                'img_px' => ['w' => $imgW, 'h' => $imgH],
                'editor_canvas' => $editorCanvas,
                'background_image_size' => $template->background_image_size,
                'scale' => ['x' => $scaleX, 'y' => $scaleY],
                'background_offset_px' => ['x' => $offsetX, 'y' => $offsetY],
                'offset_mm' => ['x' => $oxMm, 'y' => $oyMm]
            ]);

            // Nombre del usuario
            if (is_array($template->name_position)) {
                $pos = $template->name_position;
                $rawX = ($pos['x'] ?? 0) + ($isCenter ? $centerX : 0);
                $rawY = ($pos['y'] ?? 0) + ($isCenter ? $centerY : 0);
                $xMm = ($baseW > 0) ? (($rawX / $baseW) * $widthMm) - $oxMm : 0;
                $yMm = ($baseH > 0) ? (($rawY / $baseH) * $heightMm) - $oyMm : 0;
                $fontSizePt = max(8, (int)round(((($pos['fontSize'] ?? 28) / $baseH) * $heightMm) * 2.83465));
                $hex = ltrim(($pos['color'] ?? '#000000'), '#');
                $rgb = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
                $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
                $requestedFont = (string)($pos['fontFamily'] ?? 'helvetica');
                [$pdfFont, $style] = $this->resolvePdfFont($requestedFont, (string)($pos['fontWeight'] ?? ''), (string)($pos['fontStyle'] ?? ''));
                $pdf->SetFont($pdfFont, $style, $fontSizePt);
                $holderName = $certificate->user ? ($certificate->user->name ?? $certificate->nombre) : $certificate->nombre;
                $rotation = (float)($pos['rotation'] ?? 0);
                $align = strtolower((string)($pos['textAlign'] ?? 'center'));
                $textWidthMm = $pdf->GetStringWidth($holderName);
                $xMmExpected = $xMm;
                if ($align === 'center') {
                    $xMm = $xMm - ($textWidthMm / 2);
                } elseif ($align === 'right') {
                    $xMm = $xMm - $textWidthMm;
                }
                $deltaMm = abs($xMm - $xMmExpected);
                if ($deltaMm > 0.5) {
                    Log::warning('PDF desalineación respecto a plantilla (nombre)', [
                        'expected_center_mm' => ['x' => $xMmExpected, 'y' => $yMm],
                        'drawn_left_mm' => ['x' => $xMm, 'y' => $yMm],
                        'delta_mm' => $deltaMm,
                        'text_width_mm' => $textWidthMm,
                        'align' => $align,
                        'font_requested' => $requestedFont,
                        'font_applied' => $pdfFont,
                        'font_style' => $style,
                        'font_size_pt' => $fontSizePt
                    ]);
                }
                Log::info('PDF overlay nombre', [
                    'raw_pos_px' => $pos,
                    'computed_mm' => ['x' => $xMm, 'y' => $yMm],
                    'font_map' => ['px' => ($pos['fontSize'] ?? 28), 'mm_height' => $heightMm, 'pt' => $fontSizePt],
                    'color_rgb' => $rgb,
                    'align' => $align,
                    'rotation' => $rotation,
                    'font_requested' => $requestedFont,
                    'font_applied' => $pdfFont,
                    'font_style' => $style,
                    'text' => $holderName
                ]);
                if (abs($rotation) > 0.01) {
                    $pdf->StartTransform();
                    $pdf->Rotate($rotation, $xMm, $yMm);
                    $pdf->Text($xMm, $yMm, $holderName);
                    $pdf->StopTransform();
                } else {
                    $pdf->Text($xMm, $yMm, $holderName);
                }
            }

            // Fecha
            if (is_array($template->date_position)) {
                $pos = $template->date_position;
                $rawX = ($pos['x'] ?? 0) + ($isCenter ? $centerX : 0);
                $rawY = ($pos['y'] ?? 0) + ($isCenter ? $centerY : 0);
                $xMm = ($baseW > 0) ? (($rawX / $baseW) * $widthMm) - $oxMm : 0;
                $yMm = ($baseH > 0) ? (($rawY / $baseH) * $heightMm) - $oyMm : 0;
                $fontSizePt = max(8, (int)round(((($pos['fontSize'] ?? 16) / $baseH) * $heightMm) * 2.83465));
                $hex = ltrim(($pos['color'] ?? '#333333'), '#');
                $rgb = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
                $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
                $requestedFont = (string)($pos['fontFamily'] ?? 'helvetica');
                [$pdfFont, $style] = $this->resolvePdfFont($requestedFont, (string)($pos['fontWeight'] ?? ''), (string)($pos['fontStyle'] ?? ''));
                $pdf->SetFont($pdfFont, $style, $fontSizePt);
                $dateText = $certificate->fecha_emision ? $certificate->fecha_emision->format('d/m/Y') : date('d/m/Y');
                $rotation = (float)($pos['rotation'] ?? 0);
                $align = strtolower((string)($pos['textAlign'] ?? 'center'));
                $textWidthMm = $pdf->GetStringWidth($dateText);
                $xMmExpected = $xMm;
                if ($align === 'center') {
                    $xMm = $xMm - ($textWidthMm / 2);
                } elseif ($align === 'right') {
                    $xMm = $xMm - $textWidthMm;
                }
                $deltaMm = abs($xMm - $xMmExpected);
                if ($deltaMm > 0.5) {
                    Log::warning('PDF desalineación respecto a plantilla (fecha)', [
                        'expected_center_mm' => ['x' => $xMmExpected, 'y' => $yMm],
                        'drawn_left_mm' => ['x' => $xMm, 'y' => $yMm],
                        'delta_mm' => $deltaMm,
                        'text_width_mm' => $textWidthMm,
                        'align' => $align,
                        'font_requested' => $requestedFont,
                        'font_applied' => $pdfFont,
                        'font_style' => $style,
                        'font_size_pt' => $fontSizePt
                    ]);
                }
                Log::info('PDF overlay fecha', [
                    'raw_pos_px' => $pos,
                    'computed_mm' => ['x' => $xMm, 'y' => $yMm],
                    'font_map' => ['px' => ($pos['fontSize'] ?? 16), 'mm_height' => $heightMm, 'pt' => $fontSizePt],
                    'color_rgb' => $rgb,
                    'align' => $align,
                    'rotation' => $rotation,
                    'font_requested' => $requestedFont,
                    'font_applied' => $pdfFont,
                    'font_style' => $style,
                    'text' => $dateText
                ]);
                if (abs($rotation) > 0.01) {
                    $pdf->StartTransform();
                    $pdf->Rotate($rotation, $xMm, $yMm);
                    $pdf->Text($xMm, $yMm, $dateText);
                    $pdf->StopTransform();
                } else {
                    $pdf->Text($xMm, $yMm, $dateText);
                }
            }

            // QR (generado por TCPDF si falla imagen)
            if (is_array($template->qr_position)) {
                $pos = $template->qr_position;
                $rawX = ($pos['x'] ?? 0) + ($isCenter ? $centerX : 0);
                $rawY = ($pos['y'] ?? 0) + ($isCenter ? $centerY : 0);
                $xMm = ($baseW > 0) ? (($rawX / $baseW) * $widthMm) - $oxMm : 0;
                $yMm = ($baseH > 0) ? (($rawY / $baseH) * $heightMm) - $oyMm : 0;
                $wMm = max(25, ($baseW > 0 ? ((($pos['width'] ?? 120) / $baseW) * $widthMm) : 0));
                $hMm = $wMm; // preservar cuadrado
                $rotation = (float)($pos['rotation'] ?? 0);
                $style = [ 'border' => 0, 'padding' => 0, 'fgcolor' => [0,0,0], 'bgcolor' => false ];
                $verificationUrl = $certificate->verification_url ?: (config('app.url') . '/verify/' . ($certificate->verification_code ?? ''));
                Log::info('PDF overlay QR', [
                    'raw_pos_px' => $pos,
                    'computed_mm' => ['x' => $xMm, 'y' => $yMm, 'w' => $wMm, 'h' => $hMm],
                    'url' => $verificationUrl
                ]);
                if (abs($rotation) > 0.01) {
                    $pdf->StartTransform();
                    // Rotar alrededor del centro del bloque QR
                    $pdf->Rotate($rotation, $xMm + ($wMm / 2), $yMm + ($hMm / 2));
                    $pdf->write2DBarcode($verificationUrl, 'QRCODE,H', $xMm, $yMm, $wMm, $hMm, $style, 'N');
                    $pdf->StopTransform();
                } else {
                    $pdf->write2DBarcode($verificationUrl, 'QRCODE,H', $xMm, $yMm, $wMm, $hMm, $style, 'N');
                }
            }
        }

        $filename = 'certificado-' . $certificate->id . '.pdf';

        return response($pdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Descargar certificado como imagen
     */
    private function downloadAsImage($certificate, $templatePath)
    {
        // Crear una copia de la imagen base
        $image = imagecreatefromstring(file_get_contents($templatePath));

        if (!$image) {
            return $this->errorResponse('Error al procesar la imagen', 500);
        }

        // La imagen final generada contiene overlays; si no existe aún, devolvemos la imagen base sin textos de fallback

        $filename = 'certificado-' . $certificate->id . '.jpg';

        ob_start();
        imagejpeg($image, null, 90);
        $imageData = ob_get_contents();
        ob_end_clean();

        imagedestroy($image);

        return response($imageData)
            ->header('Content-Type', 'image/jpeg')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Obtener vista previa del certificado
     *
     * @param int $id
     * @return JsonResponse
     */
    public function preview($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $certificate = Certificate::find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            $template = $certificate->template;
            if (!$template) {
                return $this->errorResponse('Plantilla del certificado no encontrada', 404);
            }

            // Generar URL de vista previa
            // Preferir imagen final generada; si no existe, usar imagen de plantilla pública
            $previewUrl = null;
            if ($certificate->final_image_path && Storage::disk('public')->exists($certificate->final_image_path)) {
                $previewUrl = asset('storage/' . $certificate->final_image_path);
            } elseif ($template->file_path) {
                $previewUrl = asset('storage/' . $template->file_path);
            }

            return $this->successResponse([
                'preview_url' => $previewUrl,
                'certificate' => [
                    'id' => $certificate->id,
                    'nombre' => $certificate->nombre,
                    'user' => $certificate->user ? $certificate->user->name : 'N/A',
                    'activity' => $certificate->activity ? $certificate->activity->name : 'N/A',
                    'fecha_emision' => $certificate->fecha_emision->format('d/m/Y')
                ]
            ], 'Vista previa obtenida correctamente');

        } catch (\Exception $e) {
            Log::error('Error al obtener vista previa: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener vista previa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener certificados del usuario autenticado
     *
     * @return JsonResponse
     */
    public function myCertificates(): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return $this->errorResponse('Usuario no autenticado', 401);
            }

            $certificates = Certificate::with(['activity', 'template', 'signer'])
                ->where('user_id', $userId)
                ->orderBy('fecha_emision', 'desc')
                ->get();

            Log::info('Certificados del usuario obtenidos', [
                'user_id' => $userId,
                'certificates_count' => $certificates->count()
            ]);

            return $this->successResponse($certificates, 'Certificados obtenidos correctamente');

        } catch (\Exception $e) {
            Log::error('Error al obtener certificados del usuario: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener certificados: ' . $e->getMessage(), 500);
        }
    }
}

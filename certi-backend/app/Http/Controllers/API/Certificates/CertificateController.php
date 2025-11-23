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
use Illuminate\Support\Facades\Mail;

use App\Services\CertificateImageService;
use App\Services\QRCodeService; // Necesario para generar el QR si no existe

class CertificateController extends Controller
{
    use ApiResponseTrait;

    protected $certificateService;
    protected $imageService;
    protected $qrService;

    public function __construct(
        CertificateService $certificateService,
        CertificateImageService $imageService,
        QRCodeService $qrService
    ) {
        $this->certificateService = $certificateService;
        $this->imageService = $imageService;
        $this->qrService = $qrService;
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
     * Crear múltiples certificados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'certificates' => 'required|array|min:1',
                'certificates.*.user_id' => 'required|exists:users,id',
                'certificates.*.activity_id' => 'required|exists:activities,id',
                'certificates.*.id_template' => 'required|exists:certificate_templates,id',
                'certificates.*.nombre' => 'required|string',
            ]);

            $data = $request->input('certificates');
            
            // Asegurar campos por defecto
            foreach ($data as &$certData) {
                if (!isset($certData['status'])) {
                    $certData['status'] = 'issued';
                }
            }

            $certificates = $this->certificateService->bulkCreate($data);

            return $this->successResponse([
                'count' => count($certificates),
                'ids' => collect($certificates)->pluck('id')
            ], 'Certificados creados exitosamente en lote', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error en bulkStore: ' . $e->getMessage());
            return $this->errorResponse('Error al crear certificados en lote: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Enviar múltiples correos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkSendEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:certificates,id'
            ]);

            $ids = $request->input('ids');
            $count = 0;

            foreach ($ids as $id) {
                \App\Jobs\SendCertificateEmailJob::dispatch($id);
                $count++;
            }

            return $this->successResponse([
                'queued' => $count
            ], "Se han encolado {$count} correos para envío en segundo plano");

        } catch (\Exception $e) {
            Log::error('Error en bulkSendEmail: ' . $e->getMessage());
            return $this->errorResponse('Error al encolar correos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar múltiples certificados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:certificates,id'
            ]);

            $ids = $request->input('ids');
            $deletedCount = $this->certificateService->bulkDelete($ids);

            return $this->successResponse([
                'deleted' => $deletedCount
            ], "Se han eliminado {$deletedCount} certificados exitosamente");

        } catch (\Exception $e) {
            Log::error('Error en bulkDestroy: ' . $e->getMessage());
            return $this->errorResponse('Error al eliminar certificados en lote: ' . $e->getMessage(), 500);
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

            // 1. Verificar si ya existe una imagen final generada
            $finalImagePath = $certificate->final_image_path;
            $finalImageAbsolute = $finalImagePath ? storage_path('app/public/' . $finalImagePath) : null;

            // 2. Si no existe, intentamos generarla al vuelo
            if (!$finalImageAbsolute || !file_exists($finalImageAbsolute)) {
                Log::info('Imagen final no encontrada, generando al vuelo...', ['certificate_id' => $certificate->id]);
                
                // Necesitamos el QR
                $verificationUrl = url("/verify/{$certificate->unique_code}");
                $qrRelativePath = $this->qrService->generateQRCodeFromUrl($verificationUrl);
                $qrAbsolutePath = $qrRelativePath ? storage_path('app/public/' . $qrRelativePath) : '';

                // Generar imagen
                $finalImagePath = $this->imageService->generateFinalCertificateImage($certificate, $qrAbsolutePath);
                
                if ($finalImagePath) {
                    $certificate->final_image_path = $finalImagePath;
                    $certificate->save();
                    $finalImageAbsolute = storage_path('app/public/' . $finalImagePath);
                } else {
                    return $this->errorResponse('No se pudo generar la imagen del certificado', 500);
                }
            }

            // 3. Servir el archivo según el formato solicitado
            if ($format === 'pdf') {
                // Empaquetar la imagen final en un PDF
                $imgSize = @getimagesize($finalImageAbsolute);
                $imgW = $imgSize ? $imgSize[0] : 2100; // px
                $imgH = $imgSize ? $imgSize[1] : 1480; // px
                
                // Calcular dimensiones en mm (asumiendo 96 DPI para pantalla, pero TCPDF usa 72pt/inch por defecto, ajustamos)
                // TCPDF constructor: $format (page format)
                // Convertir px a mm: (px / dpi) * 25.4
                $dpi = 96; 
                $widthMm = ($imgW * 25.4) / $dpi;
                $heightMm = ($imgH * 25.4) / $dpi;
                
                $orientation = ($widthMm >= $heightMm) ? 'L' : 'P';

                $pdf = new \TCPDF($orientation, 'mm', [$widthMm, $heightMm]);
                $pdf->SetMargins(0, 0, 0);
                $pdf->SetAutoPageBreak(false, 0);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->AddPage();
                
                // Insertar imagen ajustada a la página
                $pdf->Image($finalImageAbsolute, 0, 0, $widthMm, $heightMm, '', '', '', false, 300, '', false, false, 0);
                
                $filename = 'certificado-' . $certificate->unique_code . '.pdf';
                return response($pdf->Output($filename, 'S'))
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

            } else {
                // Descargar imagen directa (JPG/PNG)
                $content = file_get_contents($finalImageAbsolute);
                $mime = mime_content_type($finalImageAbsolute);
                $ext = pathinfo($finalImageAbsolute, PATHINFO_EXTENSION);
                $filename = 'certificado-' . $certificate->unique_code . '.' . $ext;
                
                return response($content)
                    ->header('Content-Type', $mime)
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }

        } catch (\Exception $e) {
            Log::error('Error al descargar certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al descargar certificado: ' . $e->getMessage(), 500);
        }
    }


    /**
     * Enviar certificado por correo electrónico
     */
    public function sendEmail(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;
            $certificate = Certificate::with(['user', 'activity', 'template'])->find($id);

            if (!$certificate) {
                return $this->notFoundResponse('Certificado no encontrado');
            }

            if (!$certificate->user || !$certificate->user->email) {
                return $this->errorResponse('El certificado no tiene un email de destinatario válido', 422);
            }

            $emailTo = $certificate->user->email;
            $fullName = $certificate->user->name ?? ($certificate->nombre ?: 'Usuario');
            $certName = $certificate->nombre ?: ('Certificado #' . $certificate->id);
            $activityName = $certificate->activity ? ($certificate->activity->name ?? '') : '';

            // 1. Verificar si ya existe una imagen final generada
            $finalImagePath = $certificate->final_image_path;
            $finalImageAbsolute = $finalImagePath ? storage_path('app/public/' . $finalImagePath) : null;

            // 2. Si no existe, intentamos generarla al vuelo
            if (!$finalImageAbsolute || !file_exists($finalImageAbsolute)) {
                Log::info('Imagen final no encontrada para email, generando al vuelo...', ['certificate_id' => $certificate->id]);
                
                // Necesitamos el QR
                $verificationUrl = url("/verify/{$certificate->unique_code}");
                $qrRelativePath = $this->qrService->generateQRCodeFromUrl($verificationUrl);
                $qrAbsolutePath = $qrRelativePath ? storage_path('app/public/' . $qrRelativePath) : '';

                // Generar imagen
                $finalImagePath = $this->imageService->generateFinalCertificateImage($certificate, $qrAbsolutePath);
                
                if ($finalImagePath) {
                    $certificate->final_image_path = $finalImagePath;
                    $certificate->save();
                    $finalImageAbsolute = storage_path('app/public/' . $finalImagePath);
                } else {
                    Log::error('No se pudo generar la imagen del certificado para email', ['certificate_id' => $certificate->id]);
                    // Podríamos fallar o intentar enviar sin adjunto, pero mejor fallar para evitar correos rotos
                    return $this->errorResponse('Error al generar el certificado para el envío', 500);
                }
            }

            // 3. Generar PDF envolviendo la imagen
            $imgSize = @getimagesize($finalImageAbsolute);
            $imgW = $imgSize ? $imgSize[0] : 2100;
            $imgH = $imgSize ? $imgSize[1] : 1480;
            
            $dpi = 96;
            $widthMm = ($imgW * 25.4) / $dpi;
            $heightMm = ($imgH * 25.4) / $dpi;
            $orientation = ($widthMm >= $heightMm) ? 'L' : 'P';

            $pdf = new \TCPDF($orientation, 'mm', [$widthMm, $heightMm]);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->Image($finalImageAbsolute, 0, 0, $widthMm, $heightMm, '', '', '', false, 300, '', false, false, 0);
            
            $filename = 'certificado-' . $certificate->unique_code . '.pdf';
            $pdfBytes = $pdf->Output($filename, 'S');

            // HTML del mensaje (simple y con estilos inline)
            $subject = 'Tu certificado: ' . $certName;
            $html = '<div style="font-family: Arial, Helvetica, sans-serif; background:#f7fafc; padding:20px;">'
                . '<div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">'
                . '<div style="padding:20px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff;">'
                . '<h2 style="margin:0;">Certificado emitido</h2>'
                . '</div>'
                . '<div style="padding:20px; color:#1a202c;">'
                . '<p style="font-size:16px;">Hola <strong>' . e($fullName) . '</strong>,</p>'
                . '<p style="font-size:15px;">Adjuntamos tu certificado de <strong>' . e($certName) . '</strong>'
                . ($activityName ? (' del evento <strong>' . e($activityName) . '</strong>') : '')
                . '.</p>'
                . '<p style="font-size:14px; color:#4a5568;">Puedes validar tu certificado en cualquier momento desde el enlace de verificación incluido en el QR.</p>'
                . '<div style="margin-top:20px; padding:16px; background:#f0f4ff; border:1px solid #cfe2ff; border-radius:8px; color:#1a1a1a;">'
                . '<strong>Consejo:</strong> Si no ves este correo en tu bandeja de entrada, revisa la carpeta de spam y marca el remitente como confiable.'
                . '</div>'
                . '</div>'
                . '<div style="padding:16px; text-align:center; color:#718096; font-size:12px;">'
                . 'Sys-Certificados'
                . '</div>'
                . '</div>';

            // Enviar correo
            Mail::html($html, function ($message) use ($emailTo, $subject, $pdfBytes, $filename) {
                $message->to($emailTo)
                    ->subject($subject)
                    ->attachData($pdfBytes, $filename, ['mime' => 'application/pdf']);
            });

            Log::info('Correo de certificado enviado', [
                'certificate_id' => $certificate->id,
                'to' => $emailTo
            ]);

            return $this->successResponse(['sent' => true], 'Correo enviado correctamente');
        } catch (\Throwable $e) {
            Log::error('Error al enviar correo de certificado: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Error al enviar correo: ' . $e->getMessage(), 500);
        }
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

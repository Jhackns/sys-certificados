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

            // Obtener la plantilla del certificado
            $template = $certificate->template;
            if (!$template) {
                return $this->errorResponse('Plantilla del certificado no encontrada', 404);
            }

            // Ruta de la imagen de la plantilla
            $templatePath = storage_path('app/public/' . $template->image_path);
            
            if (!file_exists($templatePath)) {
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
        // Crear PDF usando TCPDF o similar
        $pdf = new \TCPDF();
        $pdf->AddPage();
        
        // Agregar la imagen de fondo
        $pdf->Image($templatePath, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        
        // Agregar texto del certificado
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(50, 100);
        $pdf->Cell(0, 10, $certificate->nombre, 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetXY(50, 120);
        $pdf->Cell(0, 10, 'Usuario: ' . ($certificate->user ? $certificate->user->name : 'N/A'), 0, 1, 'C');
        
        $pdf->SetXY(50, 140);
        $pdf->Cell(0, 10, 'Actividad: ' . ($certificate->activity ? $certificate->activity->name : 'N/A'), 0, 1, 'C');
        
        $pdf->SetXY(50, 160);
        $pdf->Cell(0, 10, 'Fecha: ' . $certificate->fecha_emision->format('d/m/Y'), 0, 1, 'C');

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
        // Crear una copia de la imagen y agregar texto
        $image = imagecreatefromstring(file_get_contents($templatePath));
        
        if (!$image) {
            return $this->errorResponse('Error al procesar la imagen', 500);
        }
        
        // Configurar colores y fuente
        $black = imagecolorallocate($image, 0, 0, 0);
        $font_size = 20;
        
        // Agregar texto al certificado (ajustar posiciones según la plantilla)
        imagestring($image, $font_size, 200, 300, $certificate->nombre, $black);
        imagestring($image, 3, 200, 350, 'Usuario: ' . ($certificate->user ? $certificate->user->name : 'N/A'), $black);
        imagestring($image, 3, 200, 380, 'Actividad: ' . ($certificate->activity ? $certificate->activity->name : 'N/A'), $black);
        imagestring($image, 3, 200, 410, 'Fecha: ' . $certificate->fecha_emision->format('d/m/Y'), $black);
        
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
            $previewUrl = asset('storage/' . $template->image_path);

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
}

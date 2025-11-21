<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCertificateJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CertificateBatchController extends Controller
{
    use ApiResponseTrait;

    /**
     * Generar certificados en lote para múltiples usuarios
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateBatch(Request $request): JsonResponse
    {
        try {
            // Validar la solicitud
            $validator = Validator::make($request->all(), [
                'template_id' => 'required|exists:certificate_templates,id',
                'activity_id' => 'required|exists:activities,id',
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'fecha_emision' => 'required|date',
                'fecha_vencimiento' => 'nullable|date|after:fecha_emision',
                'send_email' => 'boolean',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Obtener la plantilla
            $template = CertificateTemplate::findOrFail($request->template_id);
            

            $data = $request->all();
            $sendEmail = $data['send_email'] ?? false;
            $createdCertificates = [];
            $jobsDispatched = 0;

            // Crear certificados y encolar trabajos para cada usuario
            foreach ($data['user_ids'] as $userId) {
                $user = User::find($userId);
                
                if (!$user) {
                    Log::warning("Usuario no encontrado: {$userId}");
                    continue;
                }

                // Crear el certificado
                $certificate = new Certificate();
                $certificate->user_id = $userId;
                $certificate->id_template = $data['template_id'];
                $certificate->activity_id = $data['activity_id'];
                $certificate->nombre = $user->name;
                $certificate->fecha_emision = $data['fecha_emision'];
                $certificate->fecha_vencimiento = $data['fecha_vencimiento'] ?? null;
                $certificate->status = 'pending';
                $certificate->unique_code = uniqid('cert_');
                $certificate->save();

                // Encolar el trabajo para generar el certificado, después del commit
                GenerateCertificateJob::dispatch($certificate, $sendEmail)->afterCommit();
                $jobsDispatched++;

                $createdCertificates[] = [
                    'id' => $certificate->id,
                    'user_name' => $user->name,
                    'status' => $certificate->status,
                ];
            }

            Log::info('Generación de certificados en lote iniciada', [
                'template_id' => $data['template_id'],
                'activity_id' => $data['activity_id'],
                'user_count' => count($data['user_ids']),
                'jobs_dispatched' => $jobsDispatched,
            ]);

            return $this->successResponse([
                'message' => 'Generación de certificados iniciada en segundo plano',
                'certificates' => $createdCertificates,
                'jobs_dispatched' => $jobsDispatched,
            ], 'La generación de certificados ha comenzado. Recibirás una notificación cuando estén listos.', Response::HTTP_ACCEPTED);
        } catch (\Exception $e) {
            Log::error('Error al generar certificados en lote', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Obtener el estado de los certificados en lote
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBatchStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'certificate_ids' => 'required|array',
                'certificate_ids.*' => 'exists:certificates,id',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $certificates = Certificate::whereIn('id', $request->certificate_ids)
                ->get()
                ->map(function ($certificate) {
                    return [
                        'id' => $certificate->id,
                        'user_name' => $certificate->user->name ?? 'Usuario desconocido',
                        'status' => $certificate->status,
                        'file_url' => $certificate->file_path ? url('api/certificates/' . $certificate->id . '/download') : null,
                        'created_at' => $certificate->created_at,
                        'updated_at' => $certificate->updated_at,
                    ];
                });

            return $this->successResponse([
                'certificates' => $certificates,
                'pending_count' => $certificates->where('status', 'pending')->count(),
                'completed_count' => $certificates->where('status', 'issued')->count(),
                'failed_count' => $certificates->where('status', 'failed')->count(),
            ], 'Estado de certificados obtenido correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener estado de certificados en lote', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Error al procesar la solicitud: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
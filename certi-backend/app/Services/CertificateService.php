<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Services\QRCodeService;
use App\Jobs\GenerateCertificateJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CertificateService
{
    protected $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }
    /**
     * Obtener todos los certificados con paginación
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAll(int $perPage = 15)
    {
        return Certificate::with(['user', 'activity', 'template', 'signer', 'documents', 'validations'])
            ->withCount(['documents', 'validations'])
            ->paginate($perPage);
    }

    /**
     * Obtener certificado por ID
     *
     * @param int $id
     * @return Certificate|null
     */
    public function getById(int $id): ?Certificate
    {
        return Certificate::with(['user', 'activity', 'template', 'signer', 'documents', 'validations'])
            ->withCount(['documents', 'validations'])
            ->find($id);
    }

    /**
     * Crear nuevo certificado
     *
     * @param array $data
     * @return Certificate
     */
    public function create(array $data): Certificate
    {
        return DB::transaction(function () use ($data) {
            Log::info('=== INICIO CREACIÓN CERTIFICADO ===');
            Log::info('Datos recibidos para crear certificado', ['data' => $data]);

            // Verificar que los IDs de relaciones existan
            if (isset($data['user_id'])) {
                $userExists = \App\Models\User::find($data['user_id']);
                Log::info('Verificación user_id', [
                    'user_id' => $data['user_id'],
                    'exists' => $userExists ? 'SÍ' : 'NO',
                    'user_data' => $userExists ? ['id' => $userExists->id, 'name' => $userExists->name] : null
                ]);
                if (!$userExists) {
                    throw new \Exception("Usuario con ID {$data['user_id']} no existe");
                }
            }

            if (isset($data['activity_id'])) {
                $activityExists = \App\Models\Activity::find($data['activity_id']);
                Log::info('Verificación activity_id', [
                    'activity_id' => $data['activity_id'],
                    'exists' => $activityExists ? 'SÍ' : 'NO',
                    'activity_data' => $activityExists ? ['id' => $activityExists->id, 'name' => $activityExists->name] : null
                ]);
                if (!$activityExists) {
                    throw new \Exception("Actividad con ID {$data['activity_id']} no existe");
                }
            }

            if (isset($data['id_template'])) {
                $templateExists = \App\Models\CertificateTemplate::find($data['id_template']);
                Log::info('Verificación id_template', [
                    'id_template' => $data['id_template'],
                    'exists' => $templateExists ? 'SÍ' : 'NO',
                    'template_data' => $templateExists ? ['id' => $templateExists->id, 'name' => $templateExists->name] : null
                ]);
                if (!$templateExists) {
                    throw new \Exception("Plantilla con ID {$data['id_template']} no existe");
                }
            }

            // Generar código único del certificado
            $uniqueCode = $this->generateCertificateCode();
            $data['unique_code'] = $uniqueCode;
            Log::info('Código único generado', ['unique_code' => $uniqueCode]);

            // Establecer fecha de emisión si no se proporciona
            if (!isset($data['fecha_emision'])) {
                $data['fecha_emision'] = now()->format('Y-m-d');
                Log::info('Fecha de emisión establecida automáticamente', ['fecha_emision' => $data['fecha_emision']]);
            }

            // Establecer issued_at si no se proporciona
            if (!isset($data['issued_at'])) {
                $data['issued_at'] = now();
                Log::info('Fecha issued_at establecida automáticamente', ['issued_at' => $data['issued_at']]);
            }

            Log::info('Datos finales antes de crear certificado', ['final_data' => $data]);

            try {
                $certificate = Certificate::create($data);
                Log::info('Certificate::create() ejecutado');

                if ($certificate) {
                    Log::info('Certificado creado exitosamente', [
                        'certificate_id' => $certificate->id,
                        'certificate_data' => [
                            'id' => $certificate->id,
                            'user_id' => $certificate->user_id,
                            'activity_id' => $certificate->activity_id,
                            'id_template' => $certificate->id_template,
                            'unique_code' => $certificate->unique_code,
                            'nombre' => $certificate->nombre,
                            'status' => $certificate->status
                        ]
                    ]);

                    // Generar datos de verificación y QR
                    Log::info('Generando datos de verificación y QR para el certificado');
                    $this->generateVerificationData($certificate);
                    Log::info('Datos de verificación y QR generados exitosamente');

                    // Despachar el trabajo para generar imagen/documento del certificado
                    Log::info('Despachando trabajo para generar certificado en segundo plano');
                    GenerateCertificateJob::dispatch($certificate, false)->afterCommit(); // false = no enviar email automáticamente
                    Log::info('Trabajo despachado exitosamente');
                } else {
                    Log::error('Certificate::create() retornó null');
                    throw new \Exception('Certificate::create() retornó null - Error en la creación');
                }

                Log::info('=== FIN CREACIÓN CERTIFICADO EXITOSA ===');
                return $certificate;
            } catch (\Exception $e) {
                Log::error('Error en Certificate::create()', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'data_sent' => $data
                ]);
                throw $e;
            }
        });
    }

    /**
     * Actualizar certificado
     *
     * @param Certificate $certificate
     * @param array $data
     * @return Certificate
     */
    public function update(Certificate $certificate, array $data): Certificate
    {
        return DB::transaction(function () use ($certificate, $data) {
            $certificate->update($data);

            Log::info('Certificado actualizado exitosamente', ['certificate_id' => $certificate->id]);

            return $certificate->fresh();
        });
    }

    /**
     * Eliminar certificado
     *
     * @param Certificate $certificate
     * @return bool
     */
    public function delete(Certificate $certificate): bool
    {
        return DB::transaction(function () use ($certificate) {
            $certificateId = $certificate->id;

            // Eliminar documentos asociados
            $certificate->documents()->delete();

            // Eliminar validaciones asociadas
            $certificate->validations()->delete();

            $deleted = $certificate->delete();

            if ($deleted) {
                Log::info('Certificado eliminado exitosamente', ['certificate_id' => $certificateId]);
            }

            return $deleted;
        });
    }

    /**
     * Buscar certificados por criterios
     *
     * @param array $criteria
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(array $criteria, int $perPage = 15)
    {
        $query = Certificate::with(['user', 'activity', 'template', 'signer', 'documents', 'validations'])
            ->withCount(['documents', 'validations']);

        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('unique_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if (isset($criteria['activity_id'])) {
            $query->where('activity_id', $criteria['activity_id']);
        }

        if (isset($criteria['template_id'])) {
            $query->where('id_template', $criteria['template_id']);
        }

        if (isset($criteria['user_id'])) {
            $query->where('user_id', $criteria['user_id']);
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (isset($criteria['fecha_emision_from'])) {
            $query->where('fecha_emision', '>=', $criteria['fecha_emision_from']);
        }

        if (isset($criteria['fecha_emision_to'])) {
            $query->where('fecha_emision', '<=', $criteria['fecha_emision_to']);
        }

        if (isset($criteria['fecha_vencimiento_from'])) {
            $query->where('fecha_vencimiento', '>=', $criteria['fecha_vencimiento_from']);
        }

        if (isset($criteria['fecha_vencimiento_to'])) {
            $query->where('fecha_vencimiento', '<=', $criteria['fecha_vencimiento_to']);
        }

        if (isset($criteria['issue_date_from'])) {
            $query->where('issued_at', '>=', $criteria['issue_date_from']);
        }

        if (isset($criteria['issue_date_to'])) {
            $query->where('issued_at', '<=', $criteria['issue_date_to']);
        }

        return $query->orderBy('issued_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtener certificados por actividad
     *
     * @param int $activityId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getByActivity(int $activityId, int $perPage = 15)
    {
        return Certificate::with(['documents', 'validations'])
            ->withCount(['documents', 'validations'])
            ->where('activity_id', $activityId)
            ->orderBy('issued_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Cambiar estado del certificado
     *
     * @param Certificate $certificate
     * @param string $status
     * @return Certificate
     */
    public function changeStatus(Certificate $certificate, string $status): Certificate
    {
        $certificate->update(['status' => $status]);

        Log::info('Estado de certificado actualizado', [
            'certificate_id' => $certificate->id,
            'new_status' => $status
        ]);

        return $certificate->fresh();
    }

    /**
     * Generar documento del certificado
     *
     * @param Certificate $certificate
     * @param array $documentData
     * @return CertificateDocument
     */
    public function generateDocument(Certificate $certificate, array $documentData): CertificateDocument
    {
        return DB::transaction(function () use ($certificate, $documentData) {
            $documentData['certificate_id'] = $certificate->id;

            $document = CertificateDocument::create($documentData);

            // Actualizar estado del certificado si es necesario
            if ($certificate->status === 'draft') {
                $certificate->update(['status' => 'issued']);
            }

            Log::info('Documento de certificado generado', [
                'certificate_id' => $certificate->id,
                'document_id' => $document->id
            ]);

            return $document;
        });
    }

    /**
     * Obtener certificado por código
     *
     * @param string $code
     * @return Certificate|null
     */
    public function getByCode(string $code): ?Certificate
    {
        return Certificate::with(['activity', 'documents', 'validations'])
            ->where('unique_code', $code)
            ->first();
    }

    /**
     * Generar código único del certificado
     *
     * @return string
     */
    private function generateCertificateCode(): string
    {
        do {
            $code = 'CERT-' . strtoupper(Str::random(8));
        } while (Certificate::where('unique_code', $code)->exists());

        return $code;
    }

    /**
     * Generar datos de verificación y QR para el certificado
     *
     * @param Certificate $certificate
     * @return void
     */
    private function generateVerificationData(Certificate $certificate): void
    {
        try {
            // Generar códigos de verificación
            $verificationCode = $certificate->generateVerificationCode();
            $verificationToken = $certificate->generateVerificationToken();

            // Generar URL de verificación
            $verificationUrl = $certificate->getVerificationUrl($verificationCode);

            // Generar QR code usando la URL externa (qrserver)
            $qrContentUrl = $certificate->getQrContentUrl();
            $qrImagePath = $this->qrCodeService->generateQRCode($certificate, $qrContentUrl);

            // Preparar datos de validación
            $validationData = [
                'certificate_id' => $certificate->id,
                'holder_name' => $certificate->nombre,
                'activity_name' => $certificate->activity->name ?? 'N/A',
                'template_name' => $certificate->template->name ?? 'N/A',
                'issue_date' => $certificate->fecha_emision,
                'expiry_date' => $certificate->fecha_vencimiento,
                'status' => $certificate->status,
                'generated_at' => now()->toISOString()
            ];

            // Actualizar el certificado con los datos de verificación
            $certificate->update([
                'verification_code' => $verificationCode,
                'verification_token' => $verificationToken,
                'verification_url' => $verificationUrl,
                'qr_image_path' => $qrImagePath,
                'validation_data' => $validationData,
                'verification_count' => 0
            ]);

            Log::info('Datos de verificación generados exitosamente', [
                'certificate_id' => $certificate->id,
                'verification_code' => $verificationCode,
                'qr_image_path' => $qrImagePath
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar datos de verificación', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de certificados
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Certificate::query();

        if (isset($filters['activity_id'])) {
            $query->where('activity_id', $filters['activity_id']);
        }

        $total = $query->count();
        $issued = $query->where('status', 'issued')->count();
        $revoked = $query->where('status', 'revoked')->count();
        $draft = $query->where('status', 'draft')->count();

        return [
            'total' => $total,
            'issued' => $issued,
            'revoked' => $revoked,
            'draft' => $draft,
            'by_status' => [
                'issued' => $issued,
                'revoked' => $revoked,
                'draft' => $draft,
            ]
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * Obtener todos los certificados con paginación
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAll(int $perPage = 15)
    {
        return Certificate::with(['activity.company', 'documents', 'validations'])
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
        return Certificate::with(['activity.company', 'documents', 'validations'])
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
            // Generar código único del certificado
            $data['unique_code'] = $this->generateCertificateCode();

            $certificate = Certificate::create($data);

            Log::info('Certificado creado exitosamente', ['certificate_id' => $certificate->id]);

            return $certificate;
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
        $query = Certificate::with(['activity.company', 'documents', 'validations'])
            ->withCount(['documents', 'validations']);

        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('participant_name', 'like', "%{$search}%")
                  ->orWhere('participant_email', 'like', "%{$search}%")
                  ->orWhere('certificate_code', 'like', "%{$search}%");
            });
        }

        if (isset($criteria['activity_id'])) {
            $query->where('activity_id', $criteria['activity_id']);
        }

        if (isset($criteria['company_id'])) {
            $query->whereHas('activity', function ($q) use ($criteria) {
                $q->where('company_id', $criteria['company_id']);
            });
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
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
        return Certificate::with(['activity.company', 'documents', 'validations'])
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
        } while (Certificate::where('certificate_code', $code)->exists());

        return $code;
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

        if (isset($filters['company_id'])) {
            $query->whereHas('activity', function ($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            });
        }

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

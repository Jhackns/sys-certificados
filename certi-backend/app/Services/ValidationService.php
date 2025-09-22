<?php

namespace App\Services;

use App\Models\Validation;
use App\Models\Certificate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ValidationService
{
    /**
     * Obtener todas las validaciones con paginación
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAll(int $perPage = 15)
    {
        return Validation::with(['certificate.activity'])
            ->orderBy('validated_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Obtener validación por ID
     *
     * @param int $id
     * @return Validation|null
     */
    public function getById(int $id): ?Validation
    {
        return Validation::with(['certificate.activity'])->find($id);
    }

    /**
     * Crear nueva validación
     *
     * @param array $data
     * @return Validation
     */
    public function create(array $data): Validation
    {
        return DB::transaction(function () use ($data) {
            // Generar código único de validación
            $data['validation_code'] = $this->generateValidationCode();
            $data['validated_at'] = now();
            
            $validation = Validation::create($data);
            
            Log::info('Validación creada exitosamente', [
                'validation_id' => $validation->id,
                'certificate_id' => $validation->certificate_id
            ]);
            
            return $validation;
        });
    }

    /**
     * Validar un certificado por código
     *
     * @param string $certificateCode
     * @param array $validatorData
     * @return array
     */
    public function validateCertificate(string $certificateCode, array $validatorData): array
    {
        return DB::transaction(function () use ($certificateCode, $validatorData) {
            // Buscar el certificado
            $certificate = Certificate::with(['activity', 'template'])
                ->where('unique_code', $certificateCode)
                ->first();

            if (!$certificate) {
                return [
                    'success' => false,
                    'message' => 'Certificado no encontrado',
                    'certificate' => null,
                    'validation' => null
                ];
            }

            // Verificar si el certificado está activo
            if ($certificate->status !== 'issued') {
                return [
                    'success' => false,
                    'message' => 'El certificado no está activo o ha sido revocado',
                    'certificate' => $certificate,
                    'validation' => null
                ];
            }

            // Verificar si el certificado ha expirado
            if ($certificate->expiry_date && $certificate->expiry_date < now()) {
                return [
                    'success' => false,
                    'message' => 'El certificado ha expirado',
                    'certificate' => $certificate,
                    'validation' => null
                ];
            }

            // Crear registro de validación
            $validationData = array_merge($validatorData, [
                'certificate_id' => $certificate->id,
            ]);

            $validation = $this->create($validationData);

            Log::info('Certificado validado exitosamente', [
                'certificate_code' => $certificateCode,
                'validation_id' => $validation->id,
                'validator_ip' => $validatorData['validator_ip'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'Certificado válido',
                'certificate' => $certificate,
                'validation' => $validation
            ];
        });
    }

    /**
     * Obtener validaciones por certificado
     *
     * @param int $certificateId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getByCertificate(int $certificateId, int $perPage = 15)
    {
        return Validation::where('certificate_id', $certificateId)
            ->orderBy('validated_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Buscar validaciones por criterios
     *
     * @param array $criteria
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(array $criteria, int $perPage = 15)
    {
        $query = Validation::with(['certificate.activity']);

        if (isset($criteria['certificate_code'])) {
            $query->whereHas('certificate', function ($q) use ($criteria) {
                $q->where('unique_code', 'like', "%{$criteria['certificate_code']}%");
            });
        }

        if (isset($criteria['validation_code'])) {
            $query->where('validation_code', 'like', "%{$criteria['validation_code']}%");
        }

        if (isset($criteria['validator_ip'])) {
            $query->where('validator_ip', $criteria['validator_ip']);
        }

        if (isset($criteria['date_from'])) {
            $query->where('validated_at', '>=', $criteria['date_from']);
        }

        if (isset($criteria['date_to'])) {
            $query->where('validated_at', '<=', $criteria['date_to']);
        }

        return $query->orderBy('validated_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtener estadísticas de validaciones
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Validation::query();

        if (isset($filters['date_from'])) {
            $query->where('validated_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('validated_at', '<=', $filters['date_to']);
        }

        $total = $query->count();
        $today = $query->whereDate('validated_at', today())->count();
        $thisWeek = $query->whereBetween('validated_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $thisMonth = $query->whereMonth('validated_at', now()->month)->count();

        // Validaciones por día en los últimos 30 días
        $dailyValidations = Validation::selectRaw('DATE(validated_at) as date, COUNT(*) as count')
            ->where('validated_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        return [
            'total' => $total,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'daily_validations' => $dailyValidations,
        ];
    }

    /**
     * Generar código único de validación
     *
     * @return string
     */
    private function generateValidationCode(): string
    {
        do {
            $code = 'VAL-' . strtoupper(Str::random(10));
        } while (Validation::where('validation_code', $code)->exists());

        return $code;
    }

    /**
     * Obtener validación por código
     *
     * @param string $code
     * @return Validation|null
     */
    public function getByCode(string $code): ?Validation
    {
        return Validation::with(['certificate.activity'])
            ->where('validation_code', $code)
            ->first();
    }
}
<?php

namespace App\Http\Controllers\API\Certificates;

use App\Http\Controllers\Controller;
use App\Models\Validation;
use App\Services\ValidationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ValidationController extends Controller
{
    use ApiResponseTrait;

    protected $validationService;

    public function __construct(ValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Mostrar todas las validaciones
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            
            // Si hay criterios de búsqueda, usar el método search
            if ($request->hasAny(['certificate_code', 'validation_code', 'validator_ip', 'date_from', 'date_to', 'company_id'])) {
                $criteria = $request->only(['certificate_code', 'validation_code', 'validator_ip', 'date_from', 'date_to', 'company_id']);
                $validations = $this->validationService->search($criteria, $perPage);
            } else {
                $validations = $this->validationService->getAll($perPage);
            }

            return $this->successResponse([
                'validations' => $validations->items()->map(function ($validation) {
                    return [
                        'id' => $validation->id,
                        'validation_code' => $validation->validation_code,
                        'validated_at' => $validation->validated_at,
                        'validator_ip' => $validation->validator_ip,
                        'validator_user_agent' => $validation->validator_user_agent,
                        'certificate' => [
                            'id' => $validation->certificate->id,
                            'certificate_code' => $validation->certificate->certificate_code,
                            'participant_name' => $validation->certificate->participant_name,
                            'participant_email' => $validation->certificate->participant_email,
                            'activity' => [
                                'id' => $validation->certificate->activity->id,
                                'name' => $validation->certificate->activity->name,
                                'company' => [
                                    'id' => $validation->certificate->activity->company->id,
                                    'name' => $validation->certificate->activity->company->name,
                                ],
                            ],
                        ],
                    ];
                }),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ]
            ], 'Validaciones obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener validaciones: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener validaciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar una validación específica
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $id = (int) $id;
            $validation = $this->validationService->getById($id);
        
            if (!$validation) {
                return $this->notFoundResponse('Validación no encontrada');
            }

            return $this->successResponse([
                'validation' => [
                    'id' => $validation->id,
                    'validation_code' => $validation->validation_code,
                    'validated_at' => $validation->validated_at,
                    'validator_ip' => $validation->validator_ip,
                    'validator_user_agent' => $validation->validator_user_agent,
                    'certificate' => [
                        'id' => $validation->certificate->id,
                        'certificate_code' => $validation->certificate->certificate_code,
                        'participant_name' => $validation->certificate->participant_name,
                        'participant_email' => $validation->certificate->participant_email,
                        'issue_date' => $validation->certificate->issue_date,
                        'expiry_date' => $validation->certificate->expiry_date,
                        'status' => $validation->certificate->status,
                        'activity' => [
                            'id' => $validation->certificate->activity->id,
                            'name' => $validation->certificate->activity->name,
                            'description' => $validation->certificate->activity->description,
                            'duration_hours' => $validation->certificate->activity->duration_hours,
                            'company' => [
                                'id' => $validation->certificate->activity->company->id,
                                'name' => $validation->certificate->activity->company->name,
                                'ruc' => $validation->certificate->activity->company->ruc,
                            ],
                        ],
                    ],
                ]
            ], 'Validación obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener validación: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener validación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validar un certificado por código
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateCertificate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'certificate_code' => 'required|string',
            ]);

            $certificateCode = $request->input('certificate_code');
            
            // Obtener datos del validador
            $validatorData = [
                'validator_ip' => $request->ip(),
                'validator_user_agent' => $request->userAgent(),
            ];

            $result = $this->validationService->validateCertificate($certificateCode, $validatorData);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            return $this->successResponse([
                'message' => $result['message'],
                'certificate' => [
                    'id' => $result['certificate']->id,
                    'certificate_code' => $result['certificate']->certificate_code,
                    'participant_name' => $result['certificate']->participant_name,
                    'participant_email' => $result['certificate']->participant_email,
                    'issue_date' => $result['certificate']->issue_date,
                    'expiry_date' => $result['certificate']->expiry_date,
                    'status' => $result['certificate']->status,
                    'activity' => [
                        'id' => $result['certificate']->activity->id,
                        'name' => $result['certificate']->activity->name,
                        'description' => $result['certificate']->activity->description,
                        'duration_hours' => $result['certificate']->activity->duration_hours,
                        'company' => [
                            'id' => $result['certificate']->activity->company->id,
                            'name' => $result['certificate']->activity->company->name,
                            'ruc' => $result['certificate']->activity->company->ruc,
                        ],
                    ],
                ],
                'validation' => [
                    'id' => $result['validation']->id,
                    'validation_code' => $result['validation']->validation_code,
                    'validated_at' => $result['validation']->validated_at,
                ]
            ], 'Certificado validado correctamente');
        } catch (\Exception $e) {
            Log::error('Error al validar certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al validar certificado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener validaciones por certificado
     *
     * @param Request $request
     * @param int $certificateId
     * @return JsonResponse
     */
    public function byCertificate(Request $request, $certificateId): JsonResponse
    {
        try {
            $certificateId = (int) $certificateId;
            $perPage = $request->query('per_page', 15);
            
            $validations = $this->validationService->getByCertificate($certificateId, $perPage);

            return $this->successResponse([
                'validations' => $validations->items()->map(function ($validation) {
                    return [
                        'id' => $validation->id,
                        'validation_code' => $validation->validation_code,
                        'validated_at' => $validation->validated_at,
                        'validator_ip' => $validation->validator_ip,
                        'validator_user_agent' => $validation->validator_user_agent,
                    ];
                }),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ]
            ], 'Validaciones del certificado obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener validaciones por certificado: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener validaciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener validación por código
     *
     * @param string $code
     * @return JsonResponse
     */
    public function byCode($code): JsonResponse
    {
        try {
            $validation = $this->validationService->getByCode($code);

            if (!$validation) {
                return $this->notFoundResponse('Validación no encontrada');
            }

            return $this->successResponse([
                'validation' => [
                    'id' => $validation->id,
                    'validation_code' => $validation->validation_code,
                    'validated_at' => $validation->validated_at,
                    'validator_ip' => $validation->validator_ip,
                    'validator_user_agent' => $validation->validator_user_agent,
                    'certificate' => [
                        'id' => $validation->certificate->id,
                        'certificate_code' => $validation->certificate->certificate_code,
                        'participant_name' => $validation->certificate->participant_name,
                        'participant_email' => $validation->certificate->participant_email,
                        'activity' => [
                            'id' => $validation->certificate->activity->id,
                            'name' => $validation->certificate->activity->name,
                            'company' => [
                                'id' => $validation->certificate->activity->company->id,
                                'name' => $validation->certificate->activity->company->name,
                            ],
                        ],
                    ],
                ]
            ], 'Validación obtenida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener validación por código: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener validación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas de validaciones
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['company_id', 'date_from', 'date_to']);
            $statistics = $this->validationService->getStatistics($filters);

            return $this->successResponse([
                'statistics' => $statistics
            ], 'Estadísticas de validaciones obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de validaciones: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }
}
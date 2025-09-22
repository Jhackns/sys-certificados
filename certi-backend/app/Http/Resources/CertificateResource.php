<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Verificar que el recurso no sea nulo
        if (!$this->resource) {
            \Log::error('CertificateResource: Recurso nulo detectado');
            return [];
        }

        try {
            return [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'activity_id' => $this->activity_id,
                'id_template' => $this->id_template,
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
                'fecha_emision' => $this->fecha_emision,
                'fecha_vencimiento' => $this->fecha_vencimiento,
                'signed_by' => $this->signed_by,
                'unique_code' => $this->unique_code,
                'qr_url' => $this->qr_url,
                'issued_at' => $this->issued_at,
                'status' => $this->status,
                'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
                'validations_count' => $this->when(isset($this->validations_count), $this->validations_count),
                'user' => $this->whenLoaded('user', function () {
                    if (!$this->user) {
                        \Log::warning('CertificateResource: Usuario nulo para certificado', ['certificate_id' => $this->id]);
                        return null;
                    }
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                    ];
                }),
                'activity' => $this->whenLoaded('activity', function () {
                    if (!$this->activity) {
                        \Log::warning('CertificateResource: Actividad nula para certificado', ['certificate_id' => $this->id]);
                        return null;
                    }
                    return [
                        'id' => $this->activity->id,
                        'name' => $this->activity->name,
                        'description' => $this->activity->description,
                        'duration_hours' => $this->activity->duration_hours,
                    ];
                }),
                'template' => $this->whenLoaded('template', function () {
                    if (!$this->template) {
                        \Log::warning('CertificateResource: Plantilla nula para certificado', ['certificate_id' => $this->id]);
                        return null;
                    }
                    return [
                        'id' => $this->template->id,
                        'name' => $this->template->name,
                        'description' => $this->template->description,
                    ];
                }),
                'signer' => $this->whenLoaded('signer', function () {
                    if (!$this->signer) {
                        \Log::warning('CertificateResource: Firmante nulo para certificado', ['certificate_id' => $this->id]);
                        return null;
                    }
                    return [
                        'id' => $this->signer->id,
                        'name' => $this->signer->name,
                        'email' => $this->signer->email,
                    ];
                }),
                'documents' => $this->whenLoaded('documents', function () {
                    return $this->documents->map(function ($document) {
                        if (!$document) {
                            \Log::warning('CertificateResource: Documento nulo encontrado', ['certificate_id' => $this->id]);
                            return null;
                        }
                        return [
                            'id' => $document->id,
                            'file_name' => $document->file_name,
                            'file_path' => $document->file_path,
                            'file_type' => $document->file_type,
                            'file_size' => $document->file_size,
                            'created_at' => $document->created_at,
                        ];
                    })->filter(); // Filtrar elementos nulos
                }),
                'validations' => $this->whenLoaded('validations', function () {
                    return $this->validations->map(function ($validation) {
                        if (!$validation) {
                            \Log::warning('CertificateResource: Validación nula encontrada', ['certificate_id' => $this->id]);
                            return null;
                        }
                        return [
                            'id' => $validation->id,
                            'validation_code' => $validation->validation_code,
                            'validated_at' => $validation->validated_at,
                            'validator_ip' => $validation->validator_ip,
                            'validator_user_agent' => $validation->validator_user_agent,
                        ];
                    })->filter(); // Filtrar elementos nulos
                }),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ];
        } catch (\Exception $e) {
            \Log::error('CertificateResource: Error al procesar recurso', [
                'certificate_id' => $this->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

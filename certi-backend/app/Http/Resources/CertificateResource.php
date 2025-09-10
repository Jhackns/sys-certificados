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
        return [
            'id' => $this->id,
            'unique_code' => $this->unique_code,
            'participant_name' => $this->participant_name,
            'participant_email' => $this->participant_email,
            'participant_document' => $this->participant_document,
            'issued_at' => $this->issued_at,
            'status' => $this->status,
            'activity_id' => $this->activity_id,
            // 'template_id' => $this->template_id, // no existe en el modelo/migración actual
            'documents_count' => $this->when(isset($this->documents_count), $this->documents_count),
            'validations_count' => $this->when(isset($this->validations_count), $this->validations_count),
            'activity' => $this->whenLoaded('activity', function () {
                return [
                    'id' => $this->activity->id,
                    'name' => $this->activity->name,
                    'description' => $this->activity->description,
                    'duration_hours' => $this->activity->duration_hours,
                    'company' => $this->when($this->activity->relationLoaded('company'), [
                        'id' => $this->activity->company->id,
                        'name' => $this->activity->company->name,
                        'ruc' => $this->activity->company->ruc,
                    ]),
                ];
            }),
            // 'template' => $this->whenLoaded('template', function () {
            //     return [
            //         'id' => $this->template->id,
            //         'name' => $this->template->name,
            //         'description' => $this->template->description,
            //     ];
            // }),
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'file_name' => $document->file_name,
                        'file_path' => $document->file_path,
                        'file_type' => $document->file_type,
                        'file_size' => $document->file_size,
                        'created_at' => $document->created_at,
                    ];
                });
            }),
            'validations' => $this->whenLoaded('validations', function () {
                return $this->validations->map(function ($validation) {
                    return [
                        'id' => $validation->id,
                        'validation_code' => $validation->validation_code,
                        'validated_at' => $validation->validated_at,
                        'validator_ip' => $validation->validator_ip,
                        'validator_user_agent' => $validation->validator_user_agent,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

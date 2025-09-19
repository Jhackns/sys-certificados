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
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
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
            'template' => $this->whenLoaded('template', function () {
                return [
                    'id' => $this->template->id,
                    'name' => $this->template->name,
                    'description' => $this->template->description,
                ];
            }),
            'signer' => $this->whenLoaded('signer', function () {
                return [
                    'id' => $this->signer->id,
                    'name' => $this->signer->name,
                    'email' => $this->signer->email,
                ];
            }),
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

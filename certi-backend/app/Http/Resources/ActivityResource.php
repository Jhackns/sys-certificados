<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'company_id' => $this->company_id,
            'certificates_count' => $this->when(isset($this->certificates_count), $this->certificates_count),
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'ruc' => $this->company->ruc,
                ];
            }),
            'certificates' => $this->whenLoaded('certificates', function () {
                return $this->certificates->map(function ($certificate) {
                    return [
                        'id' => $certificate->id,
                        'unique_code' => $certificate->unique_code,
                        'issued_at' => $certificate->issued_at,
                        'status' => $certificate->status,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

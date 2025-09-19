<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'activity_id' => 'required|exists:activities,id',
            'id_template' => 'required|exists:certificate_templates,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after:fecha_emision',
            'signed_by' => 'nullable|exists:users,id',
            'status' => 'sometimes|in:active,revoked,expired',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'activity_id.required' => 'La actividad es obligatoria.',
            'activity_id.exists' => 'La actividad seleccionada no existe.',
            'id_template.required' => 'La plantilla es obligatoria.',
            'id_template.exists' => 'La plantilla seleccionada no existe.',
            'nombre.required' => 'El nombre del certificado es obligatorio.',
            'nombre.max' => 'El nombre del certificado no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date' => 'La fecha de emisión debe ser una fecha válida.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a la fecha de emisión.',
            'signed_by.exists' => 'El firmante seleccionado no existe.',
            'status.in' => 'El estado debe ser: active, revoked o expired.',
        ];
    }
}
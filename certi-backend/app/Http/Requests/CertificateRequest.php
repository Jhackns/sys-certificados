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
            'participant_name' => 'required|string|max:255',
            'participant_email' => 'required|email|max:255',
            'participant_document' => 'nullable|string|max:50',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:issue_date',
            'activity_id' => 'required|exists:activities,id',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'status' => 'sometimes|in:draft,issued,revoked',
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
            'participant_name.required' => 'El nombre del participante es obligatorio.',
            'participant_name.max' => 'El nombre del participante no puede exceder 255 caracteres.',
            'participant_email.required' => 'El email del participante es obligatorio.',
            'participant_email.email' => 'El email debe tener un formato válido.',
            'participant_email.max' => 'El email no puede exceder 255 caracteres.',
            'participant_document.max' => 'El documento no puede exceder 50 caracteres.',
            'issue_date.required' => 'La fecha de emisión es obligatoria.',
            'issue_date.date' => 'La fecha de emisión debe ser una fecha válida.',
            'expiry_date.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'expiry_date.after' => 'La fecha de vencimiento debe ser posterior a la fecha de emisión.',
            'activity_id.required' => 'La actividad es obligatoria.',
            'activity_id.exists' => 'La actividad seleccionada no existe.',
            'template_id.exists' => 'La plantilla seleccionada no existe.',
            'status.in' => 'El estado debe ser: draft, issued o revoked.',
        ];
    }
}
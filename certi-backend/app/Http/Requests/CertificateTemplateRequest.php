<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateTemplateRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'activity_type' => 'required|in:course,event,other',
            'status' => 'required|in:active,inactive',
            'template_file' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120', // Permitir imágenes y aumentar tamaño
        ];

        // Para actualizaciones, hacer todos los campos opcionales pero requeridos si se proporcionan
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|required|string|max:255';
            $rules['activity_type'] = 'sometimes|required|in:course,event,other';
            $rules['status'] = 'sometimes|required|in:active,inactive';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la plantilla es obligatorio.',
            'name.max' => 'El nombre de la plantilla no puede exceder 255 caracteres.',
            'description.max' => 'La descripción no puede exceder 1000 caracteres.',
            'activity_type.in' => 'El tipo de actividad debe ser: course, event o other.',
            'status.in' => 'El estado debe ser: active o inactive.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
            'template_file.file' => 'Debe ser un archivo válido.',
            'template_file.mimes' => 'El archivo debe ser de tipo: jpg, jpeg, png o pdf.',
            'template_file.max' => 'El archivo no puede exceder 5MB.',
        ];
    }
}
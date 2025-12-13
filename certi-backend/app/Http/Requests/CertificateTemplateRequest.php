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
            // Posiciones para texto y QR
            // Posiciones para texto y QR
            'name_position' => 'required|array',
            'name_position.x' => 'required_with:name_position|numeric',
            'name_position.y' => 'required_with:name_position|numeric',
            'name_position.left' => 'sometimes|numeric',
            'name_position.top' => 'sometimes|numeric',
            'name_position.fontSize' => 'sometimes|numeric|min:6|max:200',
            'name_position.fontFamily' => 'sometimes|string|max:100',
            'name_position.color' => 'sometimes|string|max:20',
            'name_position.rotation' => 'sometimes|numeric|min:-360|max:360',
            'name_position.textAlign' => 'sometimes|in:left,center,right',
            'name_position.fontWeight' => 'sometimes|string|max:20',
            'name_position.fontStyle' => 'sometimes|string|max:20',

            'qr_position' => 'nullable|array',
            'qr_position.x' => 'required_with:qr_position|numeric',
            'qr_position.y' => 'required_with:qr_position|numeric',
            'qr_position.left' => 'sometimes|numeric',
            'qr_position.top' => 'sometimes|numeric',
            'qr_position.width' => 'sometimes|numeric|min:10|max:2000',
            'qr_position.height' => 'sometimes|numeric|min:10|max:2000',
            'qr_position.rotation' => 'sometimes|numeric|min:-360|max:360',
            // Posición de fecha (opcional)
            'date_position' => 'nullable|array',
            'date_position.x' => 'required_with:date_position|numeric',
            'date_position.y' => 'required_with:date_position|numeric',
            'date_position.left' => 'sometimes|numeric',
            'date_position.top' => 'sometimes|numeric',
            'date_position.fontSize' => 'sometimes|numeric|min:6|max:200',
            'date_position.fontFamily' => 'sometimes|string|max:100',
            'date_position.color' => 'sometimes|string|max:20',
            'date_position.rotation' => 'sometimes|numeric|min:-360|max:360',
            'date_position.textAlign' => 'sometimes|in:left,center,right',
            'date_position.fontWeight' => 'sometimes|string|max:20',
            'date_position.fontStyle' => 'sometimes|string|max:20',
            // Tamaño y estilos de fondo
            'background_image_size' => 'sometimes|array',
            'background_image_size.width' => 'required_with:background_image_size|numeric',
            'background_image_size.height' => 'required_with:background_image_size|numeric',
            'template_styles' => 'sometimes|array',
            'template_styles.background_offset' => 'sometimes|array',
            'template_styles.background_offset.x' => 'required_with:template_styles.background_offset|numeric',
            'template_styles.background_offset.y' => 'required_with:template_styles.background_offset|numeric',
            'template_styles.editor_canvas_size' => 'sometimes|array',
            'template_styles.editor_canvas_size.width' => 'required_with:template_styles.editor_canvas_size|numeric',
            'template_styles.editor_canvas_size.height' => 'required_with:template_styles.editor_canvas_size|numeric',
            'template_styles.components' => 'sometimes|array',
            'template_styles.components.*' => 'string',
        ];

        // Para actualizaciones, hacer todos los campos opcionales pero requeridos si se proporcionan
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|required|string|max:255';
            $rules['activity_type'] = 'sometimes|required|in:course,event,other';
            $rules['status'] = 'sometimes|required|in:active,inactive';
            // Mantener reglas de posiciones como sometimes
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

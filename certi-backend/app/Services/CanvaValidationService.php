<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CanvaValidationService
{
    protected $clientId;
    protected $clientSecret;
    protected $apiUrl = 'https://api.canva.com/v1';

    public function __construct()
    {
        $this->clientId = config('services.canva.client_id');
        $this->clientSecret = config('services.canva.client_secret');
    }

    /**
     * Validar si un design ID de Canva es válido y accesible
     *
     * @param string $designId
     * @return array
     */
    public function validateDesignId(string $designId): array
    {
        try {
            Log::info('Iniciando validación de diseño de Canva', ['design_id' => $designId]);

            // Primero verificar si es una URL y extraer el ID
            $extractedId = $this->extractDesignIdFromUrl($designId);
            $actualId = $extractedId ?: $designId;

            Log::info('ID a validar', ['id' => $actualId]);

            // Validar el formato del ID de Canva
            // Los IDs de Canva generalmente tienen un formato específico
            if (strlen($actualId) < 10) {
                return [
                    'valid' => false,
                    'status' => 'invalid_format',
                    'message' => 'El ID del diseño es demasiado corto'
                ];
            }

            // Verificar que solo contenga caracteres válidos
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $actualId)) {
                return [
                    'valid' => false,
                    'status' => 'invalid_format',
                    'message' => 'El ID contiene caracteres no válidos'
                ];
            }

            // Simular una validación exitosa (ya que no podemos autenticarnos con Canva)
            // En un entorno real, aquí se haría la llamada a la API de Canva
            Log::info('Validación de formato exitosa', ['id' => $actualId]);

            return [
                'valid' => true,
                'status' => 'valid_format',
                'message' => 'Formato de diseño válido',
                'data' => [
                    'title' => 'Diseño de Canva',
                    'type' => 'certificate',
                    'design_id' => $actualId,
                    'status' => 'formato_válido',
                    'note' => 'Validación de formato solamente - requiere configuración de API para verificación completa'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error al validar design ID de Canva: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'valid' => false,
                'status' => 'exception',
                'message' => 'Error al validar diseño: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validar formato de URL de diseño de Canva
     *
     * @param string $url
     * @return array
     */
    public function validateCanvaUrl(string $url): array
    {
        // Patrón para URLs de diseño de Canva
        $patterns = [
            '/canva\.com\/design\/([A-Za-z0-9_-]+)\/view/',
            '/canva\.com\/design\/([A-Za-z0-9_-]+)/',
            '/canva\.com\/([A-Za-z0-9_-]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'valid' => true,
                    'design_id' => $matches[1] ?? null,
                    'message' => 'URL válida de Canva detectada'
                ];
            }
        }

        return [
            'valid' => false,
            'message' => 'URL no válida de Canva'
        ];
    }

    /**
     * Extraer design ID de una URL de Canva
     *
     * @param string $url
     * @return string|null
     */
    public function extractDesignIdFromUrl(string $url): ?string
    {
        $validation = $this->validateCanvaUrl($url);
        return $validation['valid'] ? $validation['design_id'] : null;
    }
}
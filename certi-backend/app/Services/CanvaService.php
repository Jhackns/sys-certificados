<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class CanvaService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.canva.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.canva.api_key');
    }

    /**
     * Genera un certificado personalizado usando la API de Canva
     *
     * @param string $designId ID del diseño en Canva
     * @param array $userData Datos del usuario para personalizar el certificado
     * @return string Ruta del archivo PDF generado
     * @throws Exception
     */
    public function generateCertificate(string $designId, array $userData): string
    {
        try {
            Log::info('Iniciando generación de certificado con Canva', [
                'design_id' => $designId,
                'user_data' => $userData
            ]);

            // Obtener token de acceso usando Client ID y Client Secret
            $tokenResponse = Http::post('https://api.canva.com/oauth/token', [
                'client_id' => config('services.canva.client_id'),
                'client_secret' => config('services.canva.client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'designs.read'
            ]);

            if (!$tokenResponse->successful()) {
                throw new \Exception('Error al obtener token de acceso: ' . $tokenResponse->body());
            }

            $accessToken = $tokenResponse->json('access_token');

            // Preparar datos para la API de Canva
            // Los marcadores en el diseño de Canva deben ser: {{nombreCompleto}}, {{nombreEvento}}, {{fechaEmision}}, {{codigoQR}}
            $requestData = [
                'design_id' => $designId,
                'brand_id' => config('services.canva.brand_id', null),
                'data' => [
                    // Mapeo de marcadores de posición a los datos del usuario
                    'nombreCompleto' => $userData['nombre'] ?? 'Usuario',
                    //'nombreEvento' => $userData['titulo'] ?? 'Certificado',
                    'fechaEmision' => $userData['fecha_emision'] ?? date('d/m/Y'),
                    'codigoQR' => $userData['qr_url'] ?? '',
                    // Mantener compatibilidad con nombres anteriores
                    'user_name' => $userData['nombre'] ?? 'Usuario',
                    'certificate_title' => $userData['titulo'] ?? 'Certificado',
                    'issue_date' => $userData['fecha_emision'] ?? date('d/m/Y'),
                    'qr_code_url' => $userData['qr_url'] ?? '',
                ]
            ];

            // Realizar petición a la API de Canva
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/designs/{$designId}/exports", $requestData);

            if (!$response->successful()) {
                Log::error('Error en la respuesta de Canva API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('Error al comunicarse con la API de Canva: ' . $response->body());
            }

            $responseData = $response->json();
            $downloadUrl = $responseData['download_url'] ?? null;

            if (!$downloadUrl) {
                throw new Exception('No se recibió URL de descarga desde Canva');
            }

            // Descargar el PDF generado
            $pdfContent = Http::get($downloadUrl)->body();

            // Generar nombre de archivo único
            $fileName = 'certificates/' . uniqid('cert_') . '_' . $userData['user_id'] . '.pdf';

            // Guardar el archivo
            Storage::put($fileName, $pdfContent);

            Log::info('Certificado generado exitosamente', ['file_path' => $fileName]);

            return $fileName;
        } catch (Exception $e) {
            Log::error('Error al generar certificado con Canva', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

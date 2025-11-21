<?php

namespace App\Services;

use App\Models\Certificate;
use App\Services\CertificateImageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class QRCodeService
{
    protected $imageService;

    public function __construct(CertificateImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Generar código QR a partir de una URL
     */
    public function generateQRCodeFromUrl(string $url): string
    {
        $filename = 'qr_' . Str::random(10) . '.png';
        $path = 'qrcodes/' . $filename;

        try {
            $qrCode = QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->errorCorrection('M')
                ->generate($url);

            Storage::disk('public')->put($path, $qrCode);
            return $path;
        } catch (\Exception $e) {
            Log::warning('Fallo al generar QR (URL) con simple-qrcode, aplicando fallback GD', [
                'error' => $e->getMessage()
            ]);

            try {
                $im = \imagecreatetruecolor(300, 300);
                $white = \imagecolorallocate($im, 255, 255, 255);
                $black = \imagecolorallocate($im, 0, 0, 0);
                \imagefill($im, 0, 0, $white);
                \imagerectangle($im, 0, 0, 299, 299, $black);
                \imagestring($im, 3, 60, 140, 'QR no disponible', $black);
                ob_start();
                \imagepng($im);
                $pngData = ob_get_clean();
                \imagedestroy($im);
                Storage::disk('public')->put($path, $pngData);
                return $path;
            } catch (\Exception $gdEx) {
                Log::error('Fallback GD para QR (URL) falló', [
                    'error' => $gdEx->getMessage()
                ]);
                return '';
            }
        }
    }
    /**
     * Generar código QR para un certificado
     */
    public function generateQRCode(Certificate $certificate, ?string $verificationUrl = null): string
    {
        // Generación activa de QR (sin deshabilitación temporal por env)

        // Generar códigos de verificación si no existen
        if (!$certificate->verification_code) {
            $certificate->verification_code = $this->generateVerificationCode($certificate);
        }

        if (!$certificate->verification_token) {
            $certificate->verification_token = $this->generateVerificationToken();
        }

        // URL de verificación (usar la provista si existe)
        $certificate->verification_url = $verificationUrl
            ? $verificationUrl
            : $this->generateVerificationUrl($certificate->verification_code);

        // Generar imagen QR
        $qrImagePath = $this->createQRImage($certificate->verification_url, $certificate);
        $certificate->qr_image_path = $qrImagePath;

        // Generar imagen final del certificado con QR y nombre
        $finalImagePath = $this->generateFinalCertificateImage($certificate);
        $certificate->final_image_path = $finalImagePath;

        // Guardar cambios
        $certificate->save();

        return $qrImagePath;
    }

    /**
     * Crear imagen QR y guardarla en storage
     */
    private function createQRImage(string $url, Certificate $certificate): string
    {
        // Generar nombre único para el archivo en PNG
        $filename = 'qr_' . $certificate->id . '_' . Str::random(8) . '.png';
        $path = 'certificates/qr/' . $filename;

        try {
            // Intentar con simple-qrcode (requiere Imagick para PNG en esta versión)
            $qrCode = QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->errorCorrection('M')
                ->generate($url);

            Storage::disk('public')->put($path, $qrCode);
            Log::info('QR generado con simple-qrcode', [
                'certificate_id' => $certificate->id,
                'path' => $path,
                'url' => $url
            ]);
            return $path;
        } catch (\Exception $e) {
            // Fallback: generar un PNG simple con GD si Imagick no está disponible
            Log::warning('Fallo al generar QR con simple-qrcode, aplicando fallback GD', [
                'error' => $e->getMessage()
            ]);

            try {
                if (class_exists('TCPDF2DBarcode')) {
                    $barcode = new \TCPDF2DBarcode($url, 'QRCODE,H');
                    $pngData = $barcode->getBarcodePngData(6);
                    Storage::disk('public')->put($path, $pngData);
                    Log::info('QR generado con TCPDF2DBarcode (fallback)', [
                        'certificate_id' => $certificate->id,
                        'path' => $path,
                        'url' => $url
                    ]);
                    return $path;
                }

                // Último fallback: generar un PNG legible indicando error
                $im = \imagecreatetruecolor(300, 300);
                \imagesavealpha($im, true);
                $transparent = \imagecolorallocatealpha($im, 255, 255, 255, 0);
                \imagefill($im, 0, 0, $transparent);
                $black = \imagecolorallocate($im, 0, 0, 0);
                \imagerectangle($im, 0, 0, 299, 299, $black);
                \imagestring($im, 3, 60, 140, 'QR no disponible', $black);
                ob_start();
                \imagepng($im);
                $pngData = ob_get_clean();
                \imagedestroy($im);
                Storage::disk('public')->put($path, $pngData);
                Log::warning('Fallback QR simple generado', [
                    'certificate_id' => $certificate->id,
                    'path' => $path,
                    'url' => $url
                ]);
                return $path;
            } catch (\Exception $gdEx) {
                Log::error('Fallback GD para QR falló', [
                    'error' => $gdEx->getMessage()
                ]);
                // En último caso, no bloquear creación: devolver ruta vacía
                return '';
            }
        }
    }

    /**
     * Generar código de verificación mixto
     */
    private function generateVerificationCode(Certificate $certificate): string
    {
        $prefix = 'CERT' . str_pad($certificate->id, 3, '0', STR_PAD_LEFT);
        $token = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        return $prefix . '-' . $token;
    }

    /**
     * Generar token de verificación seguro
     */
    private function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generar URL de verificación completa
     */
    private function generateVerificationUrl(string $verificationCode): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return $baseUrl . '/verify/' . $verificationCode;
    }

    /**
     * Obtener ruta completa de la imagen QR
     */
    public function getQRImageUrl(Certificate $certificate): ?string
    {
        if (!$certificate->qr_image_path) {
            return null;
        }

        return asset('storage/' . $certificate->qr_image_path);
    }

    /**
     * Verificar un certificado por código de verificación
     */
    public function verifyCertificate(string $verificationCode): ?Certificate
    {
        $certificate = Certificate::byVerificationCode($verificationCode)
            ->with(['user', 'template', 'activity', 'signer'])
            ->first();

        if ($certificate) {
            // Incrementar contador de verificaciones
            $certificate->incrementVerificationCount();
        }

        return $certificate;
    }

    /**
     * Obtener datos de validación para mostrar en la página web
     */
    public function getValidationData(Certificate $certificate): array
    {
        return [
            'certificate_id' => $certificate->id,
            'verification_code' => $certificate->verification_code,
            'holder_name' => $certificate->user->name ?? 'N/A',
            'certificate_title' => $certificate->nombre,
            'description' => $certificate->descripcion,
            'issue_date' => $certificate->fecha_emision->format('d/m/Y'),
            'expiry_date' => $certificate->fecha_vencimiento?->format('d/m/Y'),
            'issued_at' => $certificate->issued_at->format('d/m/Y H:i'),
            'status' => $certificate->status,
            'is_valid' => $certificate->isValid(),
            'activity' => $certificate->activity->name ?? 'N/A',
            'template' => $certificate->template->name ?? 'N/A',
            'signer' => $certificate->signer->name ?? 'N/A',
            'verification_count' => $certificate->verification_count,
            'last_verified' => $certificate->last_verified_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Generar imagen final del certificado con QR
     */
    private function generateFinalCertificateImage(Certificate $certificate): ?string
    {
        try {
            return $this->imageService->generateFinalCertificateImage($certificate, $certificate->qr_image_path);
        } catch (\Exception $e) {
            Log::error('Error generando imagen final del certificado: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Regenerar QR para un certificado existente
     */
    public function regenerateQRCode(Certificate $certificate): string
    {
        // Eliminar imagen anterior si existe
        if ($certificate->qr_image_path) {
            Storage::disk('public')->delete($certificate->qr_image_path);
        }

        // Generar nuevo QR
        return $this->generateQRCode($certificate);
    }
}

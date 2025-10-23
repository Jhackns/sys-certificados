<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Imagick;

class CertificateImageService
{
    protected $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Generar imagen final del certificado con QR y nombre superpuestos
     *
     * @param Certificate $certificate
     * @param string $qrImagePath
     * @return string|null
     */
    public function generateFinalCertificateImage(Certificate $certificate, string $qrImagePath): ?string
    {
        try {
            Log::info('Iniciando generación de imagen final del certificado', [
                'certificate_id' => $certificate->id,
                'qr_image_path' => $qrImagePath
            ]);

            // Obtener la plantilla del certificado
            $template = $certificate->template;
            if (!$template || !$template->file_path) {
                Log::error('Plantilla no encontrada o sin imagen de fondo', [
                    'certificate_id' => $certificate->id,
                    'template_id' => $certificate->id_template
                ]);
                return null;
            }

            // Cargar la imagen de fondo de la plantilla
            $backgroundImagePath = storage_path('app/' . $template->file_path);
            if (!file_exists($backgroundImagePath)) {
                Log::error('Archivo de imagen de fondo no encontrado', [
                    'path' => $backgroundImagePath
                ]);
                return null;
            }

            // Crear la imagen base
            $image = $this->imageManager->read($backgroundImagePath);
            
            // Superponer el nombre del titular si hay coordenadas definidas
            if ($template->name_position && is_array($template->name_position)) {
                $this->addNameToImage($image, $certificate->nombre, $template->name_position);
            }

            // Superponer el código QR si hay coordenadas definidas
            if ($template->qr_position && is_array($template->qr_position) && file_exists($qrImagePath)) {
                $this->addQRToImage($image, $qrImagePath, $template->qr_position);
            }

            // Generar nombre único para la imagen final
            $finalImageName = 'certificates/final/' . $certificate->id . '_' . time() . '.png';
            $finalImagePath = storage_path('app/public/' . $finalImageName);

            // Crear directorio si no existe
            $directory = dirname($finalImagePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Guardar la imagen final
            $image->save($finalImagePath);

            Log::info('Imagen final del certificado generada exitosamente', [
                'certificate_id' => $certificate->id,
                'final_image_path' => $finalImageName
            ]);

            return $finalImageName;

        } catch (\Exception $e) {
            Log::error('Error al generar imagen final del certificado', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * Añadir nombre del titular a la imagen
     *
     * @param mixed $image
     * @param string $name
     * @param array $position
     * @return void
     */
    private function addNameToImage($image, string $name, array $position): void
    {
        try {
            // Configuración por defecto
            $x = $position['x'] ?? 100;
            $y = $position['y'] ?? 100;
            $fontSize = $position['fontSize'] ?? 24;
            $fontFamily = $position['fontFamily'] ?? null;
            $fontWeight = $position['fontWeight'] ?? 'normal';
            $color = $position['color'] ?? '#000000';

            // Añadir texto a la imagen
            $image->text($name, $x, $y, function ($font) use ($fontSize, $fontFamily, $color) {
                $font->size($fontSize);
                $font->color($color);
                
                // Si se especifica una fuente, intentar usarla
                if ($fontFamily && $this->getFontPath($fontFamily)) {
                    $font->file($this->getFontPath($fontFamily));
                }
                
                $font->align('center');
                $font->valign('middle');
            });

            Log::info('Nombre añadido a la imagen del certificado', [
                'name' => $name,
                'position' => $position
            ]);

        } catch (\Exception $e) {
            Log::error('Error al añadir nombre a la imagen', [
                'name' => $name,
                'position' => $position,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Añadir código QR a la imagen
     *
     * @param mixed $image
     * @param string $qrImagePath
     * @param array $position
     * @return void
     */
    private function addQRToImage($image, string $qrImagePath, array $position): void
    {
        try {
            // Configuración por defecto
            $x = $position['x'] ?? 50;
            $y = $position['y'] ?? 50;
            $width = $position['width'] ?? 100;
            $height = $position['height'] ?? 100;

            // Si es SVG, convertir a imagen temporal para procesamiento
            if (pathinfo($qrImagePath, PATHINFO_EXTENSION) === 'svg') {
                // Para SVG, crear una imagen temporal usando el contenido SVG
                $svgContent = file_get_contents($qrImagePath);
                
                // Crear imagen temporal desde SVG usando Imagick si está disponible
                // Si no, usar una imagen placeholder o generar PNG directamente
                if (extension_loaded('imagick')) {
                    $imagick = new Imagick();
                    $imagick->readImageBlob($svgContent);
                    $imagick->setImageFormat('png');
                    $qrImage = $this->imageManager->read($imagick->getImageBlob());
                } else {
                    // Fallback: crear imagen placeholder o usar GD para procesar
                    Log::warning('SVG QR detectado pero Imagick no disponible, usando placeholder');
                    return; // Por ahora, saltar el QR si no se puede procesar
                }
            } else {
                // Cargar la imagen del QR
                $qrImage = $this->imageManager->read($qrImagePath);
            }
            
            // Redimensionar el QR si es necesario
            if ($width && $height) {
                $qrImage->resize($width, $height);
            }

            // Superponer el QR en la imagen principal
            $image->place($qrImage, 'top-left', $x, $y);

            Log::info('QR añadido a la imagen del certificado', [
                'qr_path' => $qrImagePath,
                'position' => $position
            ]);

        } catch (\Exception $e) {
            Log::error('Error al añadir QR a la imagen', [
                'qr_path' => $qrImagePath,
                'position' => $position,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener ruta de fuente personalizada
     *
     * @param string $fontFamily
     * @return string|null
     */
    private function getFontPath(string $fontFamily): ?string
    {
        $fontsPath = storage_path('app/fonts/');
        $fontFiles = [
            'arial' => 'arial.ttf',
            'times' => 'times.ttf',
            'helvetica' => 'helvetica.ttf',
            'courier' => 'courier.ttf'
        ];

        $fontKey = strtolower($fontFamily);
        if (isset($fontFiles[$fontKey])) {
            $fontPath = $fontsPath . $fontFiles[$fontKey];
            if (file_exists($fontPath)) {
                return $fontPath;
            }
        }

        return null;
    }

    /**
     * Limpiar archivos temporales de imágenes
     *
     * @param array $filePaths
     * @return void
     */
    public function cleanupTempFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                try {
                    unlink($filePath);
                    Log::info('Archivo temporal eliminado', ['path' => $filePath]);
                } catch (\Exception $e) {
                    Log::warning('No se pudo eliminar archivo temporal', [
                        'path' => $filePath,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
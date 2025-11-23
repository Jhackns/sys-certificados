<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
// Eliminada dependencia de Imagick

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

            // Asegurar relaciones necesarias
            $certificate->loadMissing(['template', 'user']);

            // Obtener la plantilla del certificado
            $template = $certificate->template;
            if (!$template || !$template->file_path) {
                Log::error('Plantilla no encontrada o sin imagen de fondo', [
                    'certificate_id' => $certificate->id,
                    'template_id' => $certificate->id_template
                ]);
                return null;
            }

            // Cargar la imagen de fondo de la plantilla (resolver ruta en disco público o local)
            $backgroundImagePath = $this->resolveBackgroundAbsolutePath($template->file_path);
            if (!$backgroundImagePath || !file_exists($backgroundImagePath)) {
                Log::error('Archivo de imagen de fondo no encontrado', [
                    'path' => $backgroundImagePath
                ]);
                return null;
            }

            $image = $this->imageManager->read($backgroundImagePath);
            Log::info('Datos de plantilla recibidos', [
                'name_position' => $template->name_position,
                'date_position' => $template->date_position,
                'qr_position' => $template->qr_position,
                'template_styles' => $template->template_styles,
                'background_image_size' => $template->background_image_size
            ]);

            // Dimensiones reales de la imagen y del lienzo del editor (si se guardó)
            $imgW = $image->width();
            $imgH = $image->height();
            
            // Obtener dimensiones del canvas del editor
            $editorCanvas = null;
            if (is_array($template->template_styles ?? null)) {
                $editorCanvas = $template->template_styles['editor_canvas_size'] ?? null;
            }
            
            $editorW = (int)($editorCanvas['width'] ?? 0);
            $editorH = (int)($editorCanvas['height'] ?? 0);

            // Si no hay dimensiones del editor, intentamos usar las dimensiones guardadas de la imagen de fondo
            // Esto es un fallback, pero lo ideal es que el frontend siempre mande el canvas size
            if ($editorW <= 0 || $editorH <= 0) {
                $bgSize = is_array($template->background_image_size ?? null) ? $template->background_image_size : null;
                $editorW = (int)($bgSize['width'] ?? $imgW);
                $editorH = (int)($bgSize['height'] ?? $imgH);
                Log::warning('No se encontró editor_canvas_size, usando fallback', [
                    'certificate_id' => $certificate->id,
                    'fallback_w' => $editorW,
                    'fallback_h' => $editorH
                ]);
            }

            // Calcular factores de escala
            // La lógica es: CoordenadaReal = CoordenadaEditor * (DimensionReal / DimensionEditor)
            $scaleX = ($editorW > 0) ? ($imgW / $editorW) : 1.0;
            $scaleY = ($editorH > 0) ? ($imgH / $editorH) : 1.0;
            
            // Factor uniforme para fuentes y elementos cuadrados (usamos el menor para asegurar que quepa, o promedio)
            // Generalmente para fuentes se usa el factor vertical si el texto es horizontal, pero un promedio es seguro
            $uniform = ($scaleX + $scaleY) / 2.0;

            $origin = strtolower((string)($template->template_styles['coords_origin'] ?? ''));
            $isCenter = ($origin === 'center');
            
            // Centro del editor para cálculos relativos al centro
            $centerX = $editorW / 2.0;
            $centerY = $editorH / 2.0;

            Log::info('Cálculo de escala para generación', [
                'image_real_size' => ['w' => $imgW, 'h' => $imgH],
                'editor_canvas_size' => ['w' => $editorW, 'h' => $editorH],
                'scale_factors' => ['x' => $scaleX, 'y' => $scaleY, 'uniform' => $uniform],
                'origin_mode' => $origin
            ]);

            // Aplicar desplazamiento de fondo (background_offset) ajustando las posiciones de overlays
            $offsetX = 0;
            $offsetY = 0;
            if (is_array($template->template_styles ?? null)) {
                $bgOffset = $template->template_styles['background_offset'] ?? null;
                if (is_array($bgOffset)) {
                    $offsetX = (int)($bgOffset['x'] ?? 0);
                    $offsetY = (int)($bgOffset['y'] ?? 0);
                }
            }

            if ($template->name_position) {
                $namePos = $this->normalizePosition($template->name_position, 'name');
                if (empty($namePos)) {
                    goto skip_name_overlay;
                }
                Log::info('Imagen overlay nombre (entrada)', [
                    'certificate_id' => $certificate->id,
                    'raw_pos_px' => $namePos,
                    'coords_origin' => $origin
                ]);
                
                // Determinar si usamos coordenadas absolutas (left/top) o relativas (x/y)
                $useAbsolute = isset($namePos['left']) && isset($namePos['top']);
                
                if ($useAbsolute) {
                    $rawX = (int)$namePos['left'];
                    $rawY = (int)$namePos['top'];
                } else {
                    $rawX = (int)($namePos['x'] ?? 0) + ($isCenter ? (int)round($centerX) : 0);
                    $rawY = (int)($namePos['y'] ?? 0) + ($isCenter ? (int)round($centerY) : 0);
                }

                $namePos['x'] = (int)round($rawX * $scaleX) - (int)round($offsetX * $scaleX);
                $namePos['y'] = (int)round($rawY * $scaleY) - (int)round($offsetY * $scaleY);
                
                if (isset($namePos['fontSize'])) {
                    $namePos['fontSize'] = (int)max(8, round(((int)$namePos['fontSize']) * $uniform));
                }
                // Usar el nombre del usuario seleccionado para el certificado
                $holderName = $certificate->user ? ($certificate->user->name ?? $certificate->nombre) : $certificate->nombre;
                Log::info('Imagen overlay nombre (final)', [
                    'computed_px' => $namePos,
                    'text' => $holderName
                ]);
                $this->addNameToImage($image, $holderName, $namePos);
            }
            skip_name_overlay:

            if ($template->date_position) {
                $datePos = $this->normalizePosition($template->date_position, 'date');
                if (empty($datePos)) {
                    goto skip_date_overlay;
                }
                Log::info('Imagen overlay fecha (entrada)', [
                    'certificate_id' => $certificate->id,
                    'raw_pos_px' => $datePos,
                    'coords_origin' => $origin
                ]);
                
                $useAbsolute = isset($datePos['left']) && isset($datePos['top']);
                
                if ($useAbsolute) {
                    $rawX = (int)$datePos['left'];
                    $rawY = (int)$datePos['top'];
                } else {
                    $rawX = (int)($datePos['x'] ?? 0) + ($isCenter ? (int)round($centerX) : 0);
                    $rawY = (int)($datePos['y'] ?? 0) + ($isCenter ? (int)round($centerY) : 0);
                }

                $datePos['x'] = (int)round($rawX * $scaleX) - (int)round($offsetX * $scaleX);
                $datePos['y'] = (int)round($rawY * $scaleY) - (int)round($offsetY * $scaleY);
                
                if (isset($datePos['fontSize'])) {
                    $datePos['fontSize'] = (int)max(8, round(((int)$datePos['fontSize']) * $uniform));
                }
                $dateText = $certificate->fecha_emision ? $certificate->fecha_emision->format('d/m/Y') : date('d/m/Y');
                Log::info('Imagen overlay fecha (final)', [
                    'computed_px' => $datePos,
                    'text' => $dateText
                ]);
                $this->addDateToImage($image, $dateText, $datePos);
            }
            skip_date_overlay:

            if ($template->qr_position) {
                $resolvedQrPath = $this->resolveQrAbsolutePath($qrImagePath);
                if ($resolvedQrPath && file_exists($resolvedQrPath) && filesize($resolvedQrPath) > 0) {
                    $qrPos = $this->normalizePosition($template->qr_position, 'qr');
                    if (empty($qrPos)) {
                        goto skip_qr_overlay;
                    }
                    Log::info('Imagen overlay QR (entrada)', [
                        'certificate_id' => $certificate->id,
                        'raw_pos_px' => $qrPos,
                        'resolved_qr_path' => $resolvedQrPath,
                        'coords_origin' => $origin
                    ]);
                    
                    $useAbsolute = isset($qrPos['left']) && isset($qrPos['top']);
                    
                    if ($useAbsolute) {
                        $rawX = (int)$qrPos['left'];
                        $rawY = (int)$qrPos['top'];
                    } else {
                        $rawX = (int)($qrPos['x'] ?? 0) + ($isCenter ? (int)round($centerX) : 0);
                        $rawY = (int)($qrPos['y'] ?? 0) + ($isCenter ? (int)round($centerY) : 0);
                    }

                    $qrPos['x'] = (int)round($rawX * $scaleX) - (int)round($offsetX * $scaleX);
                    $qrPos['y'] = (int)round($rawY * $scaleY) - (int)round($offsetY * $scaleY);
                    
                    if (isset($qrPos['width'])) {
                        $qrPos['width'] = (int)max(40, round(((int)$qrPos['width']) * $uniform));
                    }
                    if (isset($qrPos['height'])) {
                        // Preservar cuadrado
                        $qrPos['height'] = $qrPos['width'];
                    }
                    Log::info('Imagen overlay QR (final)', [
                        'computed_px' => $qrPos
                    ]);
                    $this->addQRToImage($image, $resolvedQrPath, $qrPos);
                } else {
                    Log::warning('No se pudo resolver la ruta absoluta del QR para superponer', [
                        'original_qr_path' => $qrImagePath,
                        'resolved_qr_path' => $resolvedQrPath,
                        'filesize' => $resolvedQrPath && file_exists($resolvedQrPath) ? filesize($resolvedQrPath) : null
                    ]);
                }
            }
            skip_qr_overlay:

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
                'final_image_path' => $finalImageName,
                'background_offset' => ['x' => $offsetX, 'y' => $offsetY]
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
     * Resolver ruta absoluta del fondo de plantilla cuando se guarda en disco público o local
     */
    private function resolveBackgroundAbsolutePath(?string $filePath): ?string
    {
        if (!$filePath) {
            return null;
        }

        // Si ya es absoluta (Unix o Windows), devolver tal cual
        // Unix absoluto: comienza con '/'
        // Windows absoluto: comienza con 'C:\\' (letra + ':' + backslash)
        if (str_starts_with($filePath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $filePath)) {
            return $filePath;
        }

        // Probar en storage/app/public
        $publicPath = storage_path('app/public/' . ltrim($filePath, '/'));
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Probar en storage/app
        $localPath = storage_path('app/' . ltrim($filePath, '/'));
        if (file_exists($localPath)) {
            return $localPath;
        }

        return null;
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
            $x = $position['x'] ?? 100;
            $y = $position['y'] ?? 100;
            $fontSize = $position['fontSize'] ?? 24;
            $fontFamily = $position['fontFamily'] ?? null;
            $fontWeight = $position['fontWeight'] ?? 'normal';
            $color = $position['color'] ?? '#000000';
            $align = strtolower($position['textAlign'] ?? 'left');
            $vAlign = strtolower($position['verticalAlign'] ?? 'top');
            $rotation = (float)($position['rotation'] ?? 0);
            $fontPath = $fontFamily ? $this->getFontVariantPath($fontFamily, $fontWeight, null) : null;
            $bbox = $this->measureTextBBoxRotated($name, (int)$fontSize, $fontPath, $rotation);
            $xs = [$bbox['min_x'] ?? 0, $bbox['max_x'] ?? 0];
            $ys = [$bbox['min_y'] ?? 0, $bbox['max_y'] ?? 0];
            $widthPx = $bbox['width'] ?? 0;
            $xDraw = $x - (int)round($bbox['min_x'] ?? 0);
            $yDraw = $y - (int)round($bbox['min_y'] ?? 0);
            $image->text($name, $xDraw, $yDraw, function ($font) use ($fontSize, $fontFamily, $fontWeight, $color, $rotation) {
                $font->size($fontSize);
                $font->color($color);
                $fontPath = $fontFamily ? $this->getFontVariantPath($fontFamily, $fontWeight, null) : null;
                if ($fontPath) {
                    $font->file($fontPath);
                }
                if (abs($rotation) > 0.01) {
                    $font->angle($rotation);
                }
            });

            $fontPathApplied = $fontFamily ? $this->getFontVariantPath($fontFamily, $fontWeight, null) : null;
            Log::info('Nombre añadido a la imagen del certificado', [
                'name' => $name,
                'position' => $position,
                'align' => $align,
                'vertical_align' => $vAlign,
                'x_expected' => $x,
                'x_drawn' => $xDraw,
                'text_width_px' => $widthPx,
                'y_expected' => $y,
                'y_drawn' => $yDraw,
                'bbox' => $bbox,
                'font_family' => $fontFamily,
                'font_weight' => $fontWeight,
                'font_size_px' => $fontSize,
                'font_path_applied' => $fontPathApplied
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
     * Añadir fecha a la imagen
     *
     * @param mixed $image
     * @param string $dateText
     * @param array $position
     * @return void
     */
    private function addDateToImage($image, string $dateText, array $position): void
    {
        try {
            $x = $position['x'] ?? 100;
            $y = $position['y'] ?? 100;
            $fontSize = $position['fontSize'] ?? 16;
            $fontFamily = $position['fontFamily'] ?? null;
            $color = $position['color'] ?? '#333333';
            $align = strtolower($position['textAlign'] ?? 'left');
            $vAlign = strtolower($position['verticalAlign'] ?? 'top');
            $fontWeight = $position['fontWeight'] ?? 'normal';
            $rotation = (float)($position['rotation'] ?? 0);
            $fontPath = $fontFamily ? $this->getFontVariantPath($fontFamily, $fontWeight, null) : null;
            $bbox = $this->measureTextBBoxRotated($dateText, (int)$fontSize, $fontPath, $rotation);
            $widthPx = $bbox['width'] ?? 0;
            $xDraw = $x - (int)round($bbox['min_x'] ?? 0);
            $yDraw = $y - (int)round($bbox['min_y'] ?? 0);
            $image->text($dateText, $xDraw, $yDraw, function ($font) use ($fontSize, $fontFamily, $fontWeight, $color, $rotation) {
                $font->size($fontSize);
                $font->color($color);
                $fontPath = $fontFamily ? $this->getFontVariantPath($fontFamily, $fontWeight, null) : null;
                if ($fontPath) {
                    $font->file($fontPath);
                }
                if (abs($rotation) > 0.01) {
                    $font->angle($rotation);
                }
            });

            $fontPathApplied = $fontFamily ? $this->getFontVariantPath($fontFamily, $fontWeight, null) : null;
            Log::info('Fecha añadida a la imagen del certificado', [
                'date' => $dateText,
                'position' => $position,
                'align' => $align,
                'vertical_align' => $vAlign,
                'x_expected' => $x,
                'x_drawn' => $xDraw,
                'text_width_px' => $widthPx,
                'y_expected' => $y,
                'y_drawn' => $yDraw,
                'bbox' => $bbox,
                'font_family' => $fontFamily,
                'font_weight' => $fontWeight,
                'font_size_px' => $fontSize,
                'font_path_applied' => $fontPathApplied
            ]);
        } catch (\Exception $e) {
            Log::error('Error al añadir fecha a la imagen', [
                'date' => $dateText,
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
            $rotation = (float)($position['rotation'] ?? 0);

            // Cargar la imagen del QR (se espera PNG)
            if (strtolower(pathinfo($qrImagePath, PATHINFO_EXTENSION)) !== 'png') {
                Log::warning('Formato de QR no soportado para superposición (se espera PNG)', [
                    'qr_path' => $qrImagePath,
                    'ext' => pathinfo($qrImagePath, PATHINFO_EXTENSION)
                ]);
                return;
            }

            $this->makeWhiteTransparentStrong($qrImagePath);
            $qrImage = $this->imageManager->read($qrImagePath);

            // Redimensionar el QR si es necesario
            if ($width && $height) {
                $qrImage->resize($width, $height);
            }

            // Rotar el QR si corresponde
            if (abs($rotation) > 0.01) {
                $qrImage->rotate($rotation);
            }

            // Para eliminar halos/blancos tras rotación/redimensionado, volcar a PNG temporal, aplicar transparencia fuerte y volver a cargar
            $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('qr_tmp_' . uniqid() . '.png');
            try {
                $qrImage->save($tmpPath);
                $this->makeWhiteTransparentStrong($tmpPath);
                $this->trimTransparentBorders($tmpPath);
                $qrImage = $this->imageManager->read($tmpPath);
            } catch (\Throwable $e) {
                // Si falla, continuar con la imagen en memoria
            } finally {
                if (isset($tmpPath) && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
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
     * Convertir fondo blanco del PNG a transparente para evitar cuadro visible
     */
    private function makeWhiteTransparent(string $path): void
    {
        try {
            $im = @imagecreatefrompng($path);
            if (!$im) return;
            imagesavealpha($im, true);
            $white = imagecolorallocate($im, 255, 255, 255);
            // Convertir a paleta para que imagecolortransparent funcione
            imagetruecolortopalette($im, true, 256);
            imagecolortransparent($im, $white);
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            if ($data) {
                file_put_contents($path, $data);
            }
        } catch (\Throwable $e) {
            // Ignorar si no se puede convertir
        }
    }

    private function makeWhiteTransparentStrong(string $path): void
    {
        try {
            $im = @imagecreatefrompng($path);
            if (!$im) return;
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($im);
            }
            imagesavealpha($im, true);
            $w = imagesx($im);
            $h = imagesy($im);
            $transparent = imagecolorallocatealpha($im, 255, 255, 255, 127);
            $threshold = 240;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgb = imagecolorat($im, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                        imagesetpixel($im, $x, $y, $transparent);
                    }
                }
            }
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            if ($data) {
                file_put_contents($path, $data);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Recortar bordes totalmente transparentes para eliminar halo exterior
     */
    private function trimTransparentBorders(string $path): void
    {
        try {
            $im = @imagecreatefrompng($path);
            if (!$im) return;
            imagesavealpha($im, true);
            $w = imagesx($im);
            $h = imagesy($im);
            $minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgba = imagecolorsforindex($im, imagecolorat($im, $x, $y));
                    if (($rgba['alpha'] ?? 0) < 120) { // pixel visible
                        if ($x < $minX) $minX = $x;
                        if ($y < $minY) $minY = $y;
                        if ($x > $maxX) $maxX = $x;
                        if ($y > $maxY) $maxY = $y;
                    }
                }
            }
            if ($maxX > $minX && $maxY > $minY) {
                $cropW = $maxX - $minX + 1;
                $cropH = $maxY - $minY + 1;
                $cropped = imagecrop($im, ['x' => $minX, 'y' => $minY, 'width' => $cropW, 'height' => $cropH]);
                if ($cropped) {
                    ob_start();
                    imagepng($cropped);
                    $data = ob_get_clean();
                    imagedestroy($cropped);
                    if ($data) file_put_contents($path, $data);
                }
            }
            imagedestroy($im);
        } catch (\Throwable $e) {
        }
    }

    private function normalizePosition($pos, string $type): array
    {
        $data = [];
        if (is_string($pos)) {
            $decoded = json_decode($pos, true);
            if (is_array($decoded)) {
                $pos = $decoded;
            } else {
                $pos = [];
            }
        }
        if (is_array($pos)) {
            $map = function ($arr, $from, $to) {
                if (array_key_exists($from, $arr) && !array_key_exists($to, $arr)) {
                    $arr[$to] = $arr[$from];
                }
                return $arr;
            };
            $p = $pos;
            $p = $map($p, 'font_size', 'fontSize');
            $p = $map($p, 'font_family', 'fontFamily');
            $p = $map($p, 'colour', 'color');
            $data['x'] = isset($p['x']) ? (int)$p['x'] : null;
            $data['y'] = isset($p['y']) ? (int)$p['y'] : null;
            // Add support for absolute coords
            if (isset($p['left'])) $data['left'] = (int)$p['left'];
            if (isset($p['top'])) $data['top'] = (int)$p['top'];

            if ($type === 'qr') {
                if (isset($p['width'])) $data['width'] = (int)$p['width'];
                if (isset($p['height'])) $data['height'] = (int)$p['height'];
                if (isset($p['rotation'])) $data['rotation'] = (int)$p['rotation'];
            } else {
                if (isset($p['fontSize'])) $data['fontSize'] = (int)$p['fontSize'];
                if (isset($p['fontFamily'])) $data['fontFamily'] = (string)$p['fontFamily'];
                if (isset($p['color'])) $data['color'] = (string)$p['color'];
                if (isset($p['rotation'])) $data['rotation'] = (int)$p['rotation'];
                if (isset($p['textAlign'])) $data['textAlign'] = (string)$p['textAlign'];
                if (isset($p['fontWeight'])) $data['fontWeight'] = (string)$p['fontWeight'];
                if (isset($p['fontStyle'])) $data['fontStyle'] = (string)$p['fontStyle'];
            }
        }
        foreach ($data as $k => $v) {
            if ($v === null) unset($data[$k]);
        }
        return $data;
    }

    /**
     * Resolver ruta absoluta del QR cuando se guarda en el disco público
     */
    private function resolveQrAbsolutePath(?string $qrImagePath): ?string
    {
        if (!$qrImagePath) {
            return null;
        }

        // Si ya es absoluta, devolver tal cual
        if (str_starts_with($qrImagePath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/',$qrImagePath)) {
            return $qrImagePath;
        }

        // Probar en storage/app/public
        $publicPath = storage_path('app/public/' . ltrim($qrImagePath, '/'));
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Probar en storage/app
        $localPath = storage_path('app/' . ltrim($qrImagePath, '/'));
        if (file_exists($localPath)) {
            return $localPath;
        }

        return null;
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
        $fontsPublicPath = storage_path('app/public/fonts/');

        // Normalizar nombre
        $normalize = function ($name) {
            return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
        };
        $fontKey = $normalize($fontFamily);

        // 1) Intentar coincidencia directa por nombre de archivo
        $directCandidates = [
            $fontsPath . $fontFamily . '.ttf',
            $fontsPath . $fontFamily . '.otf',
            $fontsPublicPath . $fontFamily . '.ttf',
            $fontsPublicPath . $fontFamily . '.otf',
        ];
        foreach ($directCandidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        // 2) Coincidencia por nombre normalizado con todos los .ttf/.otf en la carpeta
        $files = array_merge(
            glob($fontsPath . '*.ttf') ?: [],
            glob($fontsPath . '*.otf') ?: [],
            glob($fontsPublicPath . '*.ttf') ?: [],
            glob($fontsPublicPath . '*.otf') ?: []
        );
        foreach ($files as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            if ($normalize($base) === $fontKey) {
                return $file;
            }
            // También probar coincidencia simple sin normalizar exacta
            if (strtolower($base) === strtolower($fontFamily)) {
                return $file;
            }
        }

        // 3) Fallback: intentar fuentes comunes en carpeta local o sistema (Windows)
        $fallbacks = [
            $fontsPath . 'arial.ttf',
            $fontsPath . 'helvetica.ttf',
            $fontsPublicPath . 'arial.ttf',
            $fontsPublicPath . 'helvetica.ttf',
            // Windows system fonts
            'C:\\Windows\\Fonts\\arial.ttf',
            'C:\\Windows\\Fonts\\ARIAL.TTF',
            'C:\\Windows\\Fonts\\verdana.ttf',
            'C:\\Windows\\Fonts\\times.ttf',
            'C:\\Windows\\Fonts\\times.ttf',
            'C:\\Windows\\Fonts\\timesbd.ttf',
            'C:\\Windows\\Fonts\\timesi.ttf',
            'C:\\Windows\\Fonts\\cour.ttf',
            'C:\\Windows\\Fonts\\courbd.ttf'
        ];
        foreach ($fallbacks as $fallbackPath) {
            if (file_exists($fallbackPath)) {
                Log::warning('Fuente no encontrada, usando fallback', [
                    'requested' => $fontFamily,
                    'fallback' => $fallbackPath
                ]);
                return $fallbackPath;
            }
        }

        Log::warning('No se encontró ninguna fuente válida', ['requested' => $fontFamily]);
        return null;
    }

    /**
     * Obtener ruta de fuente considerando variantes de peso/estilo
     */
    private function getFontVariantPath(string $fontFamily, ?string $fontWeight, ?string $fontStyle): ?string
    {
        $weight = strtolower($fontWeight ?? 'normal');
        $style = strtolower($fontStyle ?? 'normal');
        $family = strtolower($fontFamily);

        // Calcular clave de variante
        $key = $weight === 'bold' && $style === 'italic' ? 'bolditalic' : ($weight === 'bold' ? 'bold' : ($style === 'italic' ? 'italic' : 'normal'));

        // Preferir en storage/fonts
        $storageFonts = [
            $family . ($key === 'normal' ? '' : '-' . $key) . '.ttf',
            $family . ($key === 'normal' ? '' : '-' . $key) . '.otf',
            $family . '.ttf',
            $family . '.otf',
        ];
        foreach ($storageFonts as $fname) {
            $path1 = storage_path('app/fonts/' . $fname);
            if (file_exists($path1)) return $path1;
            $path2 = storage_path('app/public/fonts/' . $fname);
            if (file_exists($path2)) return $path2;
        }

        // Intentar variantes comunes en Windows
        $winFonts = [
            'arial' => [
                'normal' => 'C:\\Windows\\Fonts\\arial.ttf',
                'bold' => 'C:\\Windows\\Fonts\\arialbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\ariali.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\arialbi.ttf',
            ],
            'comic sans ms' => [
                'normal' => 'C:\\Windows\\Fonts\\comic.ttf',
                'bold' => 'C:\\Windows\\Fonts\\comicbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\comici.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\comicbi.ttf',
            ],
            'courier new' => [
                'normal' => 'C:\\Windows\\Fonts\\cour.ttf',
                'bold' => 'C:\\Windows\\Fonts\\courbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\couri.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\courbi.ttf',
            ],
            'calibri' => [
                'normal' => 'C:\\Windows\\Fonts\\calibri.ttf',
                'bold' => 'C:\\Windows\\Fonts\\calibrib.ttf',
                'italic' => 'C:\\Windows\\Fonts\\calibrii.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\calibriz.ttf',
            ],
            'georgia' => [
                'normal' => 'C:\\Windows\\Fonts\\georgia.ttf',
                'bold' => 'C:\\Windows\\Fonts\\georgiab.ttf',
                'italic' => 'C:\\Windows\\Fonts\\georgiai.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\georgiaz.ttf',
            ],
            'times new roman' => [
                'normal' => 'C:\\Windows\\Fonts\\times.ttf',
                'bold' => 'C:\\Windows\\Fonts\\timesbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\timesi.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\timesbi.ttf',
            ],
            'times' => [
                'normal' => 'C:\\Windows\\Fonts\\times.ttf',
                'bold' => 'C:\\Windows\\Fonts\\timesbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\timesi.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\timesbi.ttf',
            ],
            'courier' => [
                'normal' => 'C:\\Windows\\Fonts\\cour.ttf',
                'bold' => 'C:\\Windows\\Fonts\\courbd.ttf',
                'italic' => 'C:\\Windows\\Fonts\\couri.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\courbi.ttf',
            ],
            'verdana' => [
                'normal' => 'C:\\Windows\\Fonts\\verdana.ttf',
                'bold' => 'C:\\Windows\\Fonts\\verdanab.ttf',
                'italic' => 'C:\\Windows\\Fonts\\verdanai.ttf',
                'bolditalic' => 'C:\\Windows\\Fonts\\verdanaz.ttf',
            ],
        ];
        if (isset($winFonts[$family][$key]) && file_exists($winFonts[$family][$key])) {
            return $winFonts[$family][$key];
        }

        $simple = $this->getFontPath($fontFamily);
        if ($simple) return $simple;
        $arial = 'C:\\Windows\\Fonts\\arial.ttf';
        if (file_exists($arial)) return $arial;
        return null;
    }

    /**
     * Medir ancho aproximado de texto en píxeles usando GD
     */
    private function measureTextWidthPx(string $text, int $fontSize, ?string $fontPath): int
    {
        if (!$fontPath || !file_exists($fontPath) || $fontSize <= 0 || $text === '') {
            return 0;
        }
        try {
            $bbox = \imagettfbbox($fontSize, 0, $fontPath, $text);
            if (is_array($bbox) && count($bbox) >= 8) {
                $width = abs($bbox[2] - $bbox[0]);
                return (int)max(0, round($width));
            }
        } catch (\Throwable $e) {
            // Ignorar errores de medición
        }
        return 0;
    }

    private function measureTextWidthPxRotated(string $text, int $fontSize, ?string $fontPath, float $angle): int
    {
        if (!$fontPath || !file_exists($fontPath) || $fontSize <= 0 || $text === '') {
            return 0;
        }
        try {
            $bbox = \imagettfbbox($fontSize, $angle, $fontPath, $text);
            if (is_array($bbox) && count($bbox) >= 8) {
                $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
                $minX = min($xs);
                $maxX = max($xs);
                $width = abs($maxX - $minX);
                return (int)max(0, round($width));
            }
        } catch (\Throwable $e) {
        }
        return 0;
    }

    private function measureTextBBoxRotated(string $text, int $fontSize, ?string $fontPath, float $angle): array
    {
        $out = ['width' => 0, 'height' => 0, 'center_y' => 0, 'min_y' => null, 'max_y' => null];
        if (!$fontPath || !file_exists($fontPath) || $fontSize <= 0 || $text === '') {
            return $out;
        }
        try {
            $bbox = \imagettfbbox($fontSize, $angle, $fontPath, $text);
            if (is_array($bbox) && count($bbox) >= 8) {
                $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
                $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];
                $minX = min($xs); $maxX = max($xs);
                $minY = min($ys); $maxY = max($ys);
                $out['width'] = (int)max(0, round(abs($maxX - $minX)));
                $out['height'] = (int)max(0, round(abs($maxY - $minY)));
                $out['center_y'] = ($minY + $maxY) / 2.0;
                $out['min_y'] = $minY;
                $out['max_y'] = $maxY;
            }
        } catch (\Throwable $e) {
        }
        return $out;
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

<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CertificateFileService
{
    /**
     * Subir una plantilla global de certificado
     */
    public function uploadGlobalTemplate(UploadedFile $file, string $templateName): string
    {
        $fileName = $this->generateFileName($file, $templateName);
        $path = "plantillas_globales/{$fileName}";

        Storage::disk('certificates')->put($path, file_get_contents($file));

        return $path;
    }

    /**
     * Subir un certificado de usuario
     */
    public function uploadUserCertificate(UploadedFile $file, int $userId, string $type = 'pdfs'): string
    {
        $year = date('Y');
        $month = date('m');
        $fileName = $this->generateFileName($file);

        $path = "usuarios/{$userId}/{$type}/{$year}/{$month}/{$fileName}";

        Storage::disk('certificates')->put($path, file_get_contents($file));

        return $path;
    }

    /**
     * Obtener la URL pública de un archivo
     */
    public function getPublicUrl(string $path): string
    {
        $p = str_replace('\\','/', ltrim($path, '/'));
        if (preg_match('/^https?:\/\//i', $path)) {
            Log::info('Ruta ya es URL', ['url' => $path]);
            return $path;
        }
        Log::info('Normalizando ruta de archivo', ['input' => $path, 'normalized' => $p]);
        if (Storage::disk('public')->exists($p)) {
            $url = asset('storage/' . $p);

            Log::info('URL pública desde public', ['path' => $p, 'url' => $url]);
            return $url;
        }
        if (Storage::disk('certificates')->exists($p)) {
            $url = asset('storage/certificates/' . $p);
            Log::info('URL pública desde certificates', ['path' => $p, 'url' => $url]);
            return $url;
        }
        $publicAbs = storage_path('app/public/' . $p);
        if (file_exists($publicAbs)) {
            $url = asset('storage/' . $p);
            Log::info('URL pública desde path físico public', ['path' => $publicAbs, 'url' => $url]);
            return $url;
        }
        $certAbs = storage_path('app/public/certificates/' . $p);
        if (file_exists($certAbs)) {
            $url = asset('storage/certificates/' . $p);
            Log::info('URL pública desde path físico certificates', ['path' => $certAbs, 'url' => $url]);
            return $url;
        }
        $fallback = asset('storage/' . $p);
        Log::warning('URL pública por fallback', ['path' => $p, 'url' => $fallback]);
        return $fallback;
    }

    /**
     * Eliminar un archivo
     */
    public function deleteFile(string $path): bool
    {
        $p = ltrim($path, '/');
        if (Storage::disk('certificates')->exists($p)) {
            return Storage::disk('certificates')->delete($p);
        }
        if (Storage::disk('public')->exists($p)) {
            return Storage::disk('public')->delete($p);
        }
        Log::warning('Eliminar archivo: no existe en ninguno de los discos', ['path' => $p]);
        return false;
    }

    /**
     * Verificar si un archivo existe
     */
    public function fileExists(string $path): bool
    {
        $p = ltrim($path, '/');
        return Storage::disk('certificates')->exists($p) || Storage::disk('public')->exists($p);
    }

    /**
     * Obtener el tamaño de un archivo en bytes
     */
    public function getFileSize(string $path): int
    {
        return Storage::disk('certificates')->size($path);
    }

    /**
     * Generar un nombre único para el archivo
     */
    private function generateFileName(UploadedFile $file, string $prefix = null): string
    {
        $extension = $file->getClientOriginalExtension();
        $baseName = $prefix ? Str::slug($prefix) : Str::random(10);
        $timestamp = time();

        return "{$baseName}_{$timestamp}.{$extension}";
    }

    /**
     * Validar que el archivo sea una imagen válida
     */
    public function validateImageFile(UploadedFile $file): array
    {
        $errors = [];

        // Validar tipo de archivo
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            $errors[] = 'El archivo debe ser una imagen (JPEG, PNG, JPG, GIF)';
        }

        // Validar tamaño (máximo 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB en bytes
        if ($file->getSize() > $maxSize) {
            $errors[] = 'El archivo no debe superar los 5MB';
        }

        return $errors;
    }

    /**
     * Validar que el archivo sea un PDF válido
     */
    public function validatePdfFile(UploadedFile $file): array
    {
        $errors = [];

        // Validar tipo de archivo
        if ($file->getMimeType() !== 'application/pdf') {
            $errors[] = 'El archivo debe ser un PDF';
        }

        // Validar tamaño (máximo 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB en bytes
        if ($file->getSize() > $maxSize) {
            $errors[] = 'El archivo no debe superar los 10MB';
        }

        return $errors;
    }

    /**
     * Crear la estructura de directorios para un usuario
     */
    public function createUserDirectories(int $userId): void
    {
        $year = date('Y');
        $month = date('m');

        $directories = [
            "usuarios/{$userId}/pdfs/{$year}/{$month}",
            "usuarios/{$userId}/imagenes/{$year}/{$month}"
        ];

        foreach ($directories as $directory) {
            if (!Storage::disk('certificates')->exists($directory)) {
                Storage::disk('certificates')->makeDirectory($directory);
            }
        }
    }
}

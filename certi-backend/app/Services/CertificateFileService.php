<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        return Storage::disk('certificates')->url($path);
    }

    /**
     * Eliminar un archivo
     */
    public function deleteFile(string $path): bool
    {
        return Storage::disk('certificates')->delete($path);
    }

    /**
     * Verificar si un archivo existe
     */
    public function fileExists(string $path): bool
    {
        return Storage::disk('certificates')->exists($path);
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
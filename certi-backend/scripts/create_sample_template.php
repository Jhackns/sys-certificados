<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

echo "=== CREANDO PLANTILLA DE MUESTRA ===\n";

try {
    // Crear directorio si no existe
    $templateDir = storage_path('app/plantillas_globales');
    if (!is_dir($templateDir)) {
        mkdir($templateDir, 0755, true);
        echo "Directorio creado: $templateDir\n";
    }

    // Crear imagen de muestra
    $imageManager = new ImageManager(new Driver());

    // Crear una imagen de 800x600 con fondo blanco
    $image = $imageManager->create(800, 600)->fill('ffffff');

    // Añadir texto de muestra
    $image->text('CERTIFICADO DE PARTICIPACIÓN', 400, 150, function ($font) {
        $font->size(32);
        $font->color('000000');
        $font->align('center');
        $font->valign('middle');
    });

    $image->text('Se certifica que', 400, 250, function ($font) {
        $font->size(20);
        $font->color('000000');
        $font->align('center');
        $font->valign('middle');
    });

    $image->text('[NOMBRE DEL PARTICIPANTE]', 400, 300, function ($font) {
        $font->size(24);
        $font->color('0066cc');
        $font->align('center');
        $font->valign('middle');
    });

    $image->text('Ha completado exitosamente el curso de Python', 400, 350, function ($font) {
        $font->size(18);
        $font->color('000000');
        $font->align('center');
        $font->valign('middle');
    });

    // Añadir marcadores para QR y nombre
    $image->text('QR', 650, 450, function ($font) {
        $font->size(16);
        $font->color('cccccc');
        $font->align('center');
        $font->valign('middle');
    });

    // Guardar la imagen
    $templatePath = $templateDir . '/python_1758673742.jpeg';
    $image->save($templatePath, 90, 'jpg');

    echo "Plantilla creada exitosamente: $templatePath\n";
    echo "Tamaño del archivo: " . filesize($templatePath) . " bytes\n";

    // Verificar que el archivo existe
    if (file_exists($templatePath)) {
        echo "✅ Archivo verificado correctamente\n";
    } else {
        echo "❌ Error: El archivo no se pudo crear\n";
    }

} catch (Exception $e) {
    echo "❌ Error creando plantilla: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== SCRIPT COMPLETADO ===\n";

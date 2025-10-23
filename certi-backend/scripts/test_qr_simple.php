<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

// Inicializar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=== PRUEBA SIMPLE DE GENERACIÓN QR ===\n";

try {
    echo "Probando generación de QR básica...\n";

    // Probar generación simple
    $testUrl = 'https://example.com/test';

    echo "URL de prueba: {$testUrl}\n";

    // Generar QR simple usando SVG (no requiere extensiones de imagen)
    $qrCode = QrCode::format('svg')
        ->size(200)
        ->generate($testUrl);

    echo "✅ QR SVG generado exitosamente!\n";
    echo "Tamaño del QR: " . strlen($qrCode) . " bytes\n";

    // Guardar archivo de prueba SVG
    $testPath = storage_path('app/public/test_qr.svg');
    file_put_contents($testPath, $qrCode);

    echo "✅ Archivo SVG guardado en: {$testPath}\n";
    echo "Archivo existe: " . (file_exists($testPath) ? "Sí" : "No") . "\n";

    // Ahora probar PNG con GD
    echo "\nProbando PNG con GD...\n";
    $qrCodePng = QrCode::format('png')
        ->size(200)
        ->generate($testUrl);

    echo "✅ QR PNG generado exitosamente!\n";

    // Guardar archivo PNG
    $testPathPng = storage_path('app/public/test_qr.png');
    file_put_contents($testPathPng, $qrCodePng);

    echo "✅ Archivo PNG guardado en: {$testPathPng}\n";
    echo "Archivo PNG existe: " . (file_exists($testPathPng) ? "Sí" : "No") . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== SCRIPT COMPLETADO ===\n";

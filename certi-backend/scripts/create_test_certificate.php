<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use App\Services\CertificateService;
use App\Models\User;
use App\Models\Activity;
use App\Models\CertificateTemplate;

// Inicializar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=== CREANDO CERTIFICADO DE PRUEBA ===\n";

try {
    // Obtener datos necesarios
    $user = User::find(6); // Harry Jack Ascuña Mamani
    $activity = Activity::find(2); // Logro
    $template = CertificateTemplate::find(2); // Python (tiene posiciones configuradas)
    $signer = User::find(3); // Juan Carlos Emisor

    if (!$user || !$activity || !$template || !$signer) {
        throw new Exception("No se encontraron los datos necesarios");
    }

    echo "Usuario: {$user->name}\n";
    echo "Actividad: {$activity->name}\n";
    echo "Plantilla: {$template->name}\n";
    echo "Firmante: {$signer->name}\n";

    // Crear el servicio de certificados
    $certificateService = app(CertificateService::class);

    // Datos del certificado
    $certificateData = [
        'user_id' => $user->id,
        'activity_id' => $activity->id,
        'id_template' => $template->id,
        'signed_by' => $signer->id,
        'nombre' => $user->name,
        'descripcion' => 'Certificado de prueba para validar funcionalidad QR',
        'fecha_emision' => now()->format('Y-m-d'),
        'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
        'status' => 'active'
    ];

    echo "\n=== CREANDO CERTIFICADO ===\n";
    $certificate = $certificateService->create($certificateData);

    echo "✅ Certificado creado exitosamente!\n";
    echo "ID: {$certificate->id}\n";
    echo "Código único: {$certificate->unique_code}\n";
    echo "Código de verificación: {$certificate->verification_code}\n";
    echo "URL de verificación: {$certificate->verification_url}\n";
    echo "Ruta imagen QR: {$certificate->qr_image_path}\n";
    echo "Ruta imagen final: {$certificate->final_image_path}\n";

    // Verificar archivos generados
    echo "\n=== VERIFICANDO ARCHIVOS GENERADOS ===\n";

    if ($certificate->qr_image_path) {
        $qrPath = storage_path('app/public/' . $certificate->qr_image_path);
        echo "Archivo QR: " . ($qrPath && file_exists($qrPath) ? "✅ Existe" : "❌ No existe") . "\n";
        echo "Ruta QR: {$qrPath}\n";
    }

    if ($certificate->final_image_path) {
        $finalPath = storage_path('app/public/' . $certificate->final_image_path);
        echo "Imagen final: " . ($finalPath && file_exists($finalPath) ? "✅ Existe" : "❌ No existe") . "\n";
        echo "Ruta final: {$finalPath}\n";
    }

    echo "\n=== PROBANDO VERIFICACIÓN WEB ===\n";
    echo "URL de verificación: {$certificate->verification_url}\n";
    echo "Código para verificar manualmente: {$certificate->verification_code}\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== SCRIPT COMPLETADO ===\n";

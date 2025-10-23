<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICANDO PLANTILLA DE CERTIFICADO ===\n";

$template = App\Models\CertificateTemplate::find(2);

if ($template) {
    echo "Template ID: " . $template->id . "\n";
    echo "Name: " . $template->name . "\n";
    echo "File Path: " . $template->file_path . "\n";
    echo "Full Path: " . storage_path('app/' . $template->file_path) . "\n";
    echo "File exists: " . (file_exists(storage_path('app/' . $template->file_path)) ? 'Yes' : 'No') . "\n";

    // Verificar si existe en public
    $publicPath = storage_path('app/public/' . $template->file_path);
    echo "Public Path: " . $publicPath . "\n";
    echo "Public File exists: " . (file_exists($publicPath) ? 'Yes' : 'No') . "\n";

    // Verificar directorio
    $directory = dirname(storage_path('app/' . $template->file_path));
    echo "Directory: " . $directory . "\n";
    echo "Directory exists: " . (is_dir($directory) ? 'Yes' : 'No') . "\n";

    if (is_dir($directory)) {
        echo "Directory contents:\n";
        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - " . $file . "\n";
            }
        }
    }
} else {
    echo "Template not found!\n";
}

echo "\n=== SCRIPT COMPLETADO ===\n";

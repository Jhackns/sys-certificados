<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Inicializar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Verificar plantillas disponibles
echo "=== VERIFICANDO PLANTILLAS DISPONIBLES ===\n";
$templates = App\Models\CertificateTemplate::select('id', 'name', 'qr_position', 'name_position')->get();
echo "Total de plantillas: " . $templates->count() . "\n";

foreach ($templates as $template) {
    echo "\nID: {$template->id}";
    echo "\nNombre: {$template->name}";
    echo "\nPosición QR: " . json_encode($template->qr_position);
    echo "\nPosición Nombre: " . json_encode($template->name_position);
    echo "\n" . str_repeat('-', 50) . "\n";
}

// Verificar actividades disponibles
echo "\n=== VERIFICANDO ACTIVIDADES DISPONIBLES ===\n";
$activities = App\Models\Activity::select('id', 'name')->get();
echo "Total de actividades: " . $activities->count() . "\n";

foreach ($activities as $activity) {
    echo "ID: {$activity->id}, Nombre: {$activity->name}\n";
}

// Verificar usuarios disponibles
echo "\n=== VERIFICANDO USUARIOS DISPONIBLES ===\n";
$users = App\Models\User::select('id', 'name', 'email')->get();
echo "Total de usuarios: " . $users->count() . "\n";

foreach ($users as $user) {
    echo "ID: {$user->id}, Nombre: {$user->name}, Email: {$user->email}\n";
}

echo "\n=== SCRIPT COMPLETADO ===\n";

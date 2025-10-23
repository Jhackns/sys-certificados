<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Certificate;

echo "Certificados en BD: " . Certificate::count() . PHP_EOL;

$certificates = Certificate::with(['user', 'activity', 'template', 'signer'])->take(5)->get();

foreach ($certificates as $cert) {
    echo "ID: " . $cert->id .
         ", User: " . ($cert->user ? $cert->user->id : 'NULL') .
         ", Activity: " . ($cert->activity ? $cert->activity->id : 'NULL') .
         ", Template: " . ($cert->template ? $cert->template->id : 'NULL') .
         ", Signer: " . ($cert->signer ? $cert->signer->id : 'NULL') .
         PHP_EOL;
}

// Verificar si hay certificados con relaciones nulas
$nullUsers = Certificate::whereNull('user_id')->count();
$nullActivities = Certificate::whereNull('activity_id')->count();
$nullTemplates = Certificate::whereNull('id_template')->count();

echo "Certificados con user_id NULL: " . $nullUsers . PHP_EOL;
echo "Certificados con activity_id NULL: " . $nullActivities . PHP_EOL;
echo "Certificados con id_template NULL: " . $nullTemplates . PHP_EOL;

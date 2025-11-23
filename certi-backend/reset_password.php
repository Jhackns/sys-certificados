<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking user...\n";

$email = 'superadmin@certificados.com';
$password = 'SuperAdmin123!';

$user = User::where('email', $email)->first();

if (!$user) {
    echo "User not found. Creating...\n";
    $user = new User();
    $user->name = 'Super Admin';
    $user->email = $email;
    $user->password = Hash::make($password);
    $user->save();
    
    // Assign role if possible
    try {
        $user->assignRole('super-admin');
        echo "Role assigned.\n";
    } catch (\Exception $e) {
        echo "Could not assign role: " . $e->getMessage() . "\n";
    }
    
    echo "User created.\n";
} else {
    echo "User found. Resetting password...\n";
    $user->password = Hash::make($password);
    $user->save();
    echo "Password reset.\n";
}

echo "Done.\n";

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user for development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Crear una empresa de prueba si no existe
        $company = Company::firstOrCreate(
            ['ruc' => '20123456789'],
            [
                'name' => 'Certificaciones Digitales S.A.C.',
                'email' => 'contacto@certificaciones.com',
                'phone' => '+51 987 654 321',
                'address' => 'Av. Principal 123, Lima',
                'status' => 'active'
            ]
        );

        // Crear usuario de prueba
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'email_verified_at' => now(),
                'status' => 'active'
            ]
        );

        $this->info("Usuario de prueba creado:");
        $this->info("Email: admin@example.com");
        $this->info("Password: password");
        $this->info("Empresa: {$company->name}");

        return 0;
    }
}

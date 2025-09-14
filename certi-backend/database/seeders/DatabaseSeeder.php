<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\CertificateTemplate;
use App\Models\Company;
use App\Models\EmailSend;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * El DatabaseSeeder es el seeder principal de Laravel que se encarga de poblar 
     * la base de datos con datos de prueba para la aplicación de certificados digitales.
     */
    public function run(): void
    {
        $this->command->info('Iniciando el seeding del sistema de certificados digitales...');
        $this->command->info('');

        // 1. Sistema de Roles y Permisos
        $this->createRolesAndPermissions();
        
        // 2. Usuarios de Prueba
        $this->createTestUsers();
        
        // 3. Datos Maestros del Sistema
        $this->createMasterData();
        
        // 4. Certificados y Datos Relacionados (solo en desarrollo)
        if (app()->environment(['local', 'development', 'testing'])) {
            $this->createCertificatesAndRelatedData();
        }

        $this->command->info('');
        $this->command->info('✅ Seeding completado exitosamente!');
    }

    /**
     * Sistema de Roles y Permisos
     * 
     * Crea un sistema completo de autorización con 5 roles principales 
     * (super_admin, administrador, emisor, validador, usuario_final) y más de 
     * 47 permisos granulares para gestionar usuarios, empresas, actividades, 
     * certificados, plantillas, validaciones, documentos, correos y reportes.
     */
    private function createRolesAndPermissions(): void
    {
        $this->command->info('× Creando sistema de roles y permisos...');

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos organizados por módulos
        $permissions = [
            // Módulo Usuarios
            'users.create', 'users.read', 'users.update', 'users.delete', 'users.assign_roles',
            
            // Módulo Roles y Permisos
            'roles.create', 'roles.read', 'roles.update', 'roles.delete', 'permissions.read', 'permissions.assign',
            
            // Módulo Empresas
            'companies.create', 'companies.read', 'companies.update', 'companies.delete', 'companies.manage_own',
            
            // Módulo Actividades
            'activities.create', 'activities.read', 'activities.update', 'activities.delete', 'activities.manage_own',
            
            // Módulo Certificados
            'certificates.create', 'certificates.read', 'certificates.update', 'certificates.delete', 
            'certificates.issue', 'certificates.revoke', 'certificates.validate', 'certificates.download', 'certificates.manage_own',
            
            // Módulo Plantillas
            'templates.create', 'templates.read', 'templates.update', 'templates.delete', 'templates.manage_own',
            
            // Módulo Validaciones
            'validations.read', 'validations.create', 'validations.manage_own',
            
            // Módulo Documentos
            'documents.upload', 'documents.download', 'documents.delete', 'documents.manage_own',
            
            // Módulo Correos
            'emails.send', 'emails.read', 'emails.resend', 'emails.manage_own',
            
            // Módulo Reportes
            'reports.certificates', 'reports.validations', 'reports.activities', 'reports.users', 'reports.companies', 'reports.export',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles y asignar permisos
        
        // 1. Super Admin - Acceso total al sistema
        $superAdminRole = Role::create(['name' => 'super_admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        // 2. Administrador - Gestión completa de su empresa
        $adminRole = Role::create(['name' => 'administrador']);
        $adminRole->givePermissionTo([
            'users.create', 'users.read', 'users.update', 'users.delete', 'users.assign_roles',
            'roles.read', 'permissions.read', 'permissions.assign',
            'companies.read', 'companies.update', 'companies.manage_own',
            'activities.create', 'activities.read', 'activities.update', 'activities.delete', 'activities.manage_own',
            'certificates.create', 'certificates.read', 'certificates.update', 'certificates.delete', 
            'certificates.issue', 'certificates.revoke', 'certificates.validate', 'certificates.download', 'certificates.manage_own',
            'templates.create', 'templates.read', 'templates.update', 'templates.delete', 'templates.manage_own',
            'validations.read', 'validations.create', 'validations.manage_own',
            'documents.upload', 'documents.download', 'documents.delete', 'documents.manage_own',
            'emails.send', 'emails.read', 'emails.resend', 'emails.manage_own',
            'reports.certificates', 'reports.validations', 'reports.activities', 'reports.users', 'reports.export',
        ]);

        // 3. Emisor - Puede emitir certificados y gestionar actividades
        $emisorRole = Role::create(['name' => 'emisor']);
        $emisorRole->givePermissionTo([
            'users.read', 'companies.read', 'companies.manage_own',
            'activities.create', 'activities.read', 'activities.update', 'activities.manage_own',
            'certificates.create', 'certificates.read', 'certificates.issue', 'certificates.download', 'certificates.manage_own',
            'templates.read', 'templates.manage_own', 'validations.read', 'validations.manage_own',
            'documents.upload', 'documents.download', 'documents.manage_own',
            'emails.send', 'emails.read', 'emails.resend', 'emails.manage_own',
            'reports.certificates', 'reports.activities',
        ]);

        // 4. Validador - Solo puede validar certificados
        $validadorRole = Role::create(['name' => 'validador']);
        $validadorRole->givePermissionTo([
            'certificates.read', 'certificates.validate', 'certificates.download',
            'validations.read', 'validations.create',
            'companies.read', 'activities.read',
        ]);

        // 5. Usuario Final - Solo puede ver sus propios certificados
        $usuarioFinalRole = Role::create(['name' => 'usuario_final']);
        $usuarioFinalRole->givePermissionTo([
            'certificates.read', 'certificates.download', 'certificates.manage_own',
            'validations.read', 'validations.manage_own',
            'documents.download', 'documents.manage_own',
        ]);

        $this->command->info('  × Roles y permisos creados correctamente');
    }

    /**
     * Usuarios de Prueba
     * 
     * Genera 5 usuarios predefinidos: un super administrador, un administrador, 
     * un emisor, un validador y un usuario final, cada uno con credenciales 
     * de acceso y perfiles completos.
     */
    private function createTestUsers(): void
    {
        $this->command->info('× Creando usuarios de prueba...');

        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Administrador',
            'email' => 'superadmin@certificados.com',
            'password' => Hash::make('SuperAdmin123!'),
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super_admin');

        // Administrador de empresa
        $admin = User::create([
            'name' => 'Administrador Principal',
            'email' => 'admin@certificaciones.com',
            'password' => Hash::make('Admin123!'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('administrador');

        // Emisor
        $emisor = User::create([
            'name' => 'Juan Carlos Emisor',
            'email' => 'emisor@certificaciones.com',
            'password' => Hash::make('Emisor123!'),
            'email_verified_at' => now(),
        ]);
        $emisor->assignRole('emisor');

        // Validador
        $validador = User::create([
            'name' => 'María Validadora',
            'email' => 'validador@certificaciones.com',
            'password' => Hash::make('Validador123!'),
            'email_verified_at' => now(),
        ]);
        $validador->assignRole('validador');

        // Usuario Final
        $usuarioFinal = User::create([
            'name' => 'Pedro Estudiante',
            'email' => 'estudiante@ejemplo.com',
            'password' => Hash::make('Usuario123!'),
            'email_verified_at' => now(),
        ]);
        $usuarioFinal->assignRole('usuario_final');

        $this->command->info('  × Usuarios de prueba creados correctamente');
    }

    /**
     * Datos Maestros del Sistema
     * 
     * Crea las empresas adicionales, actividades base y plantillas de certificados
     * necesarias para el funcionamiento del sistema.
     */
    private function createMasterData(): void
    {
        $this->command->info('× Creando datos maestros del sistema...');

        // Elimina la creación de empresas adicionales
        // $companies = Company::factory(3)->create();

        // Elimina la creación de actividades y plantillas por empresa
        // Crea actividades y plantillas globales
        Activity::factory(6)->create();
        CertificateTemplate::factory(2)->create();

        $this->command->info('  × Datos maestros creados correctamente');
    }

    /**
     * Certificados y Datos Relacionados
     * 
     * Genera certificados de ejemplo con sus documentos, validaciones y 
     * envíos de correo asociados para demostrar el funcionamiento completo del sistema.
     */
    private function createCertificatesAndRelatedData(): void
    {
        $this->command->info('× Creando certificados y datos relacionados...');

        $activities = Activity::all();
        $finalUsers = User::whereNull('company_id')->get();
        $signers = User::whereNotNull('company_id')->get();

        // Eliminar la creación de usuarios finales adicionales
        // User::factory(15)->withoutCompany()->create();
        // $finalUsers = User::whereNull('company_id')->get();

        foreach ($activities as $activity) {
            $certificateCount = rand(8, 20);
            
            for ($i = 0; $i < $certificateCount; $i++) {
                $finalUser = $finalUsers->random();
                $signer = $signers->where('company_id', $activity->company_id)->first() ?? $signers->random();
                
                $certificate = Certificate::factory()
                    ->forActivity($activity)
                    ->forUser($finalUser)
                    ->signedBy($signer)
                    ->create();

                // Crear documentos (80% probabilidad)
                if (rand(1, 100) <= 80) {
                    CertificateDocument::factory()
                        ->forCertificate($certificate)
                        ->pdf()
                        ->create();
                }

                // Crear validaciones (50% probabilidad)
                if (rand(1, 100) <= 50) {
                    Validation::factory(rand(1, 4))
                        ->forCertificate($certificate)
                        ->create();
                }

                // Crear envíos de correo (90% probabilidad)
                if (rand(1, 100) <= 90) {
                    EmailSend::factory()
                        ->forCertificate($certificate)
                        ->sentBy($signer)
                        ->to($finalUser->email)
                        ->sent()
                        ->create();
                }
            }
        }

        $this->command->info('  × Certificados y datos relacionados creados correctamente');
    }
}
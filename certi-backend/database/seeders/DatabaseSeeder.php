<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use App\Models\CertificateTemplate;
use App\Models\EmailSend;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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

        // 3. Datos Maestros (Actividades y Plantillas)
        $this->createMasterData();

        // No crear certificados de prueba por defecto
        // if (app()->environment(['local', 'development', 'testing'])) {
        //     $this->createCertificatesAndRelatedData();
        // }

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

        // No borres ni resetees roles/permisos existentes
        // app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }

        // Crear roles y asignar permisos solo si no existen
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], ['guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'administrador'], ['guard_name' => 'web']);
        $adminRole->syncPermissions([
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

        $emisorRole = Role::firstOrCreate(['name' => 'emisor'], ['guard_name' => 'web']);
        $emisorRole->syncPermissions([
            'users.read', 'companies.read', 'companies.manage_own',
            'activities.create', 'activities.read', 'activities.update', 'activities.manage_own',
            'certificates.create', 'certificates.read', 'certificates.issue', 'certificates.download', 'certificates.manage_own',
            'templates.read', 'templates.manage_own', 'validations.read', 'validations.manage_own',
            'documents.upload', 'documents.download', 'documents.manage_own',
            'emails.send', 'emails.read', 'emails.resend', 'emails.manage_own',
            'reports.certificates', 'reports.activities',
        ]);

        $validadorRole = Role::firstOrCreate(['name' => 'validador'], ['guard_name' => 'web']);
        $validadorRole->syncPermissions([
            'certificates.read', 'certificates.validate', 'certificates.download',
            'validations.read', 'validations.create',
            'companies.read', 'activities.read',
        ]);

        $usuarioFinalRole = Role::firstOrCreate(['name' => 'usuario_final'], ['guard_name' => 'web']);
        $usuarioFinalRole->syncPermissions([
            'certificates.read', 'certificates.download', 'certificates.manage_own',
            'validations.read', 'validations.manage_own',
            'documents.download', 'documents.manage_own',
        ]);

        $this->command->info('  × Roles y permisos creados correctamente');

        // Normalización defensiva (por si existen registros previos inconsistentes)
        // - Asegurar guard_name = 'web' en roles y permisos
        // - Asegurar model_type correcto en pivotes
        try {
            Role::query()->whereNull('guard_name')->orWhere('guard_name', '')->update(['guard_name' => 'web']);
            Permission::query()->whereNull('guard_name')->orWhere('guard_name', '')->update(['guard_name' => 'web']);

            // Arreglar model_type a User::class si viene nulo o vacío
            DB::table(config('permission.table_names.model_has_roles'))
                ->whereNull('model_type')
                ->orWhere('model_type', '=','')
                ->update(['model_type' => \App\Models\User::class]);

            DB::table(config('permission.table_names.model_has_permissions'))
                ->whereNull('model_type')
                ->orWhere('model_type', '=','')
                ->update(['model_type' => \App\Models\User::class]);
        } catch (\Throwable $e) {
            $this->command->warn('No se pudo normalizar datos de Spatie: '.$e->getMessage());
        }
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

        // Solo crea usuarios de prueba si no existen
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@certificados.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('SuperAdmin123!'),
                'email_verified_at' => now(),
                'fecha_nacimiento' => '1980-01-15',
                'pais' => 'España',
                'genero' => 'Masculino',
                'telefono' => '+34 612345678',
                'activo' => true,
                'last_login' => now(),
            ]
        );
        $superAdmin->assignRole('super_admin');

        $admin = User::firstOrCreate(
            ['email' => 'admin@certificaciones.com'],
            [
                'name' => 'Administrador Principal',
                'password' => Hash::make('Admin123!'),
                'email_verified_at' => now(),
                'fecha_nacimiento' => '1985-05-20',
                'pais' => 'México',
                'genero' => 'Masculino',
                'telefono' => '+52 5512345678',
                'activo' => true,
                'last_login' => now(),
            ]
        );
        $admin->assignRole('administrador');

        $emisor = User::firstOrCreate(
            ['email' => 'emisor@certificaciones.com'],
            [
                'name' => 'Juan Carlos Emisor',
                'password' => Hash::make('Emisor123!'),
                'email_verified_at' => now(),
                'fecha_nacimiento' => '1990-08-10',
                'pais' => 'Colombia',
                'genero' => 'Masculino',
                'telefono' => '+57 3101234567',
                'activo' => true,
                'last_login' => now(),
            ]
        );
        $emisor->assignRole('emisor');

        $validador = User::firstOrCreate(
            ['email' => 'validador@certificaciones.com'],
            [
                'name' => 'María Validadora',
                'password' => Hash::make('Validador123!'),
                'email_verified_at' => now(),
                'fecha_nacimiento' => '1988-03-25',
                'pais' => 'Argentina',
                'genero' => 'Femenino',
                'telefono' => '+54 1123456789',
                'activo' => true,
                'last_login' => now(),
            ]
        );
        $validador->assignRole('validador');

        $usuarioFinal = User::firstOrCreate(
            ['email' => 'estudiante@ejemplo.com'],
            [
                'name' => 'Estudiante Ejemplo',
                'password' => Hash::make('Estudiante123!'),
                'email_verified_at' => now(),
                'fecha_nacimiento' => '1995-11-30',
                'pais' => 'Chile',
                'genero' => 'Otro',
                'telefono' => '+56 912345678',
                'activo' => true,
                'last_login' => now(),
            ]
        );
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

        // Crear tipos de certificados específicos
        $certificateTypes = [
            [
                'name' => 'Terminación',
                'description' => 'Certificado de terminación de curso o programa académico',
                'duration_hours' => 40,
                'is_active' => true,
            ],
            [
                'name' => 'Logro',
                'description' => 'Certificado de logro por alcanzar objetivos específicos',
                'duration_hours' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Asistencia',
                'description' => 'Certificado de asistencia a eventos, talleres o conferencias',
                'duration_hours' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Excelencia',
                'description' => 'Certificado de excelencia por desempeño sobresaliente',
                'duration_hours' => 60,
                'is_active' => true,
            ],
        ];

        foreach ($certificateTypes as $type) {
            Activity::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        // No crear plantillas por defecto - se crearán manualmente desde la interfaz
        // CertificateTemplate::factory(2)->create();

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
        $users = User::all();
        $signers = User::role(['super_admin', 'administrador', 'emisor'])->get();

        // Solo 5 certificados de ejemplo
        $certCount = 5;
        for ($i = 0; $i < $certCount; $i++) {
            $activity = $activities->random();
            $finalUser = $users->random();
            $signer = $signers->random();

            $certificate = Certificate::factory()
                ->forActivity($activity)
                ->forUser($finalUser)
                ->signedBy($signer)
                ->create();

            // Documento PDF
            CertificateDocument::factory()
                ->forCertificate($certificate)
                ->pdf()
                ->create();

            // Validación
            Validation::factory()
                ->forCertificate($certificate)
                ->create();

            // Envío de correo
            EmailSend::factory()
                ->forCertificate($certificate)
                ->sentBy($signer)
                ->to($finalUser->email)
                ->sent()
                ->create();
        }

        $this->command->info('  × Certificados y datos relacionados creados correctamente');
    }
}

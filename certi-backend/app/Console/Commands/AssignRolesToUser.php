<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignRolesToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign roles and permissions to users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Crear roles básicos si no existen
        $roles = [
            'super_admin' => 'Super Administrador',
            'administrador' => 'Administrador',
            'emisor' => 'Emisor de Certificados',
            'validador' => 'Validador',
            'usuario_final' => 'Usuario Final'
        ];

        foreach ($roles as $roleName => $roleDisplayName) {
            Role::firstOrCreate(['name' => $roleName], [
                'guard_name' => 'web',
                'display_name' => $roleDisplayName
            ]);
        }

        // Crear permisos básicos si no existen
        $permissions = [
            'users.read', 'users.create', 'users.update', 'users.delete', 'users.assign_roles',
            'roles.read', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.read', 'permissions.create', 'permissions.update', 'permissions.delete', 'permissions.assign',
            'companies.read', 'companies.create', 'companies.update', 'companies.delete',
            'activities.read', 'activities.create', 'activities.update', 'activities.delete',
            'templates.read', 'templates.create', 'templates.update', 'templates.delete',
            'certificates.read', 'certificates.create', 'certificates.update', 'certificates.delete', 'certificates.issue', 'certificates.download',
            'validations.read', 'validations.create',
            'documents.upload',
            'emails.send',
            'reports.certificates', 'reports.validations'
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName], [
                'guard_name' => 'web'
            ]);
        }

        // Asignar rol de super_admin al usuario admin
        $adminUser = User::where('email', 'admin@example.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('super_admin');
            $this->info("Rol 'super_admin' asignado al usuario: {$adminUser->name}");
        } else {
            $this->error("Usuario admin@example.com no encontrado");
        }

        // Asignar todos los permisos al rol super_admin
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo(Permission::all());
            $this->info("Todos los permisos asignados al rol 'super_admin'");
        }

        $this->info("Roles y permisos configurados correctamente");
        return 0;
    }
}

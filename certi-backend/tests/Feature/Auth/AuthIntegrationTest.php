<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_assigns_default_role(): void
    {
        // Asegurar roles necesarios existen para evitar errores en register
        Role::firstOrCreate(['name' => 'usuario_final', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'emisor', 'guard_name' => 'web']);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id', 'name', 'email'
                    ],
                    'access_token',
                    'token_type',
                    'email_verified'
                ]
            ]);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('usuario_final'));
    }

    public function test_login_success_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'Secret123!'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id', 'name', 'email'
                    ],
                    'roles',
                    'permissions',
                    'access_token',
                    'token_type',
                    'email_verified'
                ]
            ]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'noexiste@example.com',
            'password' => 'WrongPass123!'
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_me_returns_user_info_when_authenticated_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'me@example.com',
            'password' => 'Secret123!'
        ]);
        $token = $login->json('data.access_token');

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [ 'id', 'name', 'email' ],
                    'roles',
                    'permissions',
                    'can_manage_roles_users'
                ]
            ]);
    }

    public function test_logout_revokes_token_and_subsequent_requests_are_unauthorized(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'Secret123!'
        ]);
        $token = $login->json('data.access_token');

        $logout = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $logout->assertStatus(200);

        // Verificar que el token fue revocado
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_users_index_requires_permission_and_returns_200_when_authorized(): void
    {
        // Crear permiso con guard web y limpiar cache
        $perm = Permission::firstOrCreate(['name' => 'users.read', 'guard_name' => 'web']);
        // Asegurar rol requerido por el controlador
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'email' => 'perm@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $user->givePermissionTo($perm);
        $user->assignRole('super_admin');

        $login = $this->postJson('/api/auth/login', [
            'email' => 'perm@example.com',
            'password' => 'Secret123!'
        ]);
        $token = $login->json('data.access_token');

        $response = $this->getJson('/api/users', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
    }

    public function test_users_index_returns_403_when_missing_permission(): void
    {
        $user = User::factory()->create([
            'email' => 'noperm@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'noperm@example.com',
            'password' => 'Secret123!'
        ]);
        $token = $login->json('data.access_token');

        $response = $this->getJson('/api/users', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(403);
    }

    public function test_certificates_index_requires_permission_and_returns_200_when_authorized(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'certificates.read', 'guard_name' => 'web']);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'email' => 'certs@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $user->givePermissionTo($perm);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'certs@example.com',
            'password' => 'Secret123!'
        ]);
        $token = $login->json('data.access_token');

        $response = $this->getJson('/api/certificates', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }
}
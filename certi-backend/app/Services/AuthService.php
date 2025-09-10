<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthService
{
    /**
     * Registrar un nuevo usuario
     *
     * @param array $data
     * @param mixed $profilePhoto
     * @return User
     */
    public function register(array $data, $profilePhoto = null): User
    {
        return DB::transaction(function () use ($data, $profilePhoto) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'company_id' => $data['company_id'] ?? null,
            ]);

            // Asignar rol por defecto
            $defaultRole = isset($data['company_id']) ? 'emisor' : 'usuario_final';
            $user->assignRole($data['role'] ?? $defaultRole);

            return $user;
        });
    }

    /**
     * Iniciar sesión
     *
     * @param string $email
     * @param string $password
     * @return array|false
     */
    public function login(string $email, string $password)
    {
        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return false;
        }

        $user = Auth::user();

        // Verificar si el usuario está activo (si tienes este campo)
        // if (!$user->is_active) {
        //     return ['error' => 'inactive_user'];
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->load('company'),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'company' => $user->company,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    /**
     * Cerrar sesión
     *
     * @param User $user
     * @return bool
     */
    public function logout(User $user): bool
    {
        $user->currentAccessToken()->delete();
        return true;
    }

    /**
     * Cerrar todas las sesiones
     *
     * @param User $user
     * @return bool
     */
    public function logoutAll(User $user): bool
    {
        $user->tokens()->delete();
        return true;
    }

    /**
     * Actualizar perfil de usuario
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update([
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
            ]);

            if (isset($data['password'])) {
                $user->update([
                    'password' => Hash::make($data['password'])
                ]);
            }

            return $user->fresh();
        });
    }
}
<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class UserIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_be_created_in_database(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    #[Test]
    public function user_can_have_certificates(): void
    {
        $user = User::factory()->create();
        
        // Verificar que la relación existe
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->certificates());
    }

    #[Test]
    public function user_can_have_signed_certificates(): void
    {
        $user = User::factory()->create();
        
        // Verificar que la relación existe
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->signedCertificates());
    }
}
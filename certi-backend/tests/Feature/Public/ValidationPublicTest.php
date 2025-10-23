<?php

namespace Tests\Feature\Public;

use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_certificate_success(): void
    {
        $certificate = Certificate::factory()->active()->create();

        $response = $this->postJson('/api/public/validate-certificate', [
            'certificate_code' => $certificate->unique_code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'message',
                'certificate' => [
                    'id',
                    'unique_code',
                    'status',
                ],
                'validation' => [
                    'id',
                    'validation_code',
                    'validated_at'
                ]
            ]
        ]);
    }

    public function test_validate_certificate_with_invalid_code(): void
    {
        $response = $this->postJson('/api/public/validate-certificate', [
            'certificate_code' => 'INVALID-CODE',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    public function test_validate_certificate_missing_payload(): void
    {
        $response = $this->postJson('/api/public/validate-certificate', []);
        $response->assertStatus(422); // Fails validation (certificate_code required)
    }
}
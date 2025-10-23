<?php

namespace Tests\Feature\Public;

use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateByCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_for_non_existing_code(): void
    {
        $response = $this->getJson('/api/public/certificate/NON-EXISTENT-CODE');
        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_returns_certificate_for_existing_code(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->getJson('/api/public/certificate/' . $certificate->unique_code);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'certificate' => [
                    'id',
                    'user_id',
                    'activity_id',
                    'id_template',
                    'nombre',
                    'descripcion',
                    'unique_code',
                    'status',
                ]
            ]
        ]);

        $this->assertEquals($certificate->unique_code, $response->json('data.certificate.unique_code'));
    }
}

<?php

namespace Tests\Feature\Services;

use App\Models\Certificate;
use App\Models\Validation;
use App\Services\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_by_code_returns_null_for_non_existing(): void
    {
        $service = new ValidationService();
        $this->assertNull($service->getByCode('NON-EXISTENT'));
    }

    public function test_validate_certificate_creates_validation_record(): void
    {
        $certificate = Certificate::factory()->active()->create();

        $service = new ValidationService();
        $result = $service->validateCertificate($certificate->unique_code, [
            'validator_ip' => '127.0.0.1',
            'validator_user_agent' => 'PHPUnit',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['validation']);
        $this->assertInstanceOf(Validation::class, $result['validation']);

        $this->assertDatabaseHas('validations', [
            'certificate_id' => $certificate->id,
            'validation_code' => $result['validation']->validation_code,
        ]);
    }
}
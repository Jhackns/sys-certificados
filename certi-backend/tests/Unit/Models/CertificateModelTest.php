<?php

namespace Tests\Unit\Models;

use App\Models\Certificate;
use Tests\TestCase;
use Carbon\Carbon;

class CertificateModelTest extends TestCase
{
    public function test_is_valid_true_when_active_and_not_expired(): void
    {
        $certificate = new Certificate();
        $certificate->status = 'active';
        $certificate->fecha_vencimiento = Carbon::now()->addDays(10);

        $this->assertTrue($certificate->isValid());
    }

    public function test_is_valid_false_when_revoked(): void
    {
        $certificate = new Certificate();
        $certificate->status = 'revoked';

        $this->assertFalse($certificate->isValid());
    }

    public function test_generate_verification_code_format(): void
    {
        $certificate = new Certificate();
        $certificate->id = 1; // Asignar ID manualmente sin persistencia
        $code = $certificate->generateVerificationCode();

        $this->assertMatchesRegularExpression('/^CERT001-[a-f0-9]{12}$/', $code);
    }
}
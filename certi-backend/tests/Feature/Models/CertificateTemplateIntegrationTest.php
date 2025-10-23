<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CertificateTemplateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_certificate_template_can_be_created_in_database(): void
    {
        $template = CertificateTemplate::factory()->create([
            'name' => 'Test Template',
            'activity_type' => 'course',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('certificate_templates', [
            'id' => $template->id,
            'name' => 'Test Template',
            'activity_type' => 'course',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function active_scope_filters_active_templates(): void
    {
        // Crear plantillas activas e inactivas
        CertificateTemplate::factory()->create(['status' => 'active']);
        CertificateTemplate::factory()->create(['status' => 'inactive']);
        
        $activeTemplates = CertificateTemplate::active()->get();
        
        $this->assertCount(1, $activeTemplates);
        $this->assertEquals('active', $activeTemplates->first()->status);
    }

    #[Test]
    public function for_activity_type_scope_filters_by_type(): void
    {
        // Crear plantillas de diferentes tipos
        CertificateTemplate::factory()->create(['activity_type' => 'course']);
        CertificateTemplate::factory()->create(['activity_type' => 'event']);
        
        $courseTemplates = CertificateTemplate::forActivityType('course')->get();
        
        $this->assertCount(1, $courseTemplates);
        $this->assertEquals('course', $courseTemplates->first()->activity_type);
    }
}
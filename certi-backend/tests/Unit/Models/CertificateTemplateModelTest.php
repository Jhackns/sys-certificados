<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CertificateTemplate;
use PHPUnit\Framework\Attributes\Test;

class CertificateTemplateModelTest extends TestCase
{
    #[Test]
    public function it_has_correct_fillable_attributes(): void
    {
        $template = new CertificateTemplate();
        $fillable = $template->getFillable();
        
        $expectedFillable = [
            'name',
            'description',
            'file_path',
            'activity_type',
            'status',
            'canva_design_id',
        ];
        
        $this->assertEquals($expectedFillable, $fillable);
    }

    #[Test]
    public function it_can_set_and_get_attributes(): void
    {
        $template = new CertificateTemplate();
        
        $template->name = 'Test Template';
        $template->description = 'Test Description';
        $template->activity_type = 'course';
        $template->status = 'active';
        
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals('Test Description', $template->description);
        $this->assertEquals('course', $template->activity_type);
        $this->assertEquals('active', $template->status);
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $template = new CertificateTemplate();
        $this->assertEquals('certificate_templates', $template->getTable());
    }
}
<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TemplateOptionalComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup permissions
        $role = Role::firstOrCreate(['name' => 'admin']);
        Permission::firstOrCreate(['name' => 'templates.update']);
        Permission::firstOrCreate(['name' => 'templates.create']);
        $role->givePermissionTo(['templates.update', 'templates.create']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_can_clear_optional_components()
    {
        // Create a template WITH all components
        $template = CertificateTemplate::factory()->create([
            'name_position' => ['x' => 10, 'y' => 10],
            'qr_position' => ['x' => 20, 'y' => 20, 'width' => 100, 'height' => 100],
            'date_position' => ['x' => 30, 'y' => 30],
        ]);

        $this->assertNotNull($template->qr_position);
        $this->assertNotNull($template->date_position);

        // Update with empty qr_position and date_position
        // Simulating what FormData sends (empty strings)
        // Note: In integration tests, Laravel middleware ConvertEmptyStringsToNull runs.
        
        $response = $this->actingAs($this->user)
            ->putJson("/api/certificate-templates/{$template->id}", [
                'name' => $template->name,
                'activity_type' => $template->activity_type,
                'status' => $template->status,
                'name_position' => ['x' => 10, 'y' => 10], // Required
                'qr_position' => null, // Simulate cleared field
                'date_position' => null, // Simulate cleared field
            ]);

        $response->assertStatus(200);

        $template->refresh();

        $this->assertNull($template->qr_position, 'QR position should be null');
        $this->assertNull($template->date_position, 'Date position should be null');
        $this->assertNotNull($template->name_position, 'Name position should remain');
    }

    public function test_name_position_is_required()
    {
        $template = CertificateTemplate::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/certificate-templates/{$template->id}", [
                'name' => 'Updated Name',
                'activity_type' => 'course',
                'status' => 'active',
                // Missing name_position
                'qr_position' => null,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name_position']);
    }
}

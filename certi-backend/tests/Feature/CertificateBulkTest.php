<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CertificateBulkTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $activity;
    protected $template;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions
        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'certificates.create']);
        Permission::create(['name' => 'certificates.delete']);
        Permission::create(['name' => 'emails.send']);
        $role->givePermissionTo(['certificates.create', 'certificates.delete', 'emails.send']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->activity = Activity::factory()->create();
        $this->template = CertificateTemplate::factory()->create();
    }

    public function test_bulk_create_certificates()
    {
        $users = User::factory()->count(3)->create();
        
        $data = $users->map(function ($user) {
            return [
                'user_id' => $user->id,
                'activity_id' => $this->activity->id,
                'id_template' => $this->template->id,
                'nombre' => 'Certificado Test ' . $user->name,
                'fecha_emision' => now()->format('Y-m-d'),
                'status' => 'issued'
            ];
        })->toArray();

        $response = $this->actingAs($this->user)
            ->postJson('/api/certificates/bulk', ['certificates' => $data]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['count', 'ids']]);

        $this->assertEquals(3, Certificate::count());
    }

    public function test_bulk_send_emails()
    {
        Queue::fake();

        $certificates = Certificate::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'activity_id' => $this->activity->id,
            'id_template' => $this->template->id,
        ]);

        $ids = $certificates->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->postJson('/api/certificates/bulk-email', ['ids' => $ids]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Queue::assertPushed(\App\Jobs\SendCertificateEmailJob::class, 3);
    }

    public function test_bulk_delete_certificates()
    {
        $certificates = Certificate::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'activity_id' => $this->activity->id,
            'id_template' => $this->template->id,
        ]);

        $ids = $certificates->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->postJson('/api/certificates/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(0, Certificate::count());
    }
}

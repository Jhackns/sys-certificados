<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificateTemplate>
 */
class CertificateTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $activityType = $this->faker->randomElement(['course', 'event', 'other']);
        
        return [
            'company_id' => Company::factory(),
            'name' => $this->generateTemplateName($activityType),
            'description' => $this->faker->sentence(10),
            'file_path' => 'templates/' . $this->faker->uuid() . '.html',
            'activity_type' => $activityType,
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Generate template name based on activity type.
     */
    private function generateTemplateName(string $activityType): string
    {
        $designs = ['Clásico', 'Moderno', 'Elegante', 'Corporativo', 'Minimalista'];
        $colors = ['Azul', 'Verde', 'Dorado', 'Plateado', 'Rojo'];
        
        $design = $this->faker->randomElement($designs);
        $color = $this->faker->randomElement($colors);
        
        return match ($activityType) {
            'course' => "Plantilla {$design} {$color} - Cursos",
            'event' => "Plantilla {$design} {$color} - Eventos", 
            'other' => "Plantilla {$design} {$color} - General",
        };
    }

    /**
     * Indicate that the template is for courses.
     */
    public function forCourses(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'course',
            'name' => $this->generateTemplateName('course'),
        ]);
    }

    /**
     * Indicate that the template is for events.
     */
    public function forEvents(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'event',
            'name' => $this->generateTemplateName('event'),
        ]);
    }

    /**
     * Indicate that the template is for other activities.
     */
    public function forOther(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'other',
            'name' => $this->generateTemplateName('other'),
        ]);
    }

    /**
     * Indicate that the template is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the template belongs to a specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }
}
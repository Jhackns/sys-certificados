<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['course', 'event', 'other']);
        $startDate = $this->faker->dateTimeBetween('-6 months', '+3 months');
        $endDate = $this->faker->dateTimeBetween($startDate, '+6 months');

        return [
            'company_id' => Company::factory(),
            'name' => $this->generateActivityName($type),
            'description' => $this->faker->paragraph(3),
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => $this->faker->boolean(85), // 85% probabilidad de estar activo
        ];
    }

    /**
     * Generate activity name based on type.
     */
    private function generateActivityName(string $type): string
    {
        return match ($type) {
            'course' => $this->faker->randomElement([
                'Curso de ' . $this->faker->randomElement(['PHP', 'Laravel', 'JavaScript', 'Python', 'Java']),
                'Capacitación en ' . $this->faker->randomElement(['Seguridad', 'Bases de Datos', 'DevOps', 'Testing']),
                'Taller de ' . $this->faker->randomElement(['Desarrollo Web', 'Mobile', 'Cloud Computing', 'AI']),
            ]),
            'event' => $this->faker->randomElement([
                'Conferencia de ' . $this->faker->randomElement(['Tecnología', 'Innovación', 'Desarrollo']),
                'Seminario de ' . $this->faker->randomElement(['Liderazgo', 'Gestión', 'Marketing']),
                'Workshop de ' . $this->faker->randomElement(['Design Thinking', 'Agile', 'Scrum']),
            ]),
            'other' => $this->faker->randomElement([
                'Certificación en ' . $this->faker->randomElement(['Calidad', 'Procesos', 'Normas ISO']),
                'Evaluación de ' . $this->faker->randomElement(['Competencias', 'Habilidades', 'Conocimientos']),
                'Validación de ' . $this->faker->randomElement(['Experiencia', 'Logros', 'Participación']),
            ]),
        };
    }

    /**
     * Indicate that the activity is a course.
     */
    public function course(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'course',
            'name' => $this->generateActivityName('course'),
        ]);
    }

    /**
     * Indicate that the activity is an event.
     */
    public function event(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'event',
            'name' => $this->generateActivityName('event'),
        ]);
    }

    /**
     * Indicate that the activity is other type.
     */
    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'other',
            'name' => $this->generateActivityName('other'),
        ]);
    }

    /**
     * Indicate that the activity is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the activity is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the activity belongs to a specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }
}
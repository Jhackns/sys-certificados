<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Validation>
 */
class ValidationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'user_id' => $this->faker->boolean(70) ? User::factory() : null, // 70% con usuario, 30% anónimo
            'validated_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'notes' => $this->faker->optional(0.3)->sentence(), // 30% probabilidad de tener notas
        ];
    }

    /**
     * Indicate that the validation was done by a registered user.
     */
    public function byUser(User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user ? $user->id : User::factory(),
        ]);
    }

    /**
     * Indicate that the validation was done anonymously.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the validation was done recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'validated_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the validation was done long ago.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'validated_at' => $this->faker->dateTimeBetween('-1 year', '-3 months'),
        ]);
    }

    /**
     * Indicate that the validation has notes.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the validation was done from mobile.
     */
    public function fromMobile(): static
    {
        $mobileUserAgents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Android 11; Mobile; rv:68.0) Gecko/68.0 Firefox/88.0',
            'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36',
        ];

        return $this->state(fn (array $attributes) => [
            'user_agent' => $this->faker->randomElement($mobileUserAgents),
        ]);
    }

    /**
     * Indicate that the validation was done from desktop.
     */
    public function fromDesktop(): static
    {
        $desktopUserAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        ];

        return $this->state(fn (array $attributes) => [
            'user_agent' => $this->faker->randomElement($desktopUserAgents),
        ]);
    }

    /**
     * Create validation for specific certificate.
     */
    public function forCertificate(Certificate $certificate): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_id' => $certificate->id,
        ]);
    }
}
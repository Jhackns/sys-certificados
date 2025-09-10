<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedAt = $this->faker->dateTimeBetween('-1 year', 'now');
        
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'activity_id' => Activity::factory(),
            'signed_by' => User::factory(),
            'unique_code' => $this->generateUniqueCode(),
            'qr_url' => $this->generateQrUrl(),
            'issued_at' => $issuedAt,
            'status' => $this->faker->randomElement(['active', 'revoked', 'expired']),
        ];
    }

    /**
     * Generate a unique certificate code.
     */
    private function generateUniqueCode(): string
    {
        return 'CERT-' . strtoupper(Str::random(8)) . '-' . date('Y');
    }

    /**
     * Generate a QR URL for the certificate.
     */
    private function generateQrUrl(): string
    {
        $baseUrl = config('app.url', 'https://certificates.example.com');
        $code = $this->generateUniqueCode();
        return $baseUrl . '/verify/' . $code;
    }

    /**
     * Indicate that the certificate is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the certificate is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }

    /**
     * Indicate that the certificate is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
        ]);
    }

    /**
     * Indicate that the certificate was issued recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the certificate was issued long ago.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_at' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
        ]);
    }

    /**
     * Create certificate for specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }

    /**
     * Create certificate for specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create certificate for specific activity.
     */
    public function forActivity(Activity $activity): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_id' => $activity->id,
            'company_id' => $activity->company_id,
        ]);
    }

    /**
     * Create certificate signed by specific user.
     */
    public function signedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'signed_by' => $user->id,
        ]);
    }
}
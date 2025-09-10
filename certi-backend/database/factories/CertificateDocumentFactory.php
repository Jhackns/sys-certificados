<?php

namespace Database\Factories;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificateDocument>
 */
class CertificateDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentType = $this->faker->randomElement(['pdf', 'image', 'other']);
        $uploadedAt = $this->faker->dateTimeBetween('-1 year', 'now');
        
        return [
            'certificate_id' => Certificate::factory(),
            'document_type' => $documentType,
            'file_path' => $this->generateFilePath($documentType),
            'original_name' => $this->generateOriginalName($documentType),
            'mime_type' => $this->getMimeType($documentType),
            'file_size' => $this->faker->numberBetween(50000, 2000000), // 50KB a 2MB
            'uploaded_at' => $uploadedAt,
        ];
    }

    /**
     * Generate file path based on document type.
     */
    private function generateFilePath(string $documentType): string
    {
        $year = date('Y');
        $month = date('m');
        $filename = $this->faker->uuid();
        
        return match ($documentType) {
            'pdf' => "certificates/{$year}/{$month}/{$filename}.pdf",
            'image' => "certificates/{$year}/{$month}/{$filename}.jpg",
            'other' => "certificates/{$year}/{$month}/{$filename}.docx",
        };
    }

    /**
     * Generate original filename.
     */
    private function generateOriginalName(string $documentType): string
    {
        $names = [
            'Certificado_Participacion',
            'Certificate_Completion',
            'Diploma_Curso',
            'Constancia_Evento',
            'Certificado_Logro'
        ];
        
        $name = $this->faker->randomElement($names);
        $timestamp = date('Y-m-d');
        
        return match ($documentType) {
            'pdf' => "{$name}_{$timestamp}.pdf",
            'image' => "{$name}_{$timestamp}.jpg",
            'other' => "{$name}_{$timestamp}.docx",
        };
    }

    /**
     * Get MIME type based on document type.
     */
    private function getMimeType(string $documentType): string
    {
        return match ($documentType) {
            'pdf' => 'application/pdf',
            'image' => $this->faker->randomElement(['image/jpeg', 'image/png']),
            'other' => $this->faker->randomElement([
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword',
                'text/plain'
            ]),
        };
    }

    /**
     * Indicate that the document is a PDF.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => 'pdf',
            'file_path' => $this->generateFilePath('pdf'),
            'original_name' => $this->generateOriginalName('pdf'),
            'mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Indicate that the document is an image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => 'image',
            'file_path' => $this->generateFilePath('image'),
            'original_name' => $this->generateOriginalName('image'),
            'mime_type' => $this->faker->randomElement(['image/jpeg', 'image/png']),
        ]);
    }

    /**
     * Indicate that the document is other type.
     */
    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => 'other',
            'file_path' => $this->generateFilePath('other'),
            'original_name' => $this->generateOriginalName('other'),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Indicate that the document is large.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(1000000, 5000000), // 1MB a 5MB
        ]);
    }

    /**
     * Indicate that the document is small.
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(10000, 100000), // 10KB a 100KB
        ]);
    }

    /**
     * Create document for specific certificate.
     */
    public function forCertificate(Certificate $certificate): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_id' => $certificate->id,
        ]);
    }

    /**
     * Indicate that the document was uploaded recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }
}
<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailSend>
 */
class EmailSendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sentAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $status = $this->faker->randomElement(['pending', 'sent', 'failed']);
        
        return [
            'certificate_id' => Certificate::factory(),
            'user_id' => User::factory(),
            'email_to' => $this->faker->safeEmail(),
            'subject' => $this->generateSubject(),
            'body' => $this->generateBody(),
            'sent_at' => $sentAt,
            'status' => $status,
            'error_message' => $status === 'failed' ? $this->generateErrorMessage() : null,
        ];
    }

    /**
     * Generate email subject.
     */
    private function generateSubject(): string
    {
        $subjects = [
            '🎓 Tu certificado está listo - ¡Descárgalo ahora!',
            '✅ Certificado de participación emitido',
            '🏆 ¡Felicitaciones! Tu certificado ha sido generado',
            '📜 Certificado digital disponible para descarga',
            '🎉 Tu certificado de finalización está aquí',
            '✨ Certificado oficial - Descarga inmediata',
        ];

        return $this->faker->randomElement($subjects);
    }

    /**
     * Generate email body.
     */
    private function generateBody(): string
    {
        $bodies = [
            'Estimado/a participante,\n\nNos complace informarte que tu certificado ha sido emitido exitosamente. Puedes descargarlo haciendo clic en el enlace adjunto.\n\n¡Felicitaciones por tu logro!\n\nSaludos cordiales,\nEquipo de Certificaciones',
            
            'Hola,\n\nTu certificado digital está listo. Este documento certifica tu participación y/o finalización exitosa.\n\nDescárgalo desde el enlace proporcionado.\n\nGracias por tu participación.\n\nAtentamente,\nDepartamento Académico',
            
            '¡Felicitaciones!\n\nHas completado exitosamente el programa y tu certificado oficial ha sido generado.\n\nPuedes acceder a tu certificado digital a través del enlace seguro incluido en este correo.\n\nSaludos,\nEquipo de Formación',
        ];

        return $this->faker->randomElement($bodies);
    }

    /**
     * Generate error message for failed emails.
     */
    private function generateErrorMessage(): string
    {
        $errors = [
            'SMTP connection failed',
            'Invalid email address format',
            'Recipient mailbox full',
            'Email server timeout',
            'Authentication failed',
            'Message rejected by recipient server',
            'Daily sending limit exceeded',
            'Temporary server error - retry later',
        ];

        return $this->faker->randomElement($errors);
    }

    /**
     * Indicate that the email was sent successfully.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the email is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'error_message' => null,
            'sent_at' => $this->faker->dateTimeBetween('now', '+1 hour'),
        ]);
    }

    /**
     * Indicate that the email failed to send.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->generateErrorMessage(),
        ]);
    }

    /**
     * Indicate that the email was sent recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create email for specific certificate.
     */
    public function forCertificate(Certificate $certificate): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_id' => $certificate->id,
        ]);
    }

    /**
     * Create email sent by specific user.
     */
    public function sentBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create email with specific recipient.
     */
    public function to(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email_to' => $email,
        ]);
    }

    /**
     * Create email with custom subject.
     */
    public function withSubject(string $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'subject' => $subject,
        ]);
    }
}
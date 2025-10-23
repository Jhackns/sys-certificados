<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class CertificateGenerated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * El certificado generado.
     */
    public $certificate;

    /**
     * Create a new message instance.
     */
    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu certificado está listo - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.certificate-generated',
            with: [
                'certificateName' => $this->certificate->nombre,
                'userName' => $this->certificate->user->name ?? 'Usuario',
                'verificationUrl' => url("/verify/{$this->certificate->unique_code}"),
                'activityName' => $this->certificate->activity->name ?? 'Actividad',
                'issueDate' => $this->certificate->fecha_emision,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if ($this->certificate->file_path) {
            return [
                Attachment::fromStorage($this->certificate->file_path)
                    ->as('certificado.pdf')
                    ->withMime('application/pdf'),
            ];
        }
        
        return [];
    }
}
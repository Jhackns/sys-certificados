<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateDocument;
use App\Models\User;
use App\Services\CertificateImageService;
use App\Services\QRCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\CertificateGenerated;
use Exception;

class GenerateCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $certificate;
    protected $sendEmail;

    /**
     * Create a new job instance.
     */
    public function __construct(Certificate $certificate, bool $sendEmail = false)
    {
        $this->certificate = $certificate;
        $this->sendEmail = $sendEmail;
        // Ejecutar el job después de que la transacción se confirme
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(QRCodeService $qrService, CertificateImageService $imageService): void
    {
        Log::info('Iniciando generación de certificado en segundo plano (ruta local PNG)', [
            'certificate_id' => $this->certificate->id,
            'user_id' => $this->certificate->user_id
        ]);

        // Obtener la plantilla
        $template = CertificateTemplate::findOrFail($this->certificate->id_template);

        // Obtener el usuario
        $user = User::findOrFail($this->certificate->user_id);

        // Generar URL para verificación
        $verificationUrl = url("/verify/{$this->certificate->unique_code}");

        // Generar QR
        $qrRelativePath = $qrService->generateQRCodeFromUrl($verificationUrl);

        // Convertir ruta relativa del QR a absoluta para el servicio de imagen
        $qrAbsolutePath = $qrRelativePath ? storage_path('app/public/' . $qrRelativePath) : '';

        // Generar imagen final del certificado (PNG) con nombre y QR
        $finalImageRelativePath = $imageService->generateFinalCertificateImage($this->certificate, $qrAbsolutePath);

        // Registrar documento del certificado (imagen)
        if ($finalImageRelativePath) {
            CertificateDocument::create([
                'certificate_id' => $this->certificate->id,
                'document_type' => 'image',
                'file_path' => $finalImageRelativePath,
                'uploaded_at' => now(),
            ]);
        }

        // Actualizar estado del certificado
        $this->certificate->status = 'issued';
        $this->certificate->save();

        Log::info('Certificado generado exitosamente (PNG)', [
            'certificate_id' => $this->certificate->id,
            'file_path' => $finalImageRelativePath
        ]);

        // Enviar correo electrónico si está habilitado
        if ($this->sendEmail && $user->email) {
            Mail::to($user->email)->send(new CertificateGenerated($this->certificate));

            Log::info('Correo de certificado enviado', [
                'certificate_id' => $this->certificate->id,
                'user_email' => $user->email
            ]);
        }
    }
}

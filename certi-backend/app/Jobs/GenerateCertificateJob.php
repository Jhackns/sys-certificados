<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\CanvaService;
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
    }

    /**
     * Execute the job.
     */
    public function handle(CanvaService $canvaService, QRCodeService $qrService): void
    {
        try {
            Log::info('Iniciando generación de certificado en segundo plano', [
                'certificate_id' => $this->certificate->id,
                'user_id' => $this->certificate->user_id
            ]);

            // Obtener la plantilla
            $template = CertificateTemplate::findOrFail($this->certificate->id_template);

            if (empty($template->canva_design_id)) {
                throw new Exception('La plantilla no tiene un ID de diseño de Canva configurado');
            }

            // Obtener el usuario
            $user = User::findOrFail($this->certificate->user_id);

            // Generar URL para el QR
            $verificationUrl = url("/verify/{$this->certificate->unique_code}");
            $qrImagePath = $qrService->generateQRCodeFromUrl($verificationUrl);

            // Preparar datos para Canva
            $userData = [
                'user_id' => $user->id,
                'nombre' => $this->certificate->nombre ?? $user->name,
                'titulo' => $template->name,
                'fecha_emision' => $this->certificate->fecha_emision,
                'fecha_vencimiento' => $this->certificate->fecha_vencimiento,
                'qr_url' => $verificationUrl,
                'activity_name' => $this->certificate->activity->name ?? '',
                'activity_duration' => $this->certificate->activity->duration_hours ?? '',
            ];

            // Generar el certificado con Canva
            $pdfPath = $canvaService->generateCertificate($template->canva_design_id, $userData);

            // Actualizar el certificado con la ruta del archivo
            $this->certificate->file_path = $pdfPath;
            $this->certificate->status = 'issued';
            $this->certificate->save();

            Log::info('Certificado generado exitosamente', [
                'certificate_id' => $this->certificate->id,
                'file_path' => $pdfPath
            ]);

            // Enviar correo electrónico si está habilitado
            if ($this->sendEmail && $user->email) {
                Mail::to($user->email)->send(new CertificateGenerated($this->certificate));

                Log::info('Correo de certificado enviado', [
                    'certificate_id' => $this->certificate->id,
                    'user_email' => $user->email
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error al generar certificado en segundo plano', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Marcar el certificado como fallido
            $this->certificate->status = 'failed';
            $this->certificate->save();

            // Relanzar la excepción para que Laravel maneje los reintentos
            throw $e;
        }
    }
}

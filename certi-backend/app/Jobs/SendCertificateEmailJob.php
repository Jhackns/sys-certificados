<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\CertificateImageService;
use App\Services\QRCodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendCertificateEmailJob implements ShouldQueue
{
    use Queueable;

    protected $certificateId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $certificateId)
    {
        $this->certificateId = $certificateId;
    }

    /**
     * Execute the job.
     */
    public function handle(CertificateImageService $imageService, QRCodeService $qrService): void
    {
        try {
            $certificate = Certificate::with(['user', 'activity', 'template'])->find($this->certificateId);

            if (!$certificate) {
                Log::error("SendCertificateEmailJob: Certificado {$this->certificateId} no encontrado.");
                return;
            }

            if (!$certificate->user || !$certificate->user->email) {
                Log::warning("SendCertificateEmailJob: El certificado {$this->certificateId} no tiene un email válido.");
                return;
            }

            $emailTo = $certificate->user->email;
            $fullName = $certificate->user->name ?? ($certificate->nombre ?: 'Usuario');
            $certName = $certificate->nombre ?: ('Certificado #' . $certificate->id);
            $activityName = $certificate->activity ? ($certificate->activity->name ?? '') : '';

            // 1. Verificar si ya existe una imagen final generada
            $finalImagePath = $certificate->final_image_path;
            $finalImageAbsolute = $finalImagePath ? storage_path('app/public/' . $finalImagePath) : null;

            // 2. Si no existe, intentamos generarla al vuelo
            if (!$finalImageAbsolute || !file_exists($finalImageAbsolute)) {
                Log::info('SendCertificateEmailJob: Imagen final no encontrada, generando...', ['certificate_id' => $certificate->id]);
                
                // Necesitamos el QR
                $verificationUrl = url("/verify/{$certificate->unique_code}");
                $qrRelativePath = $qrService->generateQRCodeFromUrl($verificationUrl);
                $qrAbsolutePath = $qrRelativePath ? storage_path('app/public/' . $qrRelativePath) : '';

                // Generar imagen
                $finalImagePath = $imageService->generateFinalCertificateImage($certificate, $qrAbsolutePath);
                
                if ($finalImagePath) {
                    $certificate->final_image_path = $finalImagePath;
                    $certificate->save();
                    $finalImageAbsolute = storage_path('app/public/' . $finalImagePath);
                } else {
                    Log::error('SendCertificateEmailJob: No se pudo generar la imagen del certificado', ['certificate_id' => $certificate->id]);
                    return;
                }
            }

            // 3. Generar PDF envolviendo la imagen
            $imgSize = @getimagesize($finalImageAbsolute);
            $imgW = $imgSize ? $imgSize[0] : 2100;
            $imgH = $imgSize ? $imgSize[1] : 1480;
            
            $dpi = 96;
            $widthMm = ($imgW * 25.4) / $dpi;
            $heightMm = ($imgH * 25.4) / $dpi;
            $orientation = ($widthMm >= $heightMm) ? 'L' : 'P';

            $pdf = new \TCPDF($orientation, 'mm', [$widthMm, $heightMm]);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->Image($finalImageAbsolute, 0, 0, $widthMm, $heightMm, '', '', '', false, 300, '', false, false, 0);
            
            $filename = 'certificado-' . $certificate->unique_code . '.pdf';
            $pdfBytes = $pdf->Output($filename, 'S');

            // HTML del mensaje
            $subject = 'Tu certificado: ' . $certName;
            $html = '<div style="font-family: Arial, Helvetica, sans-serif; background:#f7fafc; padding:20px;">'
                . '<div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">'
                . '<div style="padding:20px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff;">'
                . '<h2 style="margin:0;">Certificado emitido</h2>'
                . '</div>'
                . '<div style="padding:20px; color:#1a202c;">'
                . '<p style="font-size:16px;">Hola <strong>' . e($fullName) . '</strong>,</p>'
                . '<p style="font-size:15px;">Adjuntamos tu certificado de <strong>' . e($certName) . '</strong>'
                . ($activityName ? (' del evento <strong>' . e($activityName) . '</strong>') : '')
                . '.</p>'
                . '<p style="font-size:14px; color:#4a5568;">Puedes validar tu certificado en cualquier momento desde el enlace de verificación incluido en el QR.</p>'
                . '<div style="margin-top:20px; padding:16px; background:#f0f4ff; border:1px solid #cfe2ff; border-radius:8px; color:#1a1a1a;">'
                . '<strong>Consejo:</strong> Si no ves este correo en tu bandeja de entrada, revisa la carpeta de spam y marca el remitente como confiable.'
                . '</div>'
                . '</div>'
                . '<div style="padding:16px; text-align:center; color:#718096; font-size:12px;">'
                . 'Sys-Certificados'
                . '</div>'
                . '</div>';

            // Enviar correo
            Mail::html($html, function ($message) use ($emailTo, $subject, $pdfBytes, $filename) {
                $message->to($emailTo)
                    ->subject($subject)
                    ->attachData($pdfBytes, $filename, ['mime' => 'application/pdf']);
            });

            Log::info('SendCertificateEmailJob: Correo enviado correctamente', [
                'certificate_id' => $certificate->id,
                'to' => $emailTo
            ]);

        } catch (\Throwable $e) {
            Log::error('SendCertificateEmailJob: Error al enviar correo', [
                'certificate_id' => $this->certificateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

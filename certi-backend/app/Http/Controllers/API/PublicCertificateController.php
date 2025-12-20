<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\CertificateImageService;
use App\Services\QRCodeService;
use TCPDF;

class PublicCertificateController extends Controller
{
    protected $imageService;
    protected $qrService;

    public function __construct(CertificateImageService $imageService, QRCodeService $qrService)
    {
        $this->imageService = $imageService;
        $this->qrService = $qrService;
    }

    /**
     * Verificar certificado públicamente por código
     *
     * @param string $code
     * @return JsonResponse
     */
    public function verify($code): JsonResponse
    {
        $certificate = Certificate::with(['user', 'activity', 'template', 'signer'])
            ->where('unique_code', $code)
            ->first();

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado no encontrado o código inválido (API Publica)'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'certificate' => [
                    'code' => $certificate->unique_code,
                    'nombre' => $certificate->nombre,
                    'descripcion' => $certificate->descripcion,
                    'receptor' => $certificate->user ? $certificate->user->name : ($certificate->nombre ?: 'Sin nombre'),
                    'email_receptor' => $certificate->user ? $certificate->user->email : 'N/A',
                    'curso' => $certificate->activity ? $certificate->activity->name : 'N/A',
                    'plantilla' => $certificate->template ? $certificate->template->name : 'N/A',
                    'fecha_emision' => $certificate->fecha_emision ? $certificate->fecha_emision->format('d/m/Y') : 'N/A',
                    'fecha_vencimiento' => $certificate->fecha_vencimiento ? $certificate->fecha_vencimiento->format('d/m/Y') : 'N/A',
                    'fecha_vencimiento' => $certificate->fecha_vencimiento ? $certificate->fecha_vencimiento->format('d/m/Y') : 'N/A',
                    'status' => $certificate->status, // Standard key
                    'estado' => $certificate->status, // Backward compatibility
                    'issuer' => $certificate->signer ? $certificate->signer->name : 'Sistema'
                ]
            ]
        ]);
    }

    /**
     * Descargar certificado públicamente
     *
     * @param string $code
     * @return \Illuminate\Http\Response|JsonResponse
     */
    public function download($code)
    {
        $certificate = Certificate::where('unique_code', $code)->first();

        if (!$certificate) {
            return response()->json(['success' => false, 'message' => 'Certificado no encontrado'], 404);
        }

        try {
            // Lógica similar a CertificateController::download pero adaptada
            
            // 1. Ubicar imagen final
            $finalImagePath = $certificate->final_image_path;
            $finalImageAbsolute = $finalImagePath ? storage_path('app/public/' . $finalImagePath) : null;

            // 2. Si no existe, intentar regenerar (JIT)
            // Nota: Al ser público, regenerar puede ser costoso si abusan, pero es necesario si el archivo físico no existe.
            // 2. Si no existe, intentar regenerar (JIT)
            if (!$finalImageAbsolute || !file_exists($finalImageAbsolute)) {
                 // Recuperar QR existente o generar si falta
                 $qrRelativePath = $certificate->qr_image_path;
                 
                 // Validar existencia física del QR
                 if (!$qrRelativePath || !Storage::disk('public')->exists($qrRelativePath)) {
                     $verificationUrl = $certificate->getQrContentUrl();
                     // Usar el método que devuelve el path relativo
                     $qrRelativePath = $this->qrService->generateQRCodeFromUrl($verificationUrl);
                     $certificate->qr_image_path = $qrRelativePath;
                     $certificate->save();
                 }

                 $qrAbsolutePath = $qrRelativePath ? storage_path('app/public/' . $qrRelativePath) : '';
                 
                 $newPath = $this->imageService->generateFinalCertificateImage($certificate, $qrAbsolutePath);
                 if ($newPath) {
                     $certificate->final_image_path = $newPath;
                     $certificate->save();
                     $finalImageAbsolute = storage_path('app/public/' . $newPath);
                 } else {
                     return response()->json(['success' => false, 'message' => 'Error al generar imagen'], 500);
                 }
            }

            // 3. Generar PDF
            $imgSize = @getimagesize($finalImageAbsolute);
            $imgW = $imgSize ? $imgSize[0] : 2100;
            $imgH = $imgSize ? $imgSize[1] : 1480;
            
            $dpi = 96;
            $widthMm = ($imgW * 25.4) / $dpi;
            $heightMm = ($imgH * 25.4) / $dpi;
            $orientation = ($widthMm >= $heightMm) ? 'L' : 'P';

            $pdf = new TCPDF($orientation, 'mm', [$widthMm, $heightMm]);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->Image($finalImageAbsolute, 0, 0, $widthMm, $heightMm, '', '', '', false, 300, '', false, false, 0);
            
            $filename = 'certificado-' . $certificate->unique_code . '.pdf';
            
            return response($pdf->Output($filename, 'S'))
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al descargar: ' . $e->getMessage()], 500);
        }
    }
}

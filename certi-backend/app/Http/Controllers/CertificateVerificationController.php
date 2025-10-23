<?php

namespace App\Http\Controllers;

use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    protected QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Mostrar página de verificación web
     */
    public function show(string $verificationCode): View
    {
        $certificate = $this->qrCodeService->verifyCertificate($verificationCode);
        
        if (!$certificate) {
            return view('verification.not-found', [
                'verification_code' => $verificationCode
            ]);
        }

        $validationData = $this->qrCodeService->getValidationData($certificate);

        return view('verification.show', [
            'certificate' => $certificate,
            'validation_data' => $validationData
        ]);
    }

    /**
     * API para verificación de certificado
     */
    public function verify(string $verificationCode): JsonResponse
    {
        $certificate = $this->qrCodeService->verifyCertificate($verificationCode);
        
        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado no encontrado',
                'verification_code' => $verificationCode
            ], 404);
        }

        $validationData = $this->qrCodeService->getValidationData($certificate);

        return response()->json([
            'success' => true,
            'message' => 'Certificado verificado exitosamente',
            'data' => $validationData
        ]);
    }

    /**
     * Descargar PDF del certificado (si está disponible)
     */
    public function downloadPdf(string $verificationCode)
    {
        $certificate = $this->qrCodeService->verifyCertificate($verificationCode);
        
        if (!$certificate) {
            abort(404, 'Certificado no encontrado');
        }

        // Aquí implementarías la lógica para generar/descargar el PDF
        // Por ahora retornamos un mensaje
        return response()->json([
            'message' => 'Funcionalidad de descarga PDF pendiente de implementar',
            'certificate_id' => $certificate->id
        ]);
    }
}

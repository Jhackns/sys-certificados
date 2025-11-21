<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    /**
     * Generar un PDF local del certificado usando TCPDF con fondo de plantilla.
     * Devuelve la ruta relativa en storage (e.g., certificates/xxx.pdf).
     */
    public function generateLocalPdf(Certificate $certificate, CertificateTemplate $template): string
    {
        $backgroundPath = $template->file_path ? storage_path('app/' . $template->file_path) : null;

        $pdf = new \TCPDF();
        $pdf->SetCreator('Sys Certificados');
        $pdf->SetAuthor('Sys Certificados');
        $pdf->SetTitle($certificate->nombre ?? 'Certificado');
        $pdf->AddPage();

        if ($backgroundPath && file_exists($backgroundPath)) {
            // A4: 210x297 mm
            $pdf->Image($backgroundPath, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }

        // Texto del certificado
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(20, 110);
        $pdf->Cell(170, 10, $certificate->nombre ?? 'Certificado', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetXY(20, 130);
        $pdf->Cell(170, 8, 'Usuario: ' . ($certificate->user ? $certificate->user->name : 'N/A'), 0, 1, 'C');

        $pdf->SetXY(20, 140);
        $pdf->Cell(170, 8, 'Actividad: ' . ($certificate->activity ? $certificate->activity->name : 'N/A'), 0, 1, 'C');

        $pdf->SetXY(20, 150);
        $pdf->Cell(170, 8, 'Fecha: ' . ($certificate->fecha_emision ? $certificate->fecha_emision->format('d/m/Y') : date('d/m/Y')), 0, 1, 'C');

        // Guardar en storage
        $filename = 'certificates/' . uniqid('local_') . '_' . $certificate->id . '.pdf';
        $content = $pdf->Output('certificado.pdf', 'S');
        Storage::put($filename, $content);

        return $filename;
    }
}
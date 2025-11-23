import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CertificateService } from './certificate.service';

interface CertificateItem {
  id: number;
  unique_code: string;
  nombre?: string;
  activity?: { id: number; name: string } | null;
  template?: { id: number; name: string; image_path?: string } | null;
  user?: { id: number; name: string } | null;
  status: string;
  fecha_emision?: string | null;
  fecha_vencimiento?: string | null;
  created_at: string;
}

interface CertificateDetail {
  id: number;
  unique_code: string;
  nombre: string;
  activity?: { id: number; name: string } | null;
  template?: { id: number; name: string; image_path?: string } | null;
  user?: { id: number; name: string } | null;
  status: string;
  fecha_emision: string;
  fecha_vencimiento?: string | null;
  created_at: string;
}

interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

@Component({
  selector: 'app-my-certificates',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './my-certificates.component.html',
  styles: [`
    .page {
      padding: 24px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .page-header {
      margin-bottom: 32px;
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 24px;
    }

    .header-text h1 {
      margin: 0 0 8px 0;
      font-size: 2rem;
      font-weight: 600;
      color: #1a1a1a;
    }

    .header-text p {
      margin: 0;
      color: #666;
      font-size: 1rem;
    }

    .header-stats {
      display: flex;
      gap: 16px;
    }

    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 16px 24px;
      border-radius: 12px;
      text-align: center;
      min-width: 100px;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .stat-number {
      display: block;
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 0.875rem;
      opacity: 0.9;
    }

    .loading-state, .error-state, .empty-state {
      text-align: center;
      padding: 48px 24px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 4px solid #f3f3f3;
      border-top: 4px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 16px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .error-state {
      color: #dc3545;
    }

    .error-state i {
      font-size: 2rem;
      margin-bottom: 16px;
      display: block;
    }

    .empty-state {
      color: #666;
    }

    .empty-icon {
      font-size: 3rem;
      margin-bottom: 16px;
    }

    .empty-state h3 {
      margin: 0 0 8px 0;
      color: #333;
    }

    .certificates-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
      gap: 24px;
    }

    .certificate-card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
      border: 1px solid #e5e7eb;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .certificate-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .certificate-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .certificate-code {
      display: flex;
      flex-direction: column;
    }

    .code-label {
      font-size: 0.75rem;
      color: #666;
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .code-value {
      font-family: 'Courier New', monospace;
      font-size: 0.875rem;
      font-weight: 600;
      color: #333;
      background: #f8f9fa;
      padding: 4px 8px;
      border-radius: 6px;
      border: 1px solid #e9ecef;
    }

    .certificate-status {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-active {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-pending {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .status-expired {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .certificate-content {
      margin-bottom: 24px;
    }

    .certificate-title {
      margin: 0 0 16px 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: #1a1a1a;
      line-height: 1.3;
    }

    .certificate-details {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .detail-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.875rem;
    }

    .detail-item i {
      width: 16px;
      height: 16px;
      color: #667eea;
      flex-shrink: 0;
    }

    .detail-label {
      color: #666;
      font-weight: 500;
      min-width: 80px;
    }

    .detail-value {
      color: #333;
      font-weight: 400;
    }

    .certificate-actions {
      display: flex;
      justify-content: flex-end;
      padding-top: 16px;
      border-top: 1px solid #e5e7eb;
    }

    .btn-view {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .btn-view:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-view i {
      width: 16px;
      height: 16px;
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 20px;
    }

    .modal-detail {
      background: white;
      border-radius: 16px;
      max-width: 900px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px 32px;
      border-bottom: 1px solid #e5e7eb;
      background: #f8f9fa;
      border-radius: 16px 16px 0 0;
    }

    .modal-header h2 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
      color: #1a1a1a;
    }

    .btn-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #666;
      padding: 8px;
      border-radius: 8px;
      transition: all 0.2s ease;
    }

    .btn-close:hover {
      background: #e9ecef;
      color: #333;
    }

    .modal-body {
      padding: 32px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px;
    }

    .certificate-preview {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .preview-container {
      width: 100%;
      max-width: 300px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .preview-image {
      width: 100%;
      height: auto;
      display: block;
    }

    .preview-placeholder, .preview-loading {
      width: 100%;
      max-width: 300px;
      height: 200px;
      background: #f8f9fa;
      border: 2px dashed #dee2e6;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #666;
    }

    .preview-placeholder i, .preview-loading i {
      font-size: 2rem;
      margin-bottom: 8px;
    }

    .certificate-info {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .info-section h3 {
      margin: 0 0 16px 0;
      font-size: 1.125rem;
      font-weight: 600;
      color: #1a1a1a;
      padding-bottom: 8px;
      border-bottom: 2px solid #667eea;
    }

    .info-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .info-item label {
      font-size: 0.75rem;
      color: #666;
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .info-item span {
      font-size: 0.875rem;
      color: #333;
      font-weight: 500;
    }

    .status-badge {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: inline-block;
    }

    /* Download Options */
    .download-options {
      margin-top: 24px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      width: 100%;
    }

    .btn-download-pdf,
    .btn-download-jpg {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 16px;
      border: 2px solid;
      border-radius: 12px;
      background: white;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 600;
    }

    .btn-download-pdf {
      border-color: #dc3545;
      color: #dc3545;
    }

    .btn-download-pdf:hover:not(:disabled) {
      background: #dc3545;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }

    .btn-download-jpg {
      border-color: #28a745;
      color: #28a745;
    }

    .btn-download-jpg:hover:not(:disabled) {
      background: #28a745;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .btn-download-pdf:disabled,
    .btn-download-jpg:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .btn-download-pdf i,
    .btn-download-jpg i {
      font-size: 1.5rem;
    }

    .btn-download-pdf span,
    .btn-download-jpg span {
      font-size: 1rem;
    }

    .btn-download-pdf small,
    .btn-download-jpg small {
      font-size: 0.75rem;
      font-weight: 400;
      opacity: 0.8;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .certificates-grid {
        grid-template-columns: 1fr;
      }

      .header-content {
        flex-direction: column;
        gap: 16px;
      }

      .modal-body {
        grid-template-columns: 1fr;
        gap: 24px;
        padding: 24px;
      }

      .modal-detail {
        margin: 10px;
        max-height: calc(100vh - 20px);
      }
    }

    /* Icon placeholders - replace with your icon system */
    .icon-eye::before { content: '👁️'; }
    .icon-activity::before { content: '📋'; }
    .icon-template::before { content: '📄'; }
    .icon-calendar::before { content: '📅'; }
    .icon-clock::before { content: '⏰'; }
    .icon-alert::before { content: '⚠️'; }
    .icon-image::before { content: '🖼️'; }
    .icon-x::before { content: '✕'; }
  `]
})
export class MyCertificatesComponent implements OnInit {
  private certificateService = inject(CertificateService);

  certificates = signal<CertificateItem[]>([]);
  isLoading = signal(false);
  errorMessage = signal<string | null>(null);

  // Modal state
  showDetailModal = signal(false);
  selectedCertificate = signal<CertificateItem | null>(null);
  certificatePreview = signal<string | null>(null);
  loadingPreview = signal(false);

  ngOnInit() {
    this.loadMyCertificates();
  }

  private async loadMyCertificates() {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    try {
      const response = await this.certificateService.getMyCertificates().toPromise();
      this.certificates.set(response.data || []);
    } catch (error: any) {
      console.error('Error loading certificates:', error);
      this.errorMessage.set(error.message || 'Error al cargar los certificados');
    } finally {
      this.isLoading.set(false);
    }
  }

  viewCertificate(certificate: CertificateItem) {
    this.selectedCertificate.set(certificate);
    this.showDetailModal.set(true);
    this.loadCertificatePreview(certificate.id);
  }

  private async loadCertificatePreview(certificateId: number) {
    this.loadingPreview.set(true);
    this.certificatePreview.set(null);

    try {
      const response = await this.certificateService.getCertificatePreview(certificateId).toPromise();
      if (response.success && response.data.preview_url) {
        this.certificatePreview.set(response.data.preview_url);
      }
    } catch (error) {
      console.error('Error loading certificate preview:', error);
      // No mostramos error, simplemente no hay vista previa
    } finally {
      this.loadingPreview.set(false);
    }
  }

  closeDetailModal() {
    this.showDetailModal.set(false);
    this.selectedCertificate.set(null);
    this.certificatePreview.set(null);
  }

  // Download state
  downloadingPdf = signal(false);
  downloadingImage = signal(false);

  async downloadCertificateFormat(format: 'pdf' | 'jpg') {
    if (!this.selectedCertificate()) return;

    const certificateId = this.selectedCertificate()!.id;
    const isDownloadingPdf = format === 'pdf';

    if (isDownloadingPdf) {
      this.downloadingPdf.set(true);
    } else {
      this.downloadingImage.set(true);
    }

    try {
      const blob = await this.certificateService.downloadCertificateFormat(certificateId, format).toPromise();

      if (!blob) {
        throw new Error('No se pudo descargar el certificado');
      }

      // Create download link
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `certificado_${this.selectedCertificate()!.unique_code}.${format}`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error(`Error downloading certificate as ${format}:`, error);
      alert(`Error al descargar el certificado como ${format.toUpperCase()}`);
    } finally {
      if (isDownloadingPdf) {
        this.downloadingPdf.set(false);
      } else {
        this.downloadingImage.set(false);
      }
    }
  }

  getStatusLabel(status: string): string {
    const statusMap: { [key: string]: string } = {
      'active': 'Activo',
      'pending': 'Pendiente',
      'expired': 'Vencido',
      'revoked': 'Revocado'
    };
    return statusMap[status] || status;
  }
}




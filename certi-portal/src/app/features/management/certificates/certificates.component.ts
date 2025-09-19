import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { CertificateService } from './certificate.service';
import { environment } from '../../../../environments/environment';

interface Certificate {
  id: number;
  user_id: number;
  activity_id: number;
  id_template: number;
  nombre: string;
  descripcion?: string;
  fecha_emision: string;
  fecha_vencimiento?: string;
  signed_by?: number;
  unique_code: string;
  qr_url?: string;
  status: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  activity?: {
    id: number;
    name: string;
    description?: string;
    company?: {
      id: number;
      name: string;
      ruc: string;
    };
  };
  template?: {
    id: number;
    name: string;
    description?: string;
  };
  signer?: {
    id: number;
    name: string;
    email: string;
  };
  created_at: string;
  updated_at: string;
}

interface ApiResponse {
  success: boolean;
  message: string;
  data: {
    certificates: Certificate[];
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    }
  };
}

@Component({
  selector: 'app-certificates',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './certificates.component.html',
  styleUrl: './certificates.component.css'
})
export class CertificatesComponent implements OnInit {
  certificates = signal<Certificate[]>([]);
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');

  // Modal states
  showViewModal = signal(false);
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  selectedCertificate = signal<Certificate | null>(null);

  // Pagination
  currentPage = signal(1);
  totalPages = signal(1);
  perPage = signal(15);
  totalItems = signal(0);

  // Filters
  filterForm: FormGroup;

  // Create form
  createForm: FormGroup;

  // Select options
  activities = signal<{ id: number; name: string }[]>([]);
  templates = signal<{ id: number; name: string }[]>([]);
  users = signal<{ id: number; name: string; email: string }[]>([]);
  selectedTemplate = signal<any>(null);
  templatePreview = signal<string>('');

  constructor(
    private certificateService: CertificateService,
    private http: HttpClient,
    private fb: FormBuilder
  ) {
    this.filterForm = this.fb.group({
      search: [''],
      status: [''],
      template_id: [''],
      user_id: [''],
      activity_id: [''],
      fecha_emision: [''],
      fecha_vencimiento: ['']
    });

    this.createForm = this.fb.group({
      user_id: ['', [Validators.required]],
      id_template: ['', [Validators.required]],
      nombre: ['', [Validators.required, Validators.minLength(2)]],
      descripcion: [''],
      fecha_emision: ['', [Validators.required]],
      fecha_vencimiento: [''],
      activity_id: ['', [Validators.required]],
      signed_by: [''],
      status: ['issued']
    });
  }

  ngOnInit(): void {
    this.loadCertificates();
    this.loadSelectData();
  }

  loadCertificates(page: number = 1): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    // Construir parámetros de consulta
    let params: any = {
      page: page,
      per_page: this.perPage()
    };

    // Añadir filtros si están definidos
    const filters = this.filterForm.value;
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.company_id) params.company_id = filters.company_id;
    if (filters.activity_id) params.activity_id = filters.activity_id;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;

    // Realizar la petición HTTP
    this.certificateService.getCertificates(params).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.certificates.set(response.data.certificates);

          // Actualizar paginación si está disponible
          if (response.data.pagination) {
            this.currentPage.set(response.data.pagination.current_page);
            this.totalPages.set(response.data.pagination.last_page);
            this.perPage.set(response.data.pagination.per_page);
            this.totalItems.set(response.data.pagination.total);
          }
        } else {
          this.errorMessage.set(response.message || 'Error al cargar certificados');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error de conexión. Intenta nuevamente.');
        console.error('Error loading certificates:', error);
      }
    });
  }

  // Métodos para manejar la paginación
  goToPage(page: number): void {
    if (page >= 1 && page <= this.totalPages()) {
      this.loadCertificates(page);
    }
  }

  // Métodos para manejar los filtros
  applyFilters(): void {
    this.loadCertificates(1); // Reiniciar a la primera página al filtrar
  }

  resetFilters(): void {
    this.filterForm.reset({
      search: '',
      status: '',
      template_id: '',
      user_id: '',
      activity_id: '',
      fecha_emision: '',
      fecha_vencimiento: ''
    });
    this.loadCertificates(1);
  }

  // Cargar datos para selects (usuarios, actividades y plantillas)
  private loadSelectData(): void {
    // Cargar usuarios
    this.http.get<any>(`${environment.apiUrl}/users`, { params: { per_page: 100 } }).subscribe({
      next: (res) => {
        if (res?.success) {
          const items = (res.data?.users ?? res.data ?? []).map((u: any) => ({ 
            id: u.id, 
            name: u.name, 
            email: u.email 
          }));
          this.users.set(items);
        }
      },
      error: () => {}
    });

    // Cargar actividades
    this.http.get<any>(`${environment.apiUrl}/activities`, { params: { per_page: 100 } }).subscribe({
      next: (res) => {
        if (res?.success) {
          const items = (res.data?.activities ?? res.data ?? []).map((a: any) => ({ id: a.id, name: a.name || a.title }));
          this.activities.set(items);
        }
      },
      error: () => {}
    });

    // Cargar plantillas
    this.http.get<any>(`${environment.apiUrl}/certificate-templates`, { params: { per_page: 100 } }).subscribe({
      next: (res) => {
        if (res?.success) {
          const items = (res.data?.templates ?? res.data ?? []).map((t: any) => ({ id: t.id, name: t.name }));
          this.templates.set(items);
        }
      },
      error: () => {}
    });
  }

  // Métodos para manejar modales
  openViewModal(certificate: Certificate): void {
    this.selectedCertificate.set(certificate);
    this.showViewModal.set(true);
  }

  openCreateModal(): void {
    this.showCreateModal.set(true);
  }

  openEditModal(certificate: Certificate): void {
    this.selectedCertificate.set(certificate);
    this.showEditModal.set(true);
  }

  openDeleteModal(certificate: Certificate): void {
    this.selectedCertificate.set(certificate);
    this.showDeleteModal.set(true);
  }

  closeModals(): void {
    this.showViewModal.set(false);
    this.showCreateModal.set(false);
    this.showEditModal.set(false);
    this.showDeleteModal.set(false);
    this.selectedCertificate.set(null);
  }

  // Crear certificado
  createCertificate(): void {
    if (this.createForm.invalid) {
      Object.values(this.createForm.controls).forEach(c => c.markAsTouched());
      return;
    }
    this.isLoading.set(true);
    const payload = this.createForm.value;
    this.certificateService.createCertificate(payload).subscribe({
      next: (res) => {
        this.isLoading.set(false);
        if (res?.success) {
          this.successMessage.set('Certificado creado correctamente');
          this.closeModals();
          this.createForm.reset({ status: 'issued' });
          this.loadCertificates(this.currentPage());
        } else {
          this.errorMessage.set(res?.message || 'No se pudo crear el certificado');
        }
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err?.error?.message || 'Error al crear el certificado');
      }
    });
  }

  // Método para descargar un certificado
  downloadCertificate(id: number): void {
    this.certificateService.downloadCertificate(id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `certificado-${id}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
      },
      error: (error) => {
        this.errorMessage.set('Error al descargar el certificado.');
        console.error('Error downloading certificate:', error);
      }
    });
  }

  // Método para enviar certificado por email
  sendCertificateEmail(id: number): void {
    this.certificateService.sendEmail(id).subscribe({
      next: (response: any) => {
        if (response.success) {
          this.successMessage.set('Certificado enviado por correo electrónico.');
        } else {
          this.errorMessage.set(response.message || 'Error al enviar el certificado.');
        }
      },
      error: (error) => {
        this.errorMessage.set('Error al enviar el certificado por correo electrónico.');
        console.error('Error sending certificate email:', error);
      }
    });
  }

  // Método para cambiar el estado de un certificado
  changeCertificateStatus(id: number, status: string): void {
    this.certificateService.changeStatus(id, status).subscribe({
      next: (response: any) => {
        if (response.success) {
          this.successMessage.set('Estado del certificado actualizado.');
          this.loadCertificates(this.currentPage()); // Recargar para ver cambios
        } else {
          this.errorMessage.set(response.message || 'Error al actualizar el estado del certificado.');
        }
      },
      error: (error) => {
        this.errorMessage.set('Error al actualizar el estado del certificado.');
        console.error('Error changing certificate status:', error);
      }
    });
  }

  // Método para formatear fechas
  formatDate(dateString: string | undefined): string {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
  }

  // Método para obtener el color de estado
  getStatusColor(status: string): string {
    switch (status.toLowerCase()) {
      case 'emitido':
      case 'issued':
        return 'success';
      case 'pendiente':
      case 'pending':
        return 'warning';
      case 'cancelado':
      case 'cancelled':
        return 'danger';
      case 'expirado':
      case 'expired':
        return 'secondary';
      default:
        return 'primary';
    }
  }

  // Método para obtener el texto de estado
  getStatusText(status: string): string {
    switch (status.toLowerCase()) {
      case 'issued':
        return 'Emitido';
      case 'pending':
        return 'Pendiente';
      case 'cancelled':
        return 'Cancelado';
      case 'expired':
        return 'Expirado';
      default:
        return status;
    }
  }

  // Método para cargar vista previa de plantilla
  onTemplateChange(templateId: string): void {
    if (!templateId) {
      this.selectedTemplate.set(null);
      this.templatePreview.set('');
      return;
    }

    const template = this.templates().find(t => t.id === parseInt(templateId));
    if (template) {
      this.selectedTemplate.set(template);
      // Cargar vista previa de la plantilla
      this.http.get<any>(`${environment.apiUrl}/certificate-templates/${templateId}/preview`).subscribe({
        next: (res) => {
          if (res?.success && res.data?.preview_url) {
            this.templatePreview.set(res.data.preview_url);
          }
        },
        error: () => {
          this.templatePreview.set('');
        }
      });
    }
  }
}

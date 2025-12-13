import { Component, OnInit, signal, computed, inject, effect, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { CertificateService } from './certificate.service';
import { environment } from '../../../../environments/environment';
import { TemplatePreviewComponent } from '../../../shared/template-preview/template-preview.component';
import html2canvas from 'html2canvas';

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
  qr?: string;
  qr_image_url?: string;
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
  imports: [CommonModule, ReactiveFormsModule, TemplatePreviewComponent],
  templateUrl: './certificates.component.html',
  styleUrl: './certificates.component.css'
})
export class CertificatesComponent implements OnInit {
  // Mensajes
  isLoading = signal(false);
  successMessage = signal<string>('');
  errorMessage = signal<string>('');

  // Fecha de hoy para el formulario
  todayDate = new Date().toISOString().split('T')[0];

  // Señales para modales
  showViewModal = signal(false);
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  showDownloadModal = signal(false);

  // Señales para datos
  certificates = signal<Certificate[]>([]);
  selectedCertificate = signal<Certificate | null>(null);
  users = signal<{ id: number; name: string; email: string }[]>([]);
  activities = signal<{ id: number; name: string }[]>([]);
  templates = signal<{ id: number; name: string }[]>([]);
  selectedTemplate = signal<any>(null);
  templatePreview = signal('');
  certificatePreview = signal('');
  templateLoading = signal(false);
  templateInfoMessage = signal('');

  // Bulk Creation Signals
  searchTerm = signal('');
  selectedUserIds = signal<Set<number>>(new Set());
  isCreatingBatch = signal(false);
  batchProgress = signal({ current: 0, total: 0 });

  // Computed filtered users
  filteredUsers = computed(() => {
    const term = this.searchTerm().toLowerCase();
    return this.users().filter(u =>
      u.name.toLowerCase().includes(term) ||
      u.email.toLowerCase().includes(term)
    );
  });



  // Bulk Actions Signals
  selectedCertificateIds = signal<Set<number>>(new Set());
  isBulkProcessing = signal(false);
  bulkProcessState = signal({
    current: 0,
    total: 0,
    successCount: 0,
    errorCount: 0,
    action: '' as 'email' | 'delete' | ''
  });
  showBulkDeleteConfirmModal = signal(false);

  // Pagination
  currentPage = signal(1);
  totalPages = signal(1);
  perPage = signal(15);
  totalItems = signal(0);

  // Filters
  filterForm: FormGroup;

  // Create form
  createForm: FormGroup;
  // Edit form
  editForm: FormGroup;

  private _successTimer: any;
  private _errorTimer: any;

  // Efecto para auto-ocultar mensajes; debe crearse en contexto de inyección
  private autoHideEffect = effect(() => {
    const s = this.successMessage();
    if (s) {
      if (this._successTimer) clearTimeout(this._successTimer);
      this._successTimer = setTimeout(() => this.successMessage.set(''), 5000);
    }
    const e = this.errorMessage();
    if (e) {
      if (this._errorTimer) clearTimeout(this._errorTimer);
      this._errorTimer = setTimeout(() => this.errorMessage.set(''), 5000);
    }
  });

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
      nombre: ['', [Validators.required, Validators.maxLength(255)]],
      descripcion: ['', [Validators.maxLength(2000)]],
      fecha_emision: [this.todayDate, [Validators.required]],
      fecha_vencimiento: ['', [this.dateAfterValidator('fecha_emision')]],
      activity_id: ['', [Validators.required]],
      signed_by: [''],
      status: ['issued']
    });

    this.editForm = this.fb.group({
      user_id: ['', [Validators.required]],
      id_template: ['', [Validators.required]],
      nombre: ['', [Validators.required, Validators.maxLength(255)]],
      descripcion: ['', [Validators.maxLength(2000)]],
      fecha_emision: ['', [Validators.required]],
      fecha_vencimiento: [''],
      activity_id: ['', [Validators.required]],
      signed_by: [''],
      status: ['issued']
    });

    // Eliminado: no forzar errores manuales, ya existe Validators.maxLength(2000)
  }

  // Contador de caracteres de descripción

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

  // Eliminado: el efecto ahora se declara como propiedad de clase

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
      error: () => { }
    });

    // Cargar actividades
    this.http.get<any>(`${environment.apiUrl}/activities`, { params: { per_page: 100 } }).subscribe({
      next: (res) => {
        if (res?.success) {
          const items = (res.data?.activities ?? res.data ?? []).map((a: any) => ({ id: a.id, name: a.name || a.title }));
          this.activities.set(items);
        }
      },
      error: () => { }
    });

    // Cargar plantillas
    this.http.get<any>(`${environment.apiUrl}/certificate-templates`, { params: { per_page: 100 } }).subscribe({
      next: (res) => {
        if (res?.success) {
          const items = (res.data?.templates ?? res.data ?? []).map((t: any) => ({ id: t.id, name: t.name, status: t.status }));
          this.templates.set(items);
        }
      },
      error: () => { }
    });
  }

  // Métodos para manejar modales

  generatedImage = signal<string | null>(null);

  // Generate certificate image (Auto-capture)
  async generateCertificateImage(): Promise<void> {
    // Try to find visible preview first, then hidden target
    let element = document.querySelector('.certificate-preview-content') as HTMLElement;
    if (!element || element.offsetParent === null) {
      element = document.getElementById('certificate-capture-target') as HTMLElement;
    }

    if (!element) return;

    try {
      // Wait a moment for hidden container to render if it was just added
      await new Promise(resolve => setTimeout(resolve, 500));

      const canvas = await html2canvas(element, {
        scale: 2, // Higher quality
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff'
      });

      const dataUrl = canvas.toDataURL('image/png');
      this.generatedImage.set(dataUrl);
    } catch (error) {
      console.error('Error generating certificate image:', error);
    }
  }
  openCreateModal(): void {
    // Reset con valores por defecto para evitar fallo en primer clic
    this.createForm.reset({
      user_id: '', // Will be ignored in batch mode if selectedUserIds has items
      id_template: '',
      nombre: '',
      descripcion: '',
      fecha_emision: this.todayDate,
      fecha_vencimiento: '',
      activity_id: '',
      signed_by: '',
      status: 'issued'
    });
    // Reset batch selection
    this.selectedUserIds.set(new Set());
    this.searchTerm.set('');
    this.isCreatingBatch.set(false);
    this.batchProgress.set({ current: 0, total: 0 });

    this.showCreateModal.set(true);
  }

  openEditModal(certificate: Certificate): void {
    this.selectedCertificate.set(certificate);
    // Formatear fechas a YYYY-MM-DD
    const toYmd = (d: any) => {
      if (!d) return '';
      const date = new Date(d);
      if (isNaN(date.getTime())) return String(d).slice(0, 10);
      return date.toISOString().slice(0, 10);
    };
    this.editForm.reset({
      user_id: certificate.user_id,
      id_template: certificate.id_template,
      nombre: certificate.nombre,
      descripcion: certificate.descripcion || '',
      fecha_emision: toYmd(certificate.fecha_emision),
      fecha_vencimiento: toYmd(certificate.fecha_vencimiento),
      activity_id: certificate.activity_id,
      signed_by: certificate.signed_by || '',
      status: certificate.status || 'issued'
    });
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
    this.showDownloadModal.set(false);
    this.selectedCertificate.set(null);
    this.certificatePreview.set('');
  }

  // Abrir modal de descarga
  openDownloadModal(certificate: Certificate): void {
    this.selectedCertificate.set(certificate);
    this.showDownloadModal.set(true);
  }

  // Eliminar certificado
  deleteCertificate(): void {
    const certificate = this.selectedCertificate();
    if (!certificate) return;

    this.isLoading.set(true);
    this.certificateService.deleteCertificate(certificate.id).subscribe({
      next: (response: any) => {
        this.isLoading.set(false);
        if (response.success) {
          this.successMessage.set('Certificado eliminado correctamente');
          this.closeModals();
          this.loadCertificates(this.currentPage());
        } else {
          this.errorMessage.set(response.message || 'Error al eliminar el certificado');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error al eliminar el certificado');
        console.error('Error deleting certificate:', error);
      }
    });
  }

  // Descargar certificado en formato específico
  async downloadCertificateFormat(format: 'pdf' | 'jpg'): Promise<void> {
    const certificate = this.selectedCertificate();
    if (!certificate) return;

    this.isLoading.set(true);

    if (format === 'jpg') {
      // Use generated image if available
      let dataUrl = this.generatedImage();

      if (!dataUrl) {
        // Try to generate on the fly
        await this.generateCertificateImage();
        dataUrl = this.generatedImage();
      }

      if (dataUrl) {
        const link = document.createElement('a');
        link.download = `certificado_${certificate.unique_code}.png`;
        link.href = dataUrl;
        link.click();
        this.isLoading.set(false);
        this.successMessage.set('Certificado descargado (Captura)');
        this.closeModals();
        return;
      }
    }

    // Fallback to backend download (PDF or if capture failed)
    this.certificateService.downloadCertificateFormat(certificate.id, format).subscribe({
      next: (blob) => {
        this.isLoading.set(false);
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `certificado-${certificate.id}.${format}`;
        document.body.appendChild(a);
        a.click();
        if (format === 'pdf') {
          window.open(url, '_blank');
          setTimeout(() => window.URL.revokeObjectURL(url), 5000);
        } else {
          window.URL.revokeObjectURL(url);
        }
        a.remove();
        this.closeModals();
        this.successMessage.set(`Certificado descargado en formato ${format.toUpperCase()}`);
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set(`Error al descargar el certificado en formato ${format.toUpperCase()}`);
        console.error('Error downloading certificate:', error);
      }
    });
  }

  // Cargar vista previa del certificado
  loadCertificatePreview(certificateId: number): void {
    this.http.get<any>(`${environment.apiUrl}/certificates/${certificateId}/preview`).subscribe({
      next: (response) => {
        if (response?.success && response.data?.preview_url) {
          this.certificatePreview.set(response.data.preview_url);
        } else {
          this.certificatePreview.set('');
        }
      },
      error: (error) => {
        console.error('Error loading certificate preview:', error);
        this.certificatePreview.set('');
      }
    });
  }

  // Abrir modal de vista y cargar vista previa automáticamente
  openViewModal(certificate: Certificate): void {
    this.selectedCertificate.set(certificate);
    this.showViewModal.set(true);
    this.generatedImage.set(null); // Reset previous capture

    // Cargar vista previa automáticamente (Backend URL)
    this.loadCertificatePreview(certificate.id);

    // Trigger auto-capture
    setTimeout(() => {
      this.generateCertificateImage();
    }, 1000); // Wait for template to render

    const tplId = certificate.template?.id;
    if (tplId) {
      this.http.get<any>(`${environment.apiUrl}/certificate-templates/${tplId}`).subscribe({
        next: (res) => {
          const fullTemplate = res?.data?.template;
          if (fullTemplate) {
            this.selectedTemplate.set({
              id: fullTemplate.id,
              name: fullTemplate.name,
              status: fullTemplate.status,
              file_url: fullTemplate.file_url,
              qr_position: fullTemplate.qr_position,
              name_position: fullTemplate.name_position,
              date_position: fullTemplate.date_position,
              background_image_size: fullTemplate.background_image_size,
              template_styles: fullTemplate.template_styles
            });
          }
        },
        error: () => { }
      });
    }
    this.certificateService.getCertificate(certificate.id).subscribe({
      next: (detail) => {
        if (detail?.data?.certificate) {
          this.selectedCertificate.set(detail.data.certificate);
          // Re-trigger capture with full details
          setTimeout(() => this.generateCertificateImage(), 500);
        }
      },
      error: () => { }
    });
  }

  // Crear certificado (Single or Batch)
  createCertificate(): void {
    // Validate common fields
    const commonControls = ['id_template', 'nombre', 'fecha_emision', 'activity_id'];
    const isCommonValid = commonControls.every(field => this.createForm.get(field)?.valid);

    if (!isCommonValid) {
      commonControls.forEach(field => this.createForm.get(field)?.markAsTouched());
      this.errorMessage.set('Completa los campos requeridos (Plantilla, Nombre, Fecha, Actividad)');
      return;
    }

    const selectedIds = Array.from(this.selectedUserIds());
    const singleUserId = this.createForm.get('user_id')?.value;

    let targetUserIds: number[] = [];
    if (selectedIds.length > 0) {
      targetUserIds = selectedIds;
    } else if (singleUserId) {
      targetUserIds = [parseInt(singleUserId)];
    } else {
      this.errorMessage.set('Selecciona al menos un usuario');
      return;
    }

    this.isLoading.set(true);

    // Prepare data for bulk create
    const certificatesData = targetUserIds.map(userId => ({
      ...this.createForm.value,
      user_id: userId
    }));

    if (targetUserIds.length > 1) {
      this.isCreatingBatch.set(true);
      this.batchProgress.set({ current: 0, total: targetUserIds.length }); // Indeterminate or just show total
    }

    this.certificateService.bulkCreate(certificatesData).subscribe({
      next: (res: any) => {
        this.isLoading.set(false);
        this.isCreatingBatch.set(false);
        if (res.success) {
          const count = res.data?.count || targetUserIds.length;
          this.successMessage.set(`Se han creado ${count} certificados correctamente.`);
          this.closeModals();
          this.createForm.reset({ status: 'issued', fecha_emision: this.todayDate });
          this.loadCertificates(this.currentPage());
        } else {
          this.errorMessage.set(res.message || 'Error al crear certificados.');
        }
      },
      error: (err) => {
        this.isLoading.set(false);
        this.isCreatingBatch.set(false);
        this.errorMessage.set(err?.error?.message || 'Error al crear certificados.');
        console.error('Error creating certificates:', err);
      }
    });
  }

  // User Selection Methods
  toggleUser(userId: number): void {
    const current = this.selectedUserIds();
    const newSet = new Set(current);
    if (newSet.has(userId)) {
      newSet.delete(userId);
    } else {
      newSet.add(userId);
    }
    this.selectedUserIds.set(newSet);
  }

  toggleAllUsers(): void {
    const current = this.selectedUserIds();
    const filtered = this.filteredUsers();

    // If all filtered users are selected, deselect them. Otherwise select all filtered.
    const allFilteredSelected = filtered.every(u => current.has(u.id));

    const newSet = new Set(current);
    if (allFilteredSelected) {
      filtered.forEach(u => newSet.delete(u.id));
    } else {
      filtered.forEach(u => newSet.add(u.id));
    }
    this.selectedUserIds.set(newSet);
  }

  isSelected(userId: number): boolean {
    return this.selectedUserIds().has(userId);
  }

  isAllSelected(): boolean {
    const filtered = this.filteredUsers();
    if (filtered.length === 0) return false;
    return filtered.every(u => this.selectedUserIds().has(u.id));
  }

  // Actualizar certificado
  updateCertificate(): void {
    const certificate = this.selectedCertificate();
    if (!certificate) return;
    if (this.editForm.invalid) {
      Object.values(this.editForm.controls).forEach(c => c.markAsTouched());
      this.errorMessage.set('Completa los campos requeridos');
      return;
    }
    this.isLoading.set(true);
    const payload = this.editForm.value;
    this.certificateService.updateCertificate(certificate.id, payload).subscribe({
      next: (res: any) => {
        this.isLoading.set(false);
        if (res?.success) {
          this.successMessage.set('Certificado actualizado correctamente');
          this.closeModals();
          this.loadCertificates(this.currentPage());
        } else {
          this.errorMessage.set(res?.message || 'No se pudo actualizar el certificado');
        }
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err?.error?.message || 'Error al actualizar el certificado');
      }
    });
  }

  @HostListener('window:keydown', ['$event'])
  onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      if (this.showCreateModal() || this.showEditModal() || this.showDeleteModal() || this.showViewModal() || this.showDownloadModal()) {
        this.closeModals();
      }
    }
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

  // Signal to track which certificate is currently sending an email
  sendingEmailId = signal<number | null>(null);

  // Método para enviar certificado por email
  sendCertificateEmail(id: number): void {
    if (this.sendingEmailId()) return; // Prevent double clicks

    this.sendingEmailId.set(id);
    this.certificateService.sendEmail(id).subscribe({
      next: (response: any) => {
        this.sendingEmailId.set(null);
        if (response.success) {
          this.successMessage.set('Certificado enviado por correo electrónico.');
        } else {
          this.errorMessage.set(response.message || 'Error al enviar el certificado.');
        }
      },
      error: (error) => {
        this.sendingEmailId.set(null);
        this.errorMessage.set('Error al enviar el certificado por correo electrónico.');
        console.error('Error sending certificate email:', error);
      }
    });
  }

  // Bulk Actions Methods

  toggleCertificate(id: number): void {
    const current = this.selectedCertificateIds();
    const newSet = new Set(current);
    if (newSet.has(id)) {
      newSet.delete(id);
    } else {
      newSet.add(id);
    }
    this.selectedCertificateIds.set(newSet);
  }

  toggleAllCertificates(): void {
    const current = this.selectedCertificateIds();
    const allIds = this.certificates().map(c => c.id);

    // Check if all currently visible certificates are selected
    const allSelected = allIds.every(id => current.has(id));

    const newSet = new Set(current);
    if (allSelected) {
      // Deselect all visible
      allIds.forEach(id => newSet.delete(id));
    } else {
      // Select all visible
      allIds.forEach(id => newSet.add(id));
    }
    this.selectedCertificateIds.set(newSet);
  }

  isCertificateSelected(id: number): boolean {
    return this.selectedCertificateIds().has(id);
  }

  isAllCertificatesSelected(): boolean {
    const certs = this.certificates();
    if (certs.length === 0) return false;
    return certs.every(c => this.selectedCertificateIds().has(c.id));
  }

  // Bulk Send Emails
  bulkSendEmails(): void {
    const selectedIds = Array.from(this.selectedCertificateIds());
    if (selectedIds.length === 0) return;

    this.isBulkProcessing.set(true);
    // Show indeterminate state or just "Processing..."
    this.bulkProcessState.set({
      current: 0,
      total: selectedIds.length,
      successCount: 0,
      errorCount: 0,
      action: 'email'
    });

    this.certificateService.bulkSendEmail(selectedIds).subscribe({
      next: (res: any) => {
        this.isBulkProcessing.set(false);
        this.selectedCertificateIds.set(new Set()); // Clear selection
        if (res.success) {
          const queued = res.data?.queued || selectedIds.length;
          this.successMessage.set(`Se han encolado ${queued} correos para envío en segundo plano.`);
        } else {
          this.errorMessage.set(res.message || 'Error al enviar correos.');
        }
      },
      error: (err) => {
        this.isBulkProcessing.set(false);
        this.errorMessage.set(err?.error?.message || 'Error al enviar correos.');
        console.error('Error sending bulk emails:', err);
      }
    });
  }

  // Bulk Delete
  openBulkDeleteConfirm(): void {
    if (this.selectedCertificateIds().size === 0) return;
    this.showBulkDeleteConfirmModal.set(true);
  }

  closeBulkDeleteConfirm(): void {
    this.showBulkDeleteConfirmModal.set(false);
  }

  bulkDeleteCertificates(): void {
    const selectedIds = Array.from(this.selectedCertificateIds());
    if (selectedIds.length === 0) return;

    this.closeBulkDeleteConfirm();
    this.isBulkProcessing.set(true);
    this.bulkProcessState.set({
      current: 0,
      total: selectedIds.length,
      successCount: 0,
      errorCount: 0,
      action: 'delete'
    });

    this.certificateService.bulkDelete(selectedIds).subscribe({
      next: (res: any) => {
        this.isBulkProcessing.set(false);
        this.selectedCertificateIds.set(new Set()); // Clear selection
        if (res.success) {
          const deleted = res.data?.deleted || selectedIds.length;
          this.successMessage.set(`Se han eliminado ${deleted} certificados exitosamente.`);
          this.loadCertificates(this.currentPage());
        } else {
          this.errorMessage.set(res.message || 'Error al eliminar certificados.');
        }
      },
      error: (err) => {
        this.isBulkProcessing.set(false);
        this.errorMessage.set(err?.error?.message || 'Error al eliminar certificados.');
        console.error('Error deleting bulk certificates:', err);
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

  // Método para obtener la clase CSS del badge de estado
  getStatusBadgeClass(status: string): string {
    if (!status) return 'badge-default';

    switch (status.toLowerCase()) {
      case 'emitido':
      case 'issued':
        return 'badge-issued';
      case 'pendiente':
      case 'pending':
        return 'badge-pending';
      case 'cancelado':
      case 'cancelled':
        return 'badge-cancelled';
      case 'expirado':
      case 'expired':
        return 'badge-expired';
      default:
        return 'badge-default';
    }
  }

  // Método para obtener el texto de estado
  getStatusText(status?: string): string {
    const s = (status || '').toLowerCase();
    switch (s) {
      case 'issued':
        return 'Emitido';
      case 'pending':
        return 'Pendiente';
      case 'cancelled':
        return 'Cancelado';
      case 'expired':
        return 'Expirado';
      default:
        return status || '—';
    }
  }

  // Método para cargar vista previa de plantilla
  onTemplateChange(templateId: string): void {
    if (!templateId) {
      this.selectedTemplate.set(null);
      this.templatePreview.set('');
      this.templateLoading.set(false);
      this.templateInfoMessage.set('');
      return;
    }

    const template = this.templates().find(t => t.id === parseInt(templateId));
    if (template) {
      this.templateLoading.set(true);
      this.templateInfoMessage.set('Cargando datos de la plantilla...');
      // Cargar datos completos de la plantilla incluyendo posiciones
      this.http.get<any>(`${environment.apiUrl}/certificate-templates/${templateId}`).subscribe({
        next: (res) => {
          if (res?.success && res.data?.template) {
            const fullTemplate = res.data.template;
            this.selectedTemplate.set({
              ...template,
              file_url: fullTemplate.file_url,
              qr_position: fullTemplate.qr_position,
              name_position: fullTemplate.name_position,
              date_position: fullTemplate.date_position,
              background_image_size: fullTemplate.background_image_size,
              template_styles: fullTemplate.template_styles
            });
            this.templatePreview.set(fullTemplate.file_url || '');
            if (fullTemplate.file_url) {
              this.templateInfoMessage.set('Autocompletado de texto listo');
            } else {
              this.templateInfoMessage.set('La plantilla no tiene imagen de fondo; autocompletado de texto listo (QR deshabilitado)');
            }
          } else {
            this.selectedTemplate.set(template);
            this.templatePreview.set('');
            this.templateInfoMessage.set('Detalles no disponibles; usando datos básicos');
          }
          this.templateLoading.set(false);
        },
        error: () => {
          this.selectedTemplate.set(template);
          this.templatePreview.set('');
          this.templateInfoMessage.set('No se pudo cargar detalles; usando datos básicos');
          this.templateLoading.set(false);
        }
      });
    }
  }



  // Método para obtener el nombre del usuario para la vista previa
  getUserNameForPreview(): string {
    const selectedUserId = this.createForm.get('user_id')?.value;
    if (selectedUserId) {
      const user = this.users().find(u => u.id === parseInt(selectedUserId));
      return user?.name || 'Usuario Ejemplo';
    }
    return 'Usuario Ejemplo';
  }

  // Validador personalizado para fechas
  dateAfterValidator(startDateField: string) {
    return (control: AbstractControl): ValidationErrors | null => {
      if (!control.value) return null;

      const form = control.parent;
      if (!form) return null;

      const startDate = form.get(startDateField)?.value;
      if (!startDate) return null;

      const start = new Date(startDate);
      const end = new Date(control.value);

      return end <= start ? { dateAfter: true } : null;
    };
  }

  // Métodos auxiliares para la UI
  getStatusLabel(status?: string): string {
    const statusMap: { [key: string]: string } = {
      'issued': 'Emitido',
      'pending': 'Pendiente',
      'cancelled': 'Cancelado',
      'expired': 'Expirado'
    };
    const s = (status || '').toLowerCase();
    return statusMap[s] || (status || '—');
  }

  getActivityTypeLabel(type?: string): string {
    const typeMap: { [key: string]: string } = {
      'course': 'Curso',
      'workshop': 'Taller',
      'seminar': 'Seminario',
      'conference': 'Conferencia',
      'other': 'Otro'
    };
    return typeMap[type || ''] || (type || '—');
  }

  // Referencia a Math para usar en la plantilla
  Math = Math;

  // URL de QR para previsualización
  getQrPreviewUrl(): string | null {
    const cert = this.selectedCertificate();
    if (!cert) return null;
    if (cert.qr_url) return cert.qr_url;
    const data = cert.unique_code || String(cert.id || '');
    if (!data) return null;
    const sizeBase = this.selectedTemplate()?.qr_position?.width || this.selectedTemplate()?.qr_position?.size || 120;
    const size = Math.max(80, Math.min(300, Number(sizeBase || 120)));
    return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(data)}`;
  }

  private getCoordsOrigin(): 'center' | 'left-top' {
    const origin = String(this.selectedTemplate()?.template_styles?.coords_origin || 'center').toLowerCase();
    return origin === 'left-top' ? 'left-top' : 'center';
  }

  private getBaseWidth(): number {
    return Number(this.selectedTemplate()?.background_image_size?.width || 0);
  }

  private getBaseHeight(): number {
    return Number(this.selectedTemplate()?.background_image_size?.height || 0);
  }

  getNameCoordX(): string {
    const t = this.selectedTemplate();
    if (!t?.name_position) return '—';
    const baseW = this.getBaseWidth();
    const origin = this.getCoordsOrigin();
    const raw = t.name_position.x != null ? Number(t.name_position.x) : (t.name_position.left != null ? Number(t.name_position.left) - (baseW / 2) : 0);
    return raw.toFixed(2);
  }

  getNameCoordY(): string {
    const t = this.selectedTemplate();
    if (!t?.name_position) return '—';
    const baseH = this.getBaseHeight();
    const origin = this.getCoordsOrigin();
    const raw = t.name_position.y != null ? Number(t.name_position.y) : (t.name_position.top != null ? Number(t.name_position.top) - (baseH / 2) : 0);
    return raw.toFixed(2);
  }

  getDateCoordX(): string {
    const t = this.selectedTemplate();
    if (!t?.date_position) return '—';
    const baseW = this.getBaseWidth();
    const origin = this.getCoordsOrigin();
    const raw = t.date_position.x != null ? Number(t.date_position.x) : (t.date_position.left != null ? Number(t.date_position.left) - (baseW / 2) : 0);
    return raw.toFixed(2);
  }

  getDateCoordY(): string {
    const t = this.selectedTemplate();
    if (!t?.date_position) return '—';
    const baseH = this.getBaseHeight();
    const origin = this.getCoordsOrigin();
    const raw = t.date_position.y != null ? Number(t.date_position.y) : (t.date_position.top != null ? Number(t.date_position.top) - (baseH / 2) : 0);
    return raw.toFixed(2);
  }

  getQrCoordX(): string {
    const t = this.selectedTemplate();
    if (!t?.qr_position) return '—';
    const baseW = this.getBaseWidth();
    const origin = this.getCoordsOrigin();
    const raw = t.qr_position.x != null ? Number(t.qr_position.x) : (t.qr_position.left != null ? Number(t.qr_position.left) - (baseW / 2) : 0);
    return raw.toFixed(2);
  }

  getQrCoordY(): string {
    const t = this.selectedTemplate();
    if (!t?.qr_position) return '—';
    const baseH = this.getBaseHeight();
    const origin = this.getCoordsOrigin();
    const raw = t.qr_position.y != null ? Number(t.qr_position.y) : (t.qr_position.top != null ? Number(t.qr_position.top) - (baseH / 2) : 0);
    return raw.toFixed(2);
  }

  getNameSize(): string {
    const t = this.selectedTemplate();
    const s = Number(t?.name_position?.fontSize ?? 28);
    return s.toFixed(2);
  }

  getDateSize(): string {
    const t = this.selectedTemplate();
    const s = Number(t?.date_position?.fontSize ?? 16);
    return s.toFixed(2);
  }

  getQrSizeValue(): string {
    const t = this.selectedTemplate();
    const s = Number(t?.qr_position?.width ?? t?.qr_position?.size ?? t?.qr_position?.height ?? 120);
    return s.toFixed(2);
  }

  // Eliminado generador de JPG en cliente según requerimiento
}

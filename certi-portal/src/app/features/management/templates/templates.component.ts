import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { TemplateService, Template, TemplateFilters } from '../../../core/services/template.service';

@Component({
  selector: 'app-templates',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './templates.component.html',
  styleUrl: './templates.component.css'
})
export class TemplatesComponent implements OnInit {
  templates = signal<Template[]>([]);
  filteredTemplates = signal<Template[]>([]);
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');

  // File upload
  selectedFile = signal<File | null>(null);
  imagePreview = signal<string | null>(null);

  // Expose Math to template
  Math = Math;

  // Modal states
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  showViewModal = signal(false);
  showImageModal = signal(false);
  selectedTemplate = signal<Template | null>(null);

  // Image modal
  imageModalUrl = signal<string>('');
  imageModalTitle = signal<string>('');

  // Pagination
  currentPage = signal(1);
  totalPages = signal(1);
  perPage = signal(15);
  totalItems = signal(0);

  // Forms
  filterForm: FormGroup;
  templateForm: FormGroup;

  // Filter options
  activityTypes = [
    { value: 'course', label: 'Curso' },
    { value: 'event', label: 'Evento' },
    { value: 'other', label: 'Otro' }
  ];

  statusOptions = [
    { value: 'active', label: 'Activo' },
    { value: 'inactive', label: 'Inactivo' }
  ];

  constructor(
    private templateService: TemplateService,
    private fb: FormBuilder
  ) {
    this.filterForm = this.fb.group({
      search: [''],
      activity_type: [''],
      status: ['']
    });

    this.templateForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(255)]],
      description: ['', [Validators.maxLength(1000)]],
      activity_type: ['other', [Validators.required]],
      status: ['active', [Validators.required]]
    });
  }

  ngOnInit(): void {
    this.loadTemplates();
    this.setupFilterSubscription();
    this.setupMessageAutoHide();
  }

  private setupFilterSubscription(): void {
    // Búsqueda en tiempo real con debounce
    this.filterForm.get('search')?.valueChanges.pipe(
      debounceTime(300), // Esperar 300ms después del último cambio
      distinctUntilChanged() // Solo si el valor cambió
    ).subscribe(() => {
      this.applyFilters();
    });

    // Filtros inmediatos para selects
    this.filterForm.get('activity_type')?.valueChanges.subscribe(() => {
      this.applyFilters();
    });

    this.filterForm.get('status')?.valueChanges.subscribe(() => {
      this.applyFilters();
    });
  }

  private setupMessageAutoHide(): void {
    // Auto-hide success messages
    setInterval(() => {
      if (this.successMessage()) {
        setTimeout(() => this.successMessage.set(''), 5000);
      }
      if (this.errorMessage()) {
        setTimeout(() => this.errorMessage.set(''), 5000);
      }
    }, 100);
  }

  loadTemplates(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    const filters: TemplateFilters = {
      page: this.currentPage(),
      per_page: this.perPage(),
      ...this.filterForm.value
    };

    // Limpiar filtros vacíos
    Object.keys(filters).forEach(key => {
      if (filters[key as keyof TemplateFilters] === '' || filters[key as keyof TemplateFilters] === null) {
        delete filters[key as keyof TemplateFilters];
      }
    });

    this.templateService.getTemplates(filters).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.templates.set(response.data.templates);
          this.filteredTemplates.set(response.data.templates);
          this.currentPage.set(response.data.pagination.current_page);
          this.totalPages.set(response.data.pagination.last_page);
          this.totalItems.set(response.data.pagination.total);
        } else {
          this.errorMessage.set(response.message || 'Error al cargar plantillas');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error al conectar con el servidor');
        console.error('Error loading templates:', error);
      }
    });
  }

  applyFilters(): void {
    this.currentPage.set(1);
    this.loadTemplates();
  }

  clearFilters(): void {
    this.filterForm.reset({
      search: '',
      activity_type: '',
      status: ''
    });
  }

  // Pagination methods
  goToPage(page: number): void {
    if (page >= 1 && page <= this.totalPages()) {
      this.currentPage.set(page);
      this.loadTemplates();
    }
  }

  // Modal methods
  openCreateModal(): void {
    this.templateForm.reset({
      name: '',
      description: '',
      activity_type: 'other',
      status: 'active'
    });
    this.selectedFile.set(null);
    this.imagePreview.set(null);
    this.selectedTemplate.set(null);
    this.showCreateModal.set(true);
    this.clearMessages();
  }

  openEditModal(template: Template): void {
    this.selectedTemplate.set(template);
    this.templateForm.patchValue({
      name: template.name,
      description: template.description || '',
      activity_type: template.activity_type,
      status: template.status
    });
    this.selectedFile.set(null);
    this.imagePreview.set(null);
    this.showEditModal.set(true);
    this.clearMessages();
  }

  openViewModal(template: Template): void {
    this.selectedTemplate.set(template);
    this.showViewModal.set(true);
    this.clearMessages();
  }

  openDeleteModal(template: Template): void {
    this.selectedTemplate.set(template);
    this.showDeleteModal.set(true);
    this.clearMessages();
  }

  closeModals(): void {
    this.showCreateModal.set(false);
    this.showEditModal.set(false);
    this.showDeleteModal.set(false);
    this.showViewModal.set(false);
    this.selectedTemplate.set(null);
    this.selectedFile.set(null);
    this.imagePreview.set(null);
    this.clearMessages();
  }

  openImageModal(imageUrl: string, title: string): void {
    this.imageModalUrl.set(imageUrl);
    this.imageModalTitle.set(title);
    this.showImageModal.set(true);
  }

  closeImageModal(): void {
    this.showImageModal.set(false);
    this.imageModalUrl.set('');
    this.imageModalTitle.set('');
  }

  // CRUD operations
  createTemplate(): void {
    if (this.templateForm.invalid) {
      this.markFormGroupTouched();
      this.errorMessage.set('Por favor completa todos los campos requeridos');
      return;
    }

    this.isLoading.set(true);
    this.clearMessages();

    const formData = new FormData();

    // Agregar campos del formulario con validación
    const formValue = this.templateForm.value;
    console.log('Form values before sending:', formValue);
    console.log('Form valid:', this.templateForm.valid);

    // Agregar campos manualmente para asegurar que se incluyan
    formData.append('name', formValue.name || '');
    formData.append('description', formValue.description || '');
    formData.append('activity_type', formValue.activity_type || 'other');
    formData.append('status', formValue.status || 'active');
    
    // NO agregar _method para creación - solo se usa en actualizaciones

    // Agregar archivo si existe
    if (this.selectedFile()) {
      formData.append('template_file', this.selectedFile()!);
      console.log('Added file to FormData:', this.selectedFile()!.name);
    }

    // Debug: mostrar todos los campos del FormData
    console.log('FormData contents:');
    formData.forEach((value, key) => {
      console.log(`${key}: ${value}`);
    });

    this.templateService.createTemplate(formData).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.successMessage.set('Plantilla creada exitosamente');
          this.closeModals();
          this.loadTemplates();
        } else {
          this.errorMessage.set(response.message || 'Error al crear plantilla');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error al crear plantilla');
        console.error('Error creating template:', error);
        console.error('Error details:', error.error);
      }
    });
  }

  updateTemplate(): void {
    if (this.templateForm.invalid || !this.selectedTemplate()) {
      this.markFormGroupTouched();
      this.errorMessage.set('Por favor completa todos los campos requeridos');
      return;
    }

    this.isLoading.set(true);
    this.clearMessages();

    const formData = new FormData();

    // Agregar campos del formulario con validación explícita
    const formValue = this.templateForm.value;
    console.log('Update form values before sending:', formValue);
    console.log('Update form valid:', this.templateForm.valid);

    // Agregar campos manualmente para asegurar que se incluyan
    formData.append('name', formValue.name || '');
    formData.append('description', formValue.description || '');
    formData.append('activity_type', formValue.activity_type || 'other');
    formData.append('status', formValue.status || 'active');

    // Agregar archivo si existe
    if (this.selectedFile()) {
      formData.append('template_file', this.selectedFile()!);
      console.log('Update - Added file to FormData:', this.selectedFile()!.name);
    }

    // Debug: mostrar todos los campos del FormData
    console.log('Update FormData contents:');
    formData.forEach((value, key) => {
      console.log(`${key}: ${value}`);
    });

    const templateId = this.selectedTemplate()!.id;

    console.log('🚀 Enviando petición de actualización:', {
      templateId,
      url: `http://localhost:8000/api/certificate-templates/${templateId}`,
      method: 'PUT'
    });

    this.templateService.updateTemplate(templateId, formData).subscribe({
      next: (response) => {
        console.log('✅ Respuesta recibida del servidor:', response);
        this.isLoading.set(false);
        if (response.success) {
          this.successMessage.set('Plantilla actualizada exitosamente');
          console.log('🔄 Recargando lista de plantillas...');
          this.closeModals();
          this.loadTemplates();
        } else {
          console.error('❌ Error en la respuesta del servidor:', response.message);
          this.errorMessage.set(response.message || 'Error al actualizar plantilla');
        }
      },
      error: (error) => {
        console.error('❌ Error en la petición HTTP:', error);
        console.error('Status:', error.status);
        console.error('StatusText:', error.statusText);
        console.error('Error body:', error.error);
        this.isLoading.set(false);
        this.errorMessage.set('Error al actualizar plantilla');
      }
    });
  }

  deleteTemplate(): void {
    if (!this.selectedTemplate()) return;

    this.isLoading.set(true);
    this.clearMessages();

    const templateId = this.selectedTemplate()!.id;

    this.templateService.deleteTemplate(templateId).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.successMessage.set('Plantilla eliminada exitosamente');
          this.closeModals();
          this.loadTemplates();
        } else {
          this.errorMessage.set(response.message || 'Error al eliminar plantilla');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error al eliminar plantilla');
        console.error('Error deleting template:', error);
      }
    });
  }

  toggleTemplateStatus(template: Template): void {
    const newStatus = template.status === 'active' ? 'inactive' : 'active';
    this.clearMessages();

    this.templateService.toggleTemplateStatus(template.id, newStatus).subscribe({
      next: (response) => {
        if (response.success) {
          this.successMessage.set(`Plantilla ${newStatus === 'active' ? 'activada' : 'desactivada'} exitosamente`);
          this.loadTemplates();
        } else {
          this.errorMessage.set(response.message || 'Error al cambiar estado');
        }
      },
      error: (error) => {
        this.errorMessage.set('Error al cambiar estado de la plantilla');
        console.error('Error toggling template status:', error);
      }
    });
  }

  // Utility methods
  private markFormGroupTouched(): void {
    Object.keys(this.templateForm.controls).forEach(key => {
      const control = this.templateForm.get(key);
      control?.markAsTouched();
      control?.markAsDirty();
    });
  }

  private clearMessages(): void {
    this.errorMessage.set('');
    this.successMessage.set('');
  }

  // File handling methods
  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];

      // Validar tipo de archivo
      if (!file.type.startsWith('image/')) {
        this.errorMessage.set('Por favor selecciona un archivo de imagen válido');
        return;
      }

      // Validar tamaño (máximo 5MB)
      if (file.size > 5 * 1024 * 1024) {
        this.errorMessage.set('El archivo es demasiado grande. Máximo 5MB');
        return;
      }

      this.selectedFile.set(file);

      // Crear vista previa
      const reader = new FileReader();
      reader.onload = (e) => {
        this.imagePreview.set(e.target?.result as string);
      };
      reader.readAsDataURL(file);

      this.clearMessages();
    }
  }

  removeImage(): void {
    this.selectedFile.set(null);
    this.imagePreview.set(null);

    // Limpiar el input file
    const fileInputs = document.querySelectorAll('input[type="file"]') as NodeListOf<HTMLInputElement>;
    fileInputs.forEach(input => {
      input.value = '';
    });
  }

  formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Getters for form validation
  get nameInvalid(): boolean {
    const control = this.templateForm.get('name');
    return !!(control && control.invalid && (control.dirty || control.touched));
  }

  get descriptionInvalid(): boolean {
    const control = this.templateForm.get('description');
    return !!(control && control.invalid && (control.dirty || control.touched));
  }

  get activityTypeInvalid(): boolean {
    const control = this.templateForm.get('activity_type');
    return !!(control && control.invalid && (control.dirty || control.touched));
  }

  get statusInvalid(): boolean {
    const control = this.templateForm.get('status');
    return !!(control && control.invalid && (control.dirty || control.touched));
  }

  // Helper methods
  getActivityTypeLabel(type: string): string {
    const activityType = this.activityTypes.find(t => t.value === type);
    return activityType ? activityType.label : type;
  }

  getStatusLabel(status: string): string {
    const statusOption = this.statusOptions.find(s => s.value === status);
    return statusOption ? statusOption.label : status;
  }

  getStatusBadgeClass(status: string): string {
    return status === 'active' ? 'badge-success' : 'badge-danger';
  }

  getActivityTypeBadgeClass(type: string): string {
    switch (type) {
      case 'course': return 'type-course';
      case 'event': return 'type-event';
      default: return 'type-other';
    }
  }
}

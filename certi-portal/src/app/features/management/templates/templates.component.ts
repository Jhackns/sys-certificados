import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
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

  // Expose Math to template
  Math = Math;

  // Modal states
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  showViewModal = signal(false);
  selectedTemplate = signal<Template | null>(null);

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
  }

  private setupFilterSubscription(): void {
    this.filterForm.valueChanges.subscribe(() => {
      this.applyFilters();
    });
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
    this.showCreateModal.set(true);
  }

  openEditModal(template: Template): void {
    this.selectedTemplate.set(template);
    this.templateForm.patchValue({
      name: template.name,
      description: template.description || '',
      activity_type: template.activity_type,
      status: template.status
    });
    this.showEditModal.set(true);
  }

  openViewModal(template: Template): void {
    this.selectedTemplate.set(template);
    this.showViewModal.set(true);
  }

  openDeleteModal(template: Template): void {
    this.selectedTemplate.set(template);
    this.showDeleteModal.set(true);
  }

  closeModals(): void {
    this.showCreateModal.set(false);
    this.showEditModal.set(false);
    this.showDeleteModal.set(false);
    this.showViewModal.set(false);
    this.selectedTemplate.set(null);
    this.clearMessages();
  }

  // CRUD operations
  createTemplate(): void {
    if (this.templateForm.invalid) {
      this.markFormGroupTouched();
      return;
    }

    this.isLoading.set(true);
    const formData = this.templateForm.value;

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
      }
    });
  }

  updateTemplate(): void {
    if (this.templateForm.invalid || !this.selectedTemplate()) {
      this.markFormGroupTouched();
      return;
    }

    this.isLoading.set(true);
    const formData = this.templateForm.value;
    const templateId = this.selectedTemplate()!.id;

    this.templateService.updateTemplate(templateId, formData).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.successMessage.set('Plantilla actualizada exitosamente');
          this.closeModals();
          this.loadTemplates();
        } else {
          this.errorMessage.set(response.message || 'Error al actualizar plantilla');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error al actualizar plantilla');
        console.error('Error updating template:', error);
      }
    });
  }

  deleteTemplate(): void {
    if (!this.selectedTemplate()) return;

    this.isLoading.set(true);
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

  cloneTemplate(template: Template): void {
    const newData = {
      name: `${template.name} (Copia)`,
      description: template.description,
      activity_type: template.activity_type,
      status: 'inactive' as const
    };

    this.templateService.cloneTemplate(template.id, newData).subscribe({
      next: (response) => {
        if (response.success) {
          this.successMessage.set('Plantilla clonada exitosamente');
          this.loadTemplates();
        } else {
          this.errorMessage.set(response.message || 'Error al clonar plantilla');
        }
      },
      error: (error) => {
        this.errorMessage.set('Error al clonar plantilla');
        console.error('Error cloning template:', error);
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



import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { TemplateService, Template, TemplateFilters } from '../../../core/services/template.service';
import { TemplatePreviewComponent } from '../../../shared/template-preview/template-preview.component';
import { ApiResponse } from '../../../core/models/api-response.model';

@Component({
  selector: 'app-templates',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TemplatePreviewComponent],
  templateUrl: './templates.component.html',
  styleUrls: ['./templates.component.css']
})
export class TemplatesComponent implements OnInit {
  templates = signal<Template[]>([]);
  isLoading = signal(false);
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  selectedTemplate = signal<Template | null>(null);

  // Paginación
  currentPage = signal(1);
  totalPages = signal(1);
  totalItems = signal(0);
  perPage = signal(10);
  filteredTemplates = signal<Template[]>([]);
  showViewModal = signal(false);

  // Mensajes
  successMessage = signal<string>('');
  errorMessage = signal<string>('');

  // Validación de Canva
  isValidatingCanvaLink = signal(false);
  canvaLinkValidated = signal(false);
  canvaLinkValid = signal(false);

  // Referencia a Math para usar en la plantilla
  Math = Math;

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
      status: ['active', [Validators.required]],
      canva_design_id: ['', Validators.required]
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

  // Validar enlace de Canva
  validateCanvaLink(): void {
    const canvaLink = this.templateForm.get('canva_design_id')?.value;

    if (!canvaLink) {
      this.canvaLinkValid.set(false);
      this.canvaLinkValidated.set(true);
      return;
    }

    this.isValidatingCanvaLink.set(true);
    this.canvaLinkValidated.set(false);

    // Extraer el ID de diseño del enlace si es una URL completa
    let designId = canvaLink;
    if (canvaLink.includes('canva.com/design/')) {
      const match = canvaLink.match(/\/design\/([^\/]+)/);
      if (match && match[1]) {
        designId = match[1];
      }
    }

    // Simular una validación con el servicio de Canva
    this.templateService.validateCanvaDesign(designId).subscribe({
      next: (response) => {
        this.isValidatingCanvaLink.set(false);
        this.canvaLinkValidated.set(true);
        this.canvaLinkValid.set(response.success);

        // Actualizar el valor en el formulario con el ID extraído
        if (response.success && designId !== canvaLink) {
          this.templateForm.get('canva_design_id')?.setValue(designId, { emitEvent: false });
        }
      },
      error: () => {
        this.isValidatingCanvaLink.set(false);
        this.canvaLinkValidated.set(true);
        this.canvaLinkValid.set(false);
      }
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
    this.selectedTemplate.set(null);
    this.showCreateModal.set(true);
    this.clearMessages();

    // Limpiar validación de Canva
    this.canvaLinkValidated.set(false);
    this.canvaLinkValid.set(false);
    this.isValidatingCanvaLink.set(false);
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
    this.selectedTemplate.set(null);
    this.templateForm.reset({
      activity_type: 'other',
      status: 'active'
    });

    // Limpiar validación de Canva
    this.canvaLinkValidated.set(false);
    this.canvaLinkValid.set(false);
    this.isValidatingCanvaLink.set(false);
  }

  clearMessages(): void {
    this.successMessage.set('');
    this.errorMessage.set('');
  }

  getStatusBadgeClass(status: string): string {
    return status === 'active' ? 'badge-success' : 'badge-danger';
  }

  // Métodos auxiliares para la UI
  getStatusLabel(status: string): string {
    const statusMap: { [key: string]: string } = {
      'active': 'Activo',
      'inactive': 'Inactivo'
    };
    return statusMap[status] || status;
  }

  getActivityTypeLabel(type: string): string {
    const typeMap: { [key: string]: string } = {
      'course': 'Curso',
      'workshop': 'Taller',
      'seminar': 'Seminario',
      'conference': 'Conferencia',
      'other': 'Otro'
    };
    return typeMap[type] || type;
  }

  getActivityTypeBadgeClass(type: string): string {
    const classMap: { [key: string]: string } = {
      'course': 'badge-course',
      'workshop': 'badge-workshop',
      'seminar': 'badge-seminar',
      'conference': 'badge-conference',
      'other': 'badge-other'
    };
    return classMap[type] || 'badge-default';
  }

  private markFormGroupTouched(): void {
    Object.keys(this.templateForm.controls).forEach(key => {
      this.templateForm.get(key)?.markAsTouched();
    });
  }

  // Métodos para el formulario
  createTemplate(): void {
    if (this.templateForm.invalid) {
      this.markFormGroupTouched();
      this.errorMessage.set('Por favor completa todos los campos requeridos');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    // Crear objeto con los datos del formulario
    const templateData = {
      name: this.templateForm.get('name')?.value,
      description: this.templateForm.get('description')?.value,
      activity_type: this.templateForm.get('activity_type')?.value,
      status: this.templateForm.get('status')?.value,
      canva_design_id: this.templateForm.get('canva_design_id')?.value
    };

    this.templateService.createTemplate(templateData).subscribe({
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
      this.errorMessage.set('Por favor completa todos los campos requeridos');
      return;
    }

    this.isLoading.set(true);
    this.clearMessages();

    // Crear objeto con los datos del formulario
    const templateData = {
      name: this.templateForm.get('name')?.value,
      description: this.templateForm.get('description')?.value,
      activity_type: this.templateForm.get('activity_type')?.value,
      status: this.templateForm.get('status')?.value,
      canva_design_id: this.templateForm.get('canva_design_id')?.value,
      _method: 'PUT'
    };

    const templateId = this.selectedTemplate()!.id;

    this.templateService.updateTemplate(templateId, templateData).subscribe({
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
}

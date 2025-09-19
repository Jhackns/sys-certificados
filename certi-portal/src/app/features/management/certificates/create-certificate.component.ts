import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule, AbstractControl, ValidationErrors } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { CertificateService } from './certificate.service';

interface User {
  id: number;
  name: string;
  email: string;
}

interface Activity {
  id: number;
  name: string;
  description?: string;
  type: string;
}

interface Template {
  id: number;
  name: string;
  description?: string;
}

interface TemplatePreview {
  id: number;
  name: string;
  description?: string;
  html_content?: string;
  css_styles?: string;
  company?: {
    id: number;
    name: string;
    logo_url?: string;
  };
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Component({
  selector: 'app-create-certificate',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './create-certificate.component.html',
  styleUrl: './create-certificate.component.css'
})
export class CreateCertificateComponent implements OnInit {
  // Form
  certificateForm: FormGroup;
  
  // Data signals
  users = signal<User[]>([]);
  activities = signal<Activity[]>([]);
  templates = signal<Template[]>([]);
  templatePreview = signal<TemplatePreview | null>(null);
  
  // State signals
  isLoading = signal(false);
  isLoadingPreview = signal(false);
  errorMessage = signal('');
  successMessage = signal('');
  isSubmitting = signal(false);

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private certificateService: CertificateService,
    private router: Router
  ) {
    this.certificateForm = this.fb.group({
      user_id: ['', [Validators.required]],
      activity_id: ['', [Validators.required]],
      id_template: ['', [Validators.required]],
      nombre: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(255)]],
      descripcion: ['', [Validators.maxLength(1000)]],
      fecha_emision: [this.getCurrentDate(), [Validators.required]],
      fecha_vencimiento: ['', [this.dateAfterValidator('fecha_emision')]],
      signed_by: ['', [Validators.maxLength(255)]],
      status: ['active']
    });
  }

  ngOnInit(): void {
    this.loadFormData();
    this.setupTemplatePreview();
  }

  private getCurrentDate(): string {
    return new Date().toISOString().split('T')[0];
  }

  private setupTemplatePreview(): void {
    // Escuchar cambios en la selección de plantilla
    this.certificateForm.get('id_template')?.valueChanges.subscribe(templateId => {
      if (templateId) {
        this.loadTemplatePreview(templateId);
      } else {
        this.templatePreview.set(null);
      }
    });

    // Escuchar cambios en otros campos para actualizar la vista previa
    this.certificateForm.valueChanges.subscribe(formData => {
      if (this.templatePreview() && formData.id_template) {
        this.updatePreviewWithFormData(formData);
      }
    });
  }

  private loadFormData(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    // Cargar usuarios, actividades y plantillas en paralelo
    Promise.all([
      this.loadUsers(),
      this.loadActivities(),
      this.loadTemplates()
    ]).then(() => {
      this.isLoading.set(false);
    }).catch(error => {
      this.isLoading.set(false);
      this.errorMessage.set('Error al cargar los datos del formulario');
      console.error('Error loading form data:', error);
    });
  }

  private loadUsers(): Promise<void> {
    return new Promise((resolve, reject) => {
      this.http.get<ApiResponse<{ users: User[] }>>(`${environment.apiUrl}/users/list`).subscribe({
        next: (response) => {
          if (response.success) {
            this.users.set(response.data.users);
            resolve();
          } else {
            reject(new Error(response.message));
          }
        },
        error: (error) => reject(error)
      });
    });
  }

  private loadActivities(): Promise<void> {
    return new Promise((resolve, reject) => {
      this.http.get<ApiResponse<{ activities: Activity[] }>>(`${environment.apiUrl}/activities/list`).subscribe({
        next: (response) => {
          if (response.success) {
            this.activities.set(response.data.activities);
            resolve();
          } else {
            reject(new Error(response.message));
          }
        },
        error: (error) => reject(error)
      });
    });
  }

  private loadTemplates(): Promise<void> {
    return new Promise((resolve, reject) => {
      this.http.get<ApiResponse<{ templates: Template[] }>>(`${environment.apiUrl}/certificate-templates/list`).subscribe({
        next: (response) => {
          if (response.success) {
            this.templates.set(response.data.templates);
            resolve();
          } else {
            reject(new Error(response.message));
          }
        },
        error: (error) => reject(error)
      });
    });
  }

  private loadTemplatePreview(templateId: number): void {
    this.isLoadingPreview.set(true);
    
    this.http.get<ApiResponse<{ template: TemplatePreview }>>(`${environment.apiUrl}/certificate-templates/${templateId}/preview`).subscribe({
      next: (response) => {
        this.isLoadingPreview.set(false);
        if (response.success) {
          const template = response.data.template;
          this.templatePreview.set(template);
          // Actualizar la vista previa con los datos actuales del formulario
          this.updatePreviewWithFormData(this.certificateForm.value);
        } else {
          console.error('Error loading template preview:', response.message);
        }
      },
      error: (error) => {
        this.isLoadingPreview.set(false);
        console.error('Error loading template preview:', error);
      }
    });
  }

  private updatePreviewWithFormData(formData: any): void {
    const currentTemplate = this.templatePreview();
    if (!currentTemplate || !currentTemplate.html_content) return;

    let updatedHtml = currentTemplate.html_content;

    // Reemplazar placeholders con datos del formulario
    const replacements: { [key: string]: string } = {
      '{{nombre}}': formData.nombre || '[Nombre del Certificado]',
      '{{user_name}}': this.getUserName(formData.user_id) || '[Nombre del Usuario]',
      '{{activity_name}}': this.getActivityName(formData.activity_id) || '[Nombre de la Actividad]',
      '{{fecha_emision}}': formData.fecha_emision ? this.formatDate(formData.fecha_emision) : '[Fecha de Emisión]',
      '{{fecha_vencimiento}}': formData.fecha_vencimiento ? this.formatDate(formData.fecha_vencimiento) : '',
      '{{descripcion}}': formData.descripcion || '',
      '{{signed_by}}': formData.signed_by || '[Firmado por]',
      '{{company_name}}': currentTemplate.company?.name || '[Nombre de la Empresa]'
    };

    // Aplicar reemplazos
    Object.keys(replacements).forEach(placeholder => {
      const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
      updatedHtml = updatedHtml.replace(regex, replacements[placeholder]);
    });

    // Actualizar el template con el HTML modificado
    this.templatePreview.set({
      ...currentTemplate,
      html_content: updatedHtml
    });
  }

  private getUserName(userId: number): string {
    if (!userId) return '';
    const user = this.users().find(u => u.id === userId);
    return user?.name || '';
  }

  private getActivityName(activityId: number): string {
    if (!activityId) return '';
    const activity = this.activities().find(a => a.id === activityId);
    return activity?.name || '';
  }

  private formatDate(dateString: string): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  onSubmit(): void {
    if (!this.validateForm()) {
      this.errorMessage.set('Por favor, corrija los errores en el formulario antes de continuar.');
      return;
    }

    this.isSubmitting.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    const formData = this.certificateForm.value;

    this.certificateService.createCertificate(formData).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        if (response.success) {
          this.successMessage.set('Certificado creado exitosamente');
          setTimeout(() => {
            this.router.navigate(['/principal/certificados']);
          }, 2000);
        } else {
          this.errorMessage.set(response.message || 'Error al crear el certificado');
        }
      },
      error: (error) => {
        this.isSubmitting.set(false);
        console.error('Error creating certificate:', error);
        this.errorMessage.set(this.handleApiError(error));
      }
    });
  }

  private markFormGroupTouched(): void {
    Object.keys(this.certificateForm.controls).forEach(key => {
      const control = this.certificateForm.get(key);
      control?.markAsTouched();
      control?.markAsDirty();
    });
  }

  onCancel(): void {
    this.router.navigate(['/principal/certificados']);
  }

  onTemplateChange(event: any): void {
    const templateId = parseInt(event.target.value);
    if (templateId) {
      this.loadTemplatePreview(templateId);
    } else {
      this.templatePreview.set(null);
    }
  }

  private validateForm(): boolean {
    if (this.certificateForm.invalid) {
      this.markFormGroupTouched();
      return false;
    }
    return true;
  }

  private handleApiError(error: any): string {
    if (error.error?.errors) {
      // Handle validation errors from backend
      const errors = error.error.errors;
      let errorMessages: string[] = [];
      
      Object.keys(errors).forEach(field => {
        if (Array.isArray(errors[field])) {
          errorMessages = errorMessages.concat(errors[field]);
        }
      });
      
      return errorMessages.join('. ');
    } else if (error.error?.message) {
      return error.error.message;
    } else if (error.status === 0) {
      return 'Error de conexión. Verifique su conexión a internet.';
    } else if (error.status >= 500) {
      return 'Error interno del servidor. Intente nuevamente más tarde.';
    } else {
      return 'Error al crear el certificado. Por favor, intente nuevamente.';
    }
  }
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

  // Getters for form validation
  get userIdInvalid() {
    const control = this.certificateForm.get('user_id');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get activityIdInvalid() {
    const control = this.certificateForm.get('activity_id');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get templateIdInvalid() {
    const control = this.certificateForm.get('id_template');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get nombreInvalid() {
    const control = this.certificateForm.get('nombre');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get descripcionInvalid() {
    const control = this.certificateForm.get('descripcion');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get fechaEmisionInvalid() {
    const control = this.certificateForm.get('fecha_emision');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get fechaVencimientoInvalid() {
    const control = this.certificateForm.get('fecha_vencimiento');
    return control?.invalid && (control?.dirty || control?.touched);
  }

  get signedByInvalid() {
    const control = this.certificateForm.get('signed_by');
    return control?.invalid && (control?.dirty || control?.touched);
  }
}
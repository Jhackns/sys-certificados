import { Component, signal, OnInit, HostListener, effect } from '@angular/core';
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

  // Subida de imagen y editor visual
  uploadedFile: File | null = null;
  uploadedPreviewUrl: string | null = null;
  isDragging = false;
  dragOffset = { x: 0, y: 0 };
  backgroundOffset = signal<{ x: number; y: number }>({ x: 0, y: 0 });
  backgroundImageSize = signal<{ width: number; height: number } | null>(null);
  editorCanvasSize = signal<{ width: number; height: number } | null>(null);
  showEditor = signal(false);
  editorElements = signal<{ type: 'name' | 'date' | 'qr'; x: number; y: number; width?: number; height?: number; fontFamily?: string; fontSize?: number; rotation?: number; color?: string; fontWeight?: string }[]>([]);
  selectedElementIndex = signal<number | null>(null);
  availableFonts = signal<string[]>([]);
  private fallbackFonts: string[] = [
    'Arial', 'Helvetica', 'Times New Roman', 'Courier New', 'Verdana', 'Georgia', 'Trebuchet MS', 'Tahoma', 'Calibri', 'Cambria', 'Segoe UI', 'Garamond', 'Bookman', 'Palatino', 'Comic Sans MS'
  ];

  // Snapshot for Undo
  private editorStateSnapshot: any = null;

  private snapshotState(): void {
    this.editorStateSnapshot = {
      elements: JSON.parse(JSON.stringify(this.editorElements())),
      backgroundOffset: { ...this.backgroundOffset() },
      editorCanvasSize: this.editorCanvasSize() ? { ...this.editorCanvasSize() } : null
    };
  }

  private restoreState(): void {
    if (this.editorStateSnapshot) {
      this.editorElements.set(this.editorStateSnapshot.elements);
      this.backgroundOffset.set(this.editorStateSnapshot.backgroundOffset);
      this.editorCanvasSize.set(this.editorStateSnapshot.editorCanvasSize);
      this.editorStateSnapshot = null;
    }
  }

  private loadTemplateToSignals(full: any): void {
    this.uploadedPreviewUrl = (full?.file_url || full?.file_path || this.uploadedPreviewUrl);

    const offX = Number(full?.template_styles?.background_offset?.x ?? 0);
    const offY = Number(full?.template_styles?.background_offset?.y ?? 0);
    this.backgroundOffset.set({ x: Math.round(offX), y: Math.round(offY) });

    // Preserve editor canvas size
    const canvasSz = full?.template_styles?.editor_canvas_size;
    let savedW = 0;
    let savedH = 0;

    if (canvasSz && canvasSz.width && canvasSz.height) {
      savedW = Number(canvasSz.width);
      savedH = Number(canvasSz.height);
      this.editorCanvasSize.set({
        width: savedW,
        height: savedH
      });
    } else {
      // Fallback to background image size if available (for legacy templates)
      const bgSz = full?.background_image_size;
      if (bgSz && bgSz.width && bgSz.height) {
        savedW = Number(bgSz.width);
        savedH = Number(bgSz.height);
        // Set it as editor canvas size so it gets saved on next update
        this.editorCanvasSize.set({
          width: savedW,
          height: savedH
        });
      } else {
        this.editorCanvasSize.set(null);
      }
    }

    // Helper to resolve absolute coordinates
    const getAbsX = (pos: any) => {
      if (pos.left != null) return Number(pos.left);
      // Si solo tenemos X relativo y conocemos el ancho original, convertimos
      if (pos.x != null && savedW > 0) return Number(pos.x) + (savedW / 2);
      // Fallback: si no hay savedW, asumimos que x ya es absoluto o no podemos convertir
      return pos.x != null ? Number(pos.x) : null;
    };

    const getAbsY = (pos: any) => {
      if (pos.top != null) return Number(pos.top);
      if (pos.y != null && savedH > 0) return Number(pos.y) + (savedH / 2);
      return pos.y != null ? Number(pos.y) : null;
    };

    const els: { type: 'name' | 'date' | 'qr'; x: number; y: number; width?: number; height?: number; fontFamily?: string; fontSize?: number; rotation?: number; color?: string; fontWeight?: string }[] = [];

    const namePos = (full as any)?.name_position;
    if (namePos) {
      const x = getAbsX(namePos);
      const y = getAbsY(namePos);
      if (x != null && y != null) {
        els.push({
          type: 'name',
          x: Math.round(x),
          y: Math.round(y),
          fontFamily: String(namePos.fontFamily || 'Arial'),
          fontSize: Number(namePos.fontSize || 28),
          rotation: Number(namePos.rotation || 0),
          color: String(namePos.color || '#000'),
          fontWeight: String(namePos.fontWeight || 'normal')
        });
      }
    }
    const datePos = (full as any)?.date_position;
    if (datePos) {
      const x = getAbsX(datePos);
      const y = getAbsY(datePos);
      if (x != null && y != null) {
        els.push({
          type: 'date',
          x: Math.round(x),
          y: Math.round(y),
          fontFamily: String(namePos.fontFamily || 'Arial'), // Fallback to name font if missing? Or default Arial
          fontSize: Number(datePos.fontSize || 16),
          rotation: Number(datePos.rotation || 0),
          color: String(datePos.color || '#333'),
          fontWeight: String(datePos.fontWeight || 'normal')
        });
      }
    }
    const qrPos = (full as any)?.qr_position;
    if (qrPos) {
      const x = getAbsX(qrPos);
      const y = getAbsY(qrPos);
      if (x != null && y != null) {
        els.push({ type: 'qr', x: Math.round(x), y: Math.round(y), width: Number(qrPos.width || qrPos.size || 120), height: Number(qrPos.height || qrPos.size || 120), rotation: Number(qrPos.rotation || 0) });
      }
    }
    if (els.length) {
      this.editorElements.set(els);
      this.selectedElementIndex.set(0);
    } else {
      this.editorElements.set([]);
      this.selectedElementIndex.set(null);
    }
  }

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
      status: ['active', [Validators.required]]
    });
  }

  ngOnInit(): void {
    this.loadTemplates();
    this.setupFilterSubscription();
    this.loadAvailableFonts();
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

  async loadAvailableFonts(): Promise<void> {
    try {
      const res = await this.templateService.getFonts().toPromise();
      let fonts = res?.data?.fonts || [];
      if (!fonts || fonts.length === 0) {
        fonts = [...this.fallbackFonts];
      }
      const sorted = fonts.sort((a: string, b: string) => a.localeCompare(b));
      this.availableFonts.set(sorted);
    } catch (e) {
      this.availableFonts.set([...this.fallbackFonts]);
    }
  }

  // Eliminado: el efecto ahora se declara como propiedad de clase

  // Manejo de subida de imagen
  onTemplateFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) {
      return;
    }
    const file = input.files[0];
    if (!file.type.startsWith('image/')) {
      this.errorMessage.set('Selecciona un archivo de imagen válido (JPG/PNG).');
      return;
    }
    this.uploadedFile = file;
    const reader = new FileReader();
    reader.onload = () => {
      this.uploadedPreviewUrl = reader.result as string;
      this.hasBeenEdited = false; // Reset edited flag
      const img = new Image();
      img.onload = () => {
        const w = img.naturalWidth;
        const h = img.naturalHeight;
        this.backgroundImageSize.set({ width: w, height: h });

        // Initialize default elements scaled to image size
        // Standard reference width: 800px. If image is 2000px, scale is 2.5
        const scale = w > 1000 ? w / 800 : 1;
        const midX = Math.round(w / 2);
        const midY = Math.round(h / 2);

        this.editorElements.set([
          {
            type: 'name',
            x: midX,
            y: midY, // Center
            fontFamily: 'Arial',
            fontSize: 28,
            color: '#000000',
            fontWeight: 'bold',
            rotation: 0
          }
        ]);
        this.selectedElementIndex.set(0);
      };
      img.src = this.uploadedPreviewUrl as string;
    };
    reader.readAsDataURL(file);
  }

  removeSelectedImage(): void {
    this.uploadedFile = null;
    this.uploadedPreviewUrl = null;
    this.editorElements.set([]);
    this.selectedElementIndex.set(null);
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

    this.removeSelectedImage();
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
    this.templateService.getTemplate(template.id).subscribe({
      next: (res) => {
        const full = res?.data?.template || template;
        this.selectedTemplate.set(full);
        this.loadTemplateToSignals(full);
      },
      error: () => { }
    });
  }

  openDeleteModal(template: Template): void {
    this.clearMessages();
    // Si la plantilla tiene certificados asociados, impedir eliminación y mostrar mensaje
    const count = (template as any).certificates_count || 0;
    if (count > 0) {
      this.errorMessage.set('No se puede eliminar una plantilla que tiene certificados asociados (' + count + ').');
      this.showDeleteModal.set(false);
      this.selectedTemplate.set(null);
      return;
    }
    this.selectedTemplate.set(template);
    this.showDeleteModal.set(true);
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

    this.removeSelectedImage();
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
    // Validar que se haya subido imagen y agregado Nombres
    const hasName = this.editorElements().some(el => el.type === 'name');
    if (!this.uploadedFile) {
      this.errorMessage.set('Debes subir una imagen de fondo de plantilla.');
      return;
    }
    if (!hasName) {
      this.errorMessage.set('Debes agregar y posicionar el cuadro de Nombres.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    const formData = new FormData();
    formData.append('name', this.templateForm.get('name')?.value);
    formData.append('description', this.templateForm.get('description')?.value || '');
    formData.append('activity_type', this.templateForm.get('activity_type')?.value);
    formData.append('status', this.templateForm.get('status')?.value);
    formData.append('template_file', this.uploadedFile);
    const bgSize = this.backgroundImageSize();
    if (bgSize) {
      formData.append('background_image_size[width]', String(bgSize.width));
      formData.append('background_image_size[height]', String(bgSize.height));
    }

    // Determine effective canvas size (use background size if editor size is missing/unedited)
    let canvasSize = this.editorCanvasSize();
    if (!canvasSize && bgSize) {
      canvasSize = { width: bgSize.width, height: bgSize.height };
    }

    if (canvasSize) {
      formData.append('template_styles[editor_canvas_size][width]', String(Math.round(canvasSize.width)));
      formData.append('template_styles[editor_canvas_size][height]', String(Math.round(canvasSize.height)));
    }
    formData.append('template_styles[coords_origin]', 'center');
    formData.append('template_styles[is_edited]', this.hasBeenEdited ? 'true' : 'false');

    // Helper to compute center-relative coordinates using the EFFECTIVE canvas size
    const computeRelative = (el: { x: number; y: number }) => {
      if (canvasSize) {
        const midX = Math.round(canvasSize.width / 2);
        const midY = Math.round(canvasSize.height / 2);
        return {
          x: Math.round((el.x || 0) - midX),
          y: Math.round((el.y || 0) - midY)
        };
      }
      return { x: Math.round(el.x || 0), y: Math.round(el.y || 0) };
    };

    // Incluir desplazamiento del fondo en estilos de plantilla
    const bgOffCreate = this.backgroundOffset();
    formData.append('template_styles[background_offset][x]', String(Math.round(bgOffCreate.x)));
    formData.append('template_styles[background_offset][y]', String(Math.round(bgOffCreate.y)));
    // Guardar componentes existentes (name/date/qr)
    this.editorElements().forEach(el => {
      formData.append('template_styles[components][]', el.type);
    });

    const nameEl = this.editorElements().find(el => el.type === 'name')!;
    const dateEl = this.editorElements().find(el => el.type === 'date');
    const qrEl = this.editorElements().find(el => el.type === 'qr');

    const namePosCreate = computeRelative(nameEl!);
    formData.append('name_position[x]', String(namePosCreate.x));
    formData.append('name_position[y]', String(namePosCreate.y));
    formData.append('name_position[left]', String(Math.round(nameEl.x)));
    formData.append('name_position[top]', String(Math.round(nameEl.y)));
    formData.append('name_position[fontSize]', String(nameEl.fontSize || 28));
    formData.append('name_position[fontFamily]', String(nameEl.fontFamily || 'Arial'));
    formData.append('name_position[color]', String(nameEl.color || '#000'));
    formData.append('name_position[rotation]', String(nameEl.rotation || 0));
    formData.append('name_position[fontWeight]', String(nameEl.fontWeight || 'normal'));

    if (dateEl) {
      const datePosCreate = computeRelative(dateEl);
      formData.append('date_position[x]', String(datePosCreate.x));
      formData.append('date_position[y]', String(datePosCreate.y));
      formData.append('date_position[left]', String(Math.round(dateEl.x)));
      formData.append('date_position[top]', String(Math.round(dateEl.y)));
      formData.append('date_position[fontSize]', String(dateEl.fontSize || 16));
      formData.append('date_position[fontFamily]', String(dateEl.fontFamily || 'Arial'));
      formData.append('date_position[color]', String(dateEl.color || '#333'));
      formData.append('date_position[rotation]', String(dateEl.rotation || 0));
      formData.append('date_position[fontWeight]', String(dateEl.fontWeight || 'normal'));
    }

    if (qrEl) {
      const qrPosCreate = computeRelative(qrEl);
      formData.append('qr_position[x]', String(qrPosCreate.x));
      formData.append('qr_position[y]', String(qrPosCreate.y));
      formData.append('qr_position[left]', String(Math.round(qrEl.x)));
      formData.append('qr_position[top]', String(Math.round(qrEl.y)));
      formData.append('qr_position[width]', String(qrEl.width || 120));
      formData.append('qr_position[height]', String(qrEl.height || 120));
      formData.append('qr_position[rotation]', String(qrEl.rotation || 0));
    }

    this.templateService.createTemplate(formData).subscribe({
      next: (response) => {
        const createdId = (response as any)?.data?.template?.id;
        if (response.success && createdId) {
          const fd = new FormData();
          fd.append('_method', 'PUT');
          const bg = this.backgroundOffset();
          fd.append('template_styles[background_offset][x]', String(Math.round(bg.x)));
          fd.append('template_styles[background_offset][y]', String(Math.round(bg.y)));
          const bgSz = this.backgroundImageSize();
          if (bgSz) {
            fd.append('background_image_size[width]', String(bgSz.width));
            fd.append('background_image_size[height]', String(bgSz.height));
          }
          fd.append('template_styles[coords_origin]', 'center');
          fd.append('template_styles[is_edited]', this.hasBeenEdited ? 'true' : 'false');

          // Ensure editor_canvas_size is saved so coordinates have a reference
          const canvasSz = this.editorCanvasSize();
          if (canvasSz) {
            fd.append('template_styles[editor_canvas_size][width]', String(Math.round(canvasSz.width)));
            fd.append('template_styles[editor_canvas_size][height]', String(Math.round(canvasSz.height)));
          }

          // Append components list (CRITICAL: Missing this caused components to not render on unedited templates)
          this.editorElements().forEach(el => {
            fd.append('template_styles[components][]', el.type);
          });
          const nameElUpd = this.editorElements().find(el => el.type === 'name');
          const dateElUpd = this.editorElements().find(el => el.type === 'date');
          const qrElUpd = this.editorElements().find(el => el.type === 'qr');
          if (nameElUpd) {
            const namePosUpd = this.computeOriginAdjusted(nameElUpd);
            fd.append('name_position[x]', String(namePosUpd.x));
            fd.append('name_position[y]', String(namePosUpd.y));
            fd.append('name_position[fontSize]', String(nameElUpd.fontSize || 28));
            fd.append('name_position[fontFamily]', String(nameElUpd.fontFamily || 'Arial'));
            fd.append('name_position[color]', String(nameElUpd.color || '#000'));
            fd.append('name_position[rotation]', String(nameElUpd.rotation || 0));
            fd.append('name_position[fontWeight]', String(nameElUpd.fontWeight || 'normal'));
            fd.append('name_position[textAlign]', 'left');
          }
          if (dateElUpd) {
            const datePosUpd = this.computeOriginAdjusted(dateElUpd);
            fd.append('date_position[x]', String(datePosUpd.x));
            fd.append('date_position[y]', String(datePosUpd.y));
            fd.append('date_position[fontSize]', String(dateElUpd.fontSize || 16));
            fd.append('date_position[fontFamily]', String(dateElUpd.fontFamily || 'Arial'));
            fd.append('date_position[color]', String(dateElUpd.color || '#333'));
            fd.append('date_position[rotation]', String(dateElUpd.rotation || 0));
            fd.append('date_position[fontWeight]', String(dateElUpd.fontWeight || 'normal'));
            fd.append('date_position[textAlign]', 'left');
          }
          if (qrElUpd) {
            const qrPosUpd = this.computeOriginAdjusted(qrElUpd);
            fd.append('qr_position[x]', String(qrPosUpd.x));
            fd.append('qr_position[y]', String(qrPosUpd.y));
            fd.append('qr_position[width]', String(qrElUpd.width || 120));
            fd.append('qr_position[height]', String(qrElUpd.height || 120));
            fd.append('qr_position[rotation]', String(qrElUpd.rotation || 0));
          }
          this.templateService.updateTemplate(createdId, fd).subscribe({
            next: (updRes) => {
              this.isLoading.set(false);
              if (updRes.success) {
                this.successMessage.set('Plantilla creada y posiciones guardadas');
                this.closeModals();
                this.loadTemplates();
              } else {
                this.errorMessage.set(updRes.message || 'Plantilla creada, pero no se guardaron posiciones');
              }
            },
            error: (updErr) => {
              this.isLoading.set(false);
              this.errorMessage.set(updErr?.error?.message || 'Plantilla creada, error al guardar posiciones');
              console.error('Error updating positions after create:', updErr);
            }
          });
        } else {
          this.isLoading.set(false);
          if (response.success) {
            this.successMessage.set('Plantilla creada exitosamente');
            this.closeModals();
            this.loadTemplates();
          } else {
            this.errorMessage.set(response.message || 'Error al crear plantilla');
          }
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        if (error?.status === 422) {
          const errs = error?.error?.errors || {};
          const messages = Object.keys(errs).map(k => Array.isArray(errs[k]) ? errs[k].join(' ') : String(errs[k]));
          this.errorMessage.set(messages.length ? messages[0] : (error?.error?.message || 'Datos inválidos (422)'));
        } else {
          this.errorMessage.set(error?.error?.message || 'Error al crear plantilla');
        }
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

    const formData = new FormData();
    formData.append('name', this.templateForm.get('name')?.value);
    formData.append('description', this.templateForm.get('description')?.value || '');
    formData.append('activity_type', this.templateForm.get('activity_type')?.value);
    formData.append('status', this.templateForm.get('status')?.value);
    formData.append('_method', 'PUT');

    if (this.uploadedFile) {
      formData.append('template_file', this.uploadedFile);
    }
    const bgSizeUpd = this.backgroundImageSize();
    if (bgSizeUpd) {
      formData.append('background_image_size[width]', String(bgSizeUpd.width));
      formData.append('background_image_size[height]', String(bgSizeUpd.height));
    }

    // Ensure editor_canvas_size is sent
    const canvasUpd = this.editorCanvasSize();
    if (canvasUpd) {
      formData.append('template_styles[editor_canvas_size][width]', String(Math.round(canvasUpd.width)));
      formData.append('template_styles[editor_canvas_size][height]', String(Math.round(canvasUpd.height)));
    } else if (this.selectedTemplate()?.template_styles?.editor_canvas_size) {
      // Fallback to existing canvas size
      const existing = this.selectedTemplate()!.template_styles.editor_canvas_size;
      formData.append('template_styles[editor_canvas_size][width]', String(existing.width));
      formData.append('template_styles[editor_canvas_size][height]', String(existing.height));
    }
    formData.append('template_styles[is_edited]', this.hasBeenEdited ? 'true' : 'false');

    // Append components list
    this.editorElements().forEach(el => {
      formData.append('template_styles[components][]', el.type);
    });

    // Find elements
    const nameEl = this.editorElements().find(el => el.type === 'name');
    const dateEl = this.editorElements().find(el => el.type === 'date');
    const qrEl = this.editorElements().find(el => el.type === 'qr');

    // Name is required (enforced by validation, but good to be safe)
    if (nameEl) {
      const namePos = this.computeOriginAdjusted(nameEl);
      formData.append('name_position[x]', String(namePos.x));
      formData.append('name_position[y]', String(namePos.y));
      formData.append('name_position[left]', String(Math.round(nameEl.x)));
      formData.append('name_position[top]', String(Math.round(nameEl.y)));
      formData.append('name_position[fontSize]', String(nameEl.fontSize || 28));
      formData.append('name_position[fontFamily]', String(nameEl.fontFamily || 'Arial'));
      formData.append('name_position[color]', String(nameEl.color || '#000'));
      formData.append('name_position[rotation]', String(nameEl.rotation || 0));
      formData.append('name_position[fontWeight]', String(nameEl.fontWeight || 'normal'));
    }

    // Date (Optional)
    if (dateEl) {
      const datePos = this.computeOriginAdjusted(dateEl);
      formData.append('date_position[x]', String(datePos.x));
      formData.append('date_position[y]', String(datePos.y));
      formData.append('date_position[left]', String(Math.round(dateEl.x)));
      formData.append('date_position[top]', String(Math.round(dateEl.y)));
      formData.append('date_position[fontSize]', String(dateEl.fontSize || 16));
      formData.append('date_position[fontFamily]', String(dateEl.fontFamily || 'Arial'));
      formData.append('date_position[color]', String(dateEl.color || '#333'));
      formData.append('date_position[rotation]', String(dateEl.rotation || 0));
      formData.append('date_position[fontWeight]', String(dateEl.fontWeight || 'normal'));
    } else {
      formData.append('date_position', '');
    }

    // QR (Optional)
    if (qrEl) {
      const qrPos = this.computeOriginAdjusted(qrEl);
      formData.append('qr_position[x]', String(qrPos.x));
      formData.append('qr_position[y]', String(qrPos.y));
      formData.append('qr_position[left]', String(Math.round(qrEl.x)));
      formData.append('qr_position[top]', String(Math.round(qrEl.y)));
      formData.append('qr_position[width]', String(qrEl.width || 120));
      formData.append('qr_position[height]', String(qrEl.height || 120));
      formData.append('qr_position[rotation]', String(qrEl.rotation || 0));
    } else {
      formData.append('qr_position', '');
    }

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
        if (error?.status === 422) {
          const errs = error?.error?.errors || {};
          const messages = Object.keys(errs).map(k => Array.isArray(errs[k]) ? errs[k].join(' ') : String(errs[k]));
          this.errorMessage.set(messages.length ? messages[0] : (error?.error?.message || 'Datos inválidos (422)'));
        } else {
          this.errorMessage.set(error?.error?.message || 'Error al actualizar plantilla');
        }
        console.error('Error updating template:', error);
      }
    });
  }

  // Editor visual
  openPositionEditor(): void {
    // Snapshot state for undo
    this.snapshotState();

    const currentUrl = this.uploadedPreviewUrl;
    if (!currentUrl) {
      this.errorMessage.set('Sube una imagen de fondo de plantilla para editar posiciones.');
      return;
    }

    const img = new Image();
    img.onload = () => {
      this.backgroundImageSize.set({ width: img.naturalWidth, height: img.naturalHeight });
    };
    img.src = currentUrl;

    this.showEditor.set(true);

    // Logic to adjust to current canvas size (responsive)
    setTimeout(() => {
      const bgImgEl = document.querySelector('.editor-background') as HTMLImageElement | null;
      const adjust = () => {
        const layerEl = document.querySelector('.editor-layer') as HTMLElement | null;
        const w = layerEl ? layerEl.clientWidth : (bgImgEl ? bgImgEl.clientWidth : 0);
        const h = layerEl ? layerEl.clientHeight : (bgImgEl ? bgImgEl.clientHeight : 0);

        if (w > 0 && h > 0) {
          // Current display size
          const currentW = w;
          const currentH = h;

          // Saved canvas size (from DB or previous edit)
          let savedW = this.editorCanvasSize()?.width || 0;
          let savedH = this.editorCanvasSize()?.height || 0;

          // Fallback: If no saved size, use background image size (Natural dimensions)
          // This handles the case where the template was just uploaded (coords are natural) but editorCanvasSize wasn't set yet.
          if (savedW === 0) {
            const bgSz = this.backgroundImageSize();
            if (bgSz) {
              savedW = bgSz.width;
              savedH = bgSz.height;
            }
          }

          // Update current canvas size
          this.editorCanvasSize.set({ width: w, height: h });

          // Calculate scale if we have a saved size
          const scaleX = savedW > 0 ? currentW / savedW : 1;
          const scaleY = savedH > 0 ? currentH / savedH : 1;

          // If scale is effectively 1, we don't need to do anything special IF the elements are already loaded correctly.
          // But we should re-verify positions just in case.

          // However, editorElements are already loaded via loadTemplateToSignals or preserved.
          // They contain the positions relative to the SAVED canvas (or absolute).
          // Wait, loadTemplateToSignals loads them as they were saved.
          // If they were saved with absolute pixels (left/top), we should prioritize that?
          // Actually, the previous fix in openPositionEditor handled this re-mapping.
          // We should keep that re-mapping logic but apply it to the CURRENT signals.

          const els2 = this.editorElements().map(el => {
            // Logic similar to previous fix: determine base pos, then scale
            let baseX = el.x;
            let baseY = el.y;

            // Try to find absolute coords if stored in the element (we added them in getElementAsPos, but loadTemplateToSignals might not have them if not saved)
            // Actually loadTemplateToSignals does NOT put 'left'/'top' into the editorElements array items directly, 
            // it puts 'x' and 'y'.
            // But wait, 'x' and 'y' in editorElements are intended to be current canvas relative?
            // Yes.

            // If we just loaded from DB, 'x' and 'y' are from the DB.
            // If DB has 'left'/'top', we should use them as base if possible?
            // loadTemplateToSignals uses:
            // x = namePos.x ...

            // If we want to support responsive scaling, we need to know the ORIGINAL canvas size of those coordinates.
            // That is 'savedW' / 'savedH'.

            // So:
            // 1. Take current el.x / el.y (which are from DB/State)
            // 2. Assume they are relative to savedW/savedH
            // 3. Scale to currentW/currentH

            // BUT, what if we have absolute coordinates?
            // In loadTemplateToSignals, we didn't store 'left'/'top' in the editorElement object.
            // We should probably have stored them if we wanted to use them here.
            // However, the previous fix relied on 't' (the raw template).
            // Now 't' is gone, we only have signals.

            // If we want to be robust, we should trust that el.x/el.y are correct for the savedCanvasSize.
            // So scaling them is the right approach.

            const newX = el.x * scaleX;
            const newY = el.y * scaleY;

            return { ...el, x: Math.round(newX), y: Math.round(newY) };
          });

          this.editorElements.set(els2);

          if (this.editorElements().length === 0) {
            this.addNameElement(true);
          }
        } else {
          setTimeout(adjust, 50);
        }
      };

      if (bgImgEl && bgImgEl.complete) {
        adjust();
      } else if (bgImgEl) {
        bgImgEl.addEventListener('load', adjust, { once: true });
      } else {
        adjust();
      }
    }, 0);
  }

  closePositionEditor(): void {
    this.showEditor.set(false);
    this.isDragging = false;
  }

  // Flag to track if the template has been edited in the visual editor
  hasBeenEdited = false;

  cancelEditor(): void {
    this.restoreState();
    this.closePositionEditor();
  }

  saveEditor(): void {
    // Mark as edited
    this.hasBeenEdited = true;
    // No guardamos en BD, solo cerramos. Los cambios quedan en los signals (editorElements, etc)
    // y se enviarán cuando el usuario haga clic en "Actualizar Plantilla" o "Crear Plantilla".
    this.successMessage.set('Posiciones listas. Recuerda guardar la plantilla para aplicar los cambios.');
    this.closePositionEditor();
  }

  updateBackgroundOffsetX(event: Event): void {
    const value = parseInt((event.target as HTMLInputElement).value, 10);
    const cur = this.backgroundOffset();
    this.backgroundOffset.set({ x: isNaN(value) ? cur.x : Math.round(value), y: cur.y });
  }

  updateBackgroundOffsetY(event: Event): void {
    const value = parseInt((event.target as HTMLInputElement).value, 10);
    const cur = this.backgroundOffset();
    this.backgroundOffset.set({ x: cur.x, y: isNaN(value) ? cur.y : Math.round(value) });
  }

  nudgeBackground(dx: number, dy: number): void {
    const cur = this.backgroundOffset();
    this.backgroundOffset.set({ x: cur.x + dx, y: cur.y + dy });
  }

  addNameElement(center = false): void {
    const existingIndex = this.editorElements().findIndex(e => e.type === 'name');
    if (existingIndex !== -1) {
      const els = [...this.editorElements()];
      els.splice(existingIndex, 1);
      this.editorElements.set(els);
      this.selectedElementIndex.set(null);
      return;
    }
    const canvasRect = this.getCanvasRect();
    const x = center ? Math.round(canvasRect.width / 2) : 40; // top-left anclado en centro => coords relativas 0,0
    const y = center ? Math.round(canvasRect.height / 2) : 40;
    const el = { type: 'name' as const, x, y, fontFamily: 'Arial', fontSize: 28, rotation: 0, color: '#000' };
    this.editorElements.set([...this.editorElements(), el]);
    this.selectedElementIndex.set(this.editorElements().length - 1);
  }

  addDateElement(): void {
    const existingIndex = this.editorElements().findIndex(e => e.type === 'date');
    if (existingIndex !== -1) {
      const els = [...this.editorElements()];
      els.splice(existingIndex, 1);
      this.editorElements.set(els);
      this.selectedElementIndex.set(null);
      return;
    }
    const canvasRect = this.getCanvasRect();
    const el = { type: 'date' as const, x: Math.round(canvasRect.width / 2), y: Math.round(canvasRect.height / 2), fontFamily: 'Arial', fontSize: 16, rotation: 0, color: '#333' };
    this.editorElements.set([...this.editorElements(), el]);
    this.selectedElementIndex.set(this.editorElements().length - 1);
  }

  addQrElement(): void {
    const existingIndex = this.editorElements().findIndex(e => e.type === 'qr');
    if (existingIndex !== -1) {
      const els = [...this.editorElements()];
      els.splice(existingIndex, 1);
      this.editorElements.set(els);
      this.selectedElementIndex.set(null);
      return;
    }
    const el = { type: 'qr' as const, x: 80, y: 120, width: 120, height: 120 };
    this.editorElements.set([...this.editorElements(), el]);
    this.selectedElementIndex.set(this.editorElements().length - 1);
  }

  selectElement(index: number): void {
    this.selectedElementIndex.set(index);
  }

  getSelected() {
    const idx = this.selectedElementIndex();
    if (idx === null) return null;
    return this.editorElements()[idx] || null;
  }

  updateFontFamily(event: Event): void {
    const el = this.getSelected();
    if (!el) return;
    const value = (event.target as HTMLSelectElement).value;
    el.fontFamily = value;
    this.editorElements.set([...this.editorElements()]);
  }

  updateFontSize(event: Event): void {
    const el = this.getSelected();
    if (!el) return;
    const value = parseInt((event.target as HTMLInputElement).value, 10);
    el.fontSize = isNaN(value) ? el.fontSize : value;
    this.editorElements.set([...this.editorElements()]);
    this.clampSelectedWithinCanvas();
  }

  // Ajusta tamaño del QR (ancho y alto iguales)
  updateQrSize(event: Event): void {
    const el = this.getSelected();
    if (!el || el.type !== 'qr') return;
    const value = parseInt((event.target as HTMLInputElement).value, 10);
    const size = isNaN(value) ? (el.width || 120) : Math.max(20, Math.min(value, 1000));
    el.width = size;
    el.height = size;
    this.editorElements.set([...this.editorElements()]);
    this.clampSelectedWithinCanvas();
  }

  updateRotation(event: Event): void {
    const el = this.getSelected();
    if (!el) return;
    const value = parseInt((event.target as HTMLInputElement).value, 10);
    el.rotation = isNaN(value) ? (el.rotation || 0) : value;
    this.editorElements.set([...this.editorElements()]);
    this.clampSelectedWithinCanvas();
  }

  updateColor(event: Event): void {
    const val = (event.target as HTMLInputElement).value;
    const idx = this.selectedElementIndex();
    if (idx === null) return;
    const els = [...this.editorElements()];
    els[idx] = { ...els[idx], color: val };
    this.editorElements.set(els);
  }

  startDrag(event: MouseEvent, index: number): void {
    this.selectElement(index);
    this.isDragging = true;
    const target = event.target as HTMLElement;
    const rect = target.getBoundingClientRect();
    this.dragOffset = { x: event.clientX - rect.left, y: event.clientY - rect.top };
    window.addEventListener('mousemove', this.onDragBound);
    window.addEventListener('mouseup', this.endDragBound);
  }

  private onDragBound = (event: MouseEvent) => this.onDrag(event);
  private endDragBound = () => this.endDrag();

  onDrag(event: MouseEvent): void {
    if (!this.isDragging || this.selectedElementIndex() === null) return;
    const idx = this.selectedElementIndex()!;
    const canvasRect = this.getCanvasRect();
    const x = event.clientX - canvasRect.left - this.dragOffset.x;
    const y = event.clientY - canvasRect.top - this.dragOffset.y;
    const domEls = Array.from(document.querySelectorAll('.editor-element')) as HTMLElement[];
    const domEl = domEls[idx] || null;
    const elW = domEl ? domEl.offsetWidth : (this.editorElements()[idx].type === 'qr' ? (this.editorElements()[idx].width || 0) : 1);
    const elH = domEl ? domEl.offsetHeight : (this.editorElements()[idx].type === 'qr' ? (this.editorElements()[idx].height || 0) : 1);
    const els = [...this.editorElements()];
    const maxX = canvasRect.width - elW;
    const maxY = canvasRect.height - elH;
    els[idx] = { ...els[idx], x: Math.max(0, Math.min(x, maxX)), y: Math.max(0, Math.min(y, maxY)) };
    this.editorElements.set(els);
  }

  endDrag(): void {
    this.isDragging = false;
    window.removeEventListener('mousemove', this.onDragBound);
    window.removeEventListener('mouseup', this.endDragBound);
  }

  private getCanvasRect(): { left: number; top: number; width: number; height: number } {
    const layer = document.querySelector('.editor-layer') as HTMLElement | null;
    if (!layer) return { left: 0, top: 0, width: 800, height: 600 };
    const rect = layer.getBoundingClientRect();
    return { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
  }

  private clampSelectedWithinCanvas(): void {
    const idx = this.selectedElementIndex();
    if (idx === null) return;
    const canvasRect = this.getCanvasRect();
    if (canvasRect.width === 0 || canvasRect.height === 0) return;
    const domEls = Array.from(document.querySelectorAll('.editor-element')) as HTMLElement[];
    const domEl = domEls[idx] || null;
    const elW = domEl ? domEl.offsetWidth : (this.editorElements()[idx].type === 'qr' ? (this.editorElements()[idx].width || 0) : 1);
    const elH = domEl ? domEl.offsetHeight : (this.editorElements()[idx].type === 'qr' ? (this.editorElements()[idx].height || 0) : 1);
    const maxX = Math.max(0, canvasRect.width - elW);
    const maxY = Math.max(0, canvasRect.height - elH);
    const els = [...this.editorElements()];
    const cur = els[idx];
    // Ensure x and y are numbers
    const currentX = Number(cur.x) || 0;
    const currentY = Number(cur.y) || 0;
    els[idx] = { ...cur, x: Math.max(0, Math.min(currentX, maxX)), y: Math.max(0, Math.min(currentY, maxY)) };
    this.editorElements.set(els);
  }

  private computeOriginAdjusted(el: { x: number; y: number; type?: 'name' | 'date' | 'qr'; width?: number; height?: number }): { x: number; y: number } {
    const canSz = this.editorCanvasSize();
    if (canSz) {
      const midX = Math.round(canSz.width / 2);
      const midY = Math.round(canSz.height / 2);
      const rx = Math.round((el.x || 0) - midX);
      const ry = Math.round((el.y || 0) - midY);
      return { x: rx, y: ry };
    }
    return { x: Math.round(el.x || 0), y: Math.round(el.y || 0) };
  }

  deleteSelectedElement(): void {
    const idx = this.selectedElementIndex();
    if (idx === null) return;
    const els = [...this.editorElements()];
    els.splice(idx, 1);
    this.editorElements.set(els);
    this.selectedElementIndex.set(null);
  }

  hasElement(type: 'name' | 'date' | 'qr'): boolean {
    return this.editorElements().some(e => e.type === type);
  }

  isSelectedType(type: 'name' | 'date' | 'qr'): boolean {
    const idx = this.selectedElementIndex();
    return idx !== null && this.editorElements()[idx]?.type === type;
  }

  // Coordenadas relativas al centro del lienzo (mostrar solo)
  getSelectedRelativeX(): number {
    const el = this.getSelected();
    if (!el) return 0;
    const canvasRect = this.getCanvasRect();
    return Math.round((el.x || 0) - (canvasRect.width / 2));
  }

  getSelectedRelativeY(): number {
    const el = this.getSelected();
    if (!el) return 0;
    const canvasRect = this.getCanvasRect();
    return Math.round((el.y || 0) - (canvasRect.height / 2));
  }

  updateFontWeight(): void {
    const idx = this.selectedElementIndex();
    if (idx === null) return;
    const els = [...this.editorElements()];
    const currentWeight = els[idx].fontWeight || 'normal';
    els[idx] = { ...els[idx], fontWeight: currentWeight === 'bold' ? 'normal' : 'bold' };
    this.editorElements.set(els);
  }

  private getElementAsPos(type: 'name' | 'date' | 'qr'): any {
    const el = this.editorElements().find(e => e.type === type);
    if (!el) return null;

    // Calcular relativas
    const rel = this.computeOriginAdjusted(el);

    return {
      x: rel.x,
      y: rel.y,
      left: Math.round(el.x),
      top: Math.round(el.y),
      width: el.width,
      height: el.height,
      fontSize: el.fontSize,
      fontFamily: el.fontFamily,
      color: el.color,
      fontWeight: el.fontWeight || 'normal',
      rotation: el.rotation,
      textAlign: 'left'
    };

  }

  @HostListener('window:keydown', ['$event'])
  onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      if (this.showEditor()) {
        this.cancelEditor();
        return;
      }
      if (this.showCreateModal() || this.showEditModal() || this.showDeleteModal() || this.showViewModal()) {
        this.closeModals();
        return;
      }
    }
    if (this.showEditor() && event.key === 'Delete') {
      this.deleteSelectedElement();
    }
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
        if (error?.status === 409) {
          this.errorMessage.set(error?.error?.message || 'No se puede eliminar una plantilla que tiene certificados asociados');
        } else {
          this.errorMessage.set(error?.error?.message || 'Error al eliminar plantilla');
        }
        console.error('Error deleting template:', error);
      }
    });
  }
}
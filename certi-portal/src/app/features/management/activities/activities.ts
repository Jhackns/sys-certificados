import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivityService, Activity } from '../../../core/services/activity';

@Component({
  selector: 'app-activities',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule],
  templateUrl: './activities.html',
  styleUrl: './activities.css'
})
export class ActivitiesComponent implements OnInit {
  activities = signal<Activity[]>([]);
  filteredActivities = signal<Activity[]>([]);
  loading = signal(false);
  showModal = signal(false);
  editingActivity = signal<Activity | null>(null);
  searchTerm = signal('');
  statusFilter = signal('all');
  typeFilter = signal('all');

  activityForm: FormGroup;

  constructor(
    private activityService: ActivityService,
    private fb: FormBuilder
  ) {
    this.activityForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(3)]],
      description: [''],
      type: ['other', Validators.required],
      duration_hours: [null, [Validators.min(1)]],
      start_date: [''],
      end_date: [''],
      is_active: [true]
    });
  }

  ngOnInit() {
    // Inicializar filteredActivities con un array vacío
    this.filteredActivities.set([]);
    this.loadActivities();
  }

  loadActivities() {
    this.loading.set(true);
    this.activityService.getActivities().subscribe({
      next: (activities) => {
        console.log('Activities loaded:', activities);
        this.activities.set(activities || []);
        this.applyFilters();
        this.loading.set(false);
      },
      error: (error) => {
        console.error('Error loading activities:', error);
        this.activities.set([]);
        this.filteredActivities.set([]);
        this.loading.set(false);
      }
    });
  }

  applyFilters() {
    let filtered = this.activities() || [];

    // Filtro por búsqueda
    if (this.searchTerm()) {
      const term = this.searchTerm().toLowerCase();
      filtered = filtered.filter(activity => 
        activity.name.toLowerCase().includes(term) ||
        (activity.description && activity.description.toLowerCase().includes(term))
      );
    }

    // Filtro por estado
    if (this.statusFilter() !== 'all') {
      const isActive = this.statusFilter() === 'active';
      filtered = filtered.filter(activity => activity.is_active === isActive);
    }

    // Filtro por tipo
    if (this.typeFilter() !== 'all') {
      filtered = filtered.filter(activity => activity.type === this.typeFilter());
    }

    this.filteredActivities.set(filtered);
  }

  onSearchChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.searchTerm.set(target.value);
    this.applyFilters();
  }

  onStatusFilterChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    this.statusFilter.set(target.value);
    this.applyFilters();
  }

  onTypeFilterChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    this.typeFilter.set(target.value);
    this.applyFilters();
  }

  openCreateModal() {
    this.editingActivity.set(null);
    this.activityForm.reset({
      name: '',
      description: '',
      type: 'other',
      duration_hours: null,
      start_date: '',
      end_date: '',
      is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(activity: Activity) {
    this.editingActivity.set(activity);
    this.activityForm.patchValue({
      name: activity.name,
      description: activity.description || '',
      type: activity.type,
      duration_hours: activity.duration_hours,
      start_date: activity.start_date || '',
      end_date: activity.end_date || '',
      is_active: activity.is_active
    });
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.editingActivity.set(null);
    this.activityForm.reset();
  }

  onSubmit() {
    if (this.activityForm.valid) {
      const formData = this.activityForm.value;
      
      // Limpiar campos vacíos
      Object.keys(formData).forEach(key => {
        if (formData[key] === '' || formData[key] === null) {
          delete formData[key];
        }
      });

      if (this.editingActivity()) {
        this.updateActivity(this.editingActivity()!.id!, formData);
      } else {
        this.createActivity(formData);
      }
    }
  }

  createActivity(activityData: any) {
    this.loading.set(true);
    this.activityService.createActivity(activityData).subscribe({
      next: () => {
        this.loadActivities();
        this.closeModal();
      },
      error: (error) => {
        console.error('Error creating activity:', error);
        this.loading.set(false);
      }
    });
  }

  updateActivity(id: number, activityData: any) {
    this.loading.set(true);
    this.activityService.updateActivity(id, activityData).subscribe({
      next: () => {
        this.loadActivities();
        this.closeModal();
      },
      error: (error) => {
        console.error('Error updating activity:', error);
        this.loading.set(false);
      }
    });
  }

  deleteActivity(activity: Activity) {
    if (confirm(`¿Estás seguro de que deseas eliminar la actividad "${activity.name}"?`)) {
      this.loading.set(true);
      this.activityService.deleteActivity(activity.id!).subscribe({
        next: () => {
          this.loadActivities();
        },
        error: (error) => {
          console.error('Error deleting activity:', error);
          this.loading.set(false);
        }
      });
    }
  }

  toggleActivityStatus(activity: Activity) {
    this.loading.set(true);
    this.activityService.toggleActivityStatus(activity.id!, !activity.is_active).subscribe({
      next: () => {
        this.loadActivities();
      },
      error: (error) => {
        console.error('Error toggling activity status:', error);
        this.loading.set(false);
      }
    });
  }

  getStatusBadgeClass(isActive: boolean): string {
    return isActive ? 'badge-success' : 'badge-danger';
  }

  getStatusText(isActive: boolean): string {
    return isActive ? 'Activa' : 'Inactiva';
  }

  getTypeText(type: string): string {
    const types: { [key: string]: string } = {
      'course': 'Curso',
      'event': 'Evento',
      'other': 'Otro'
    };
    return types[type] || type;
  }

  formatDate(date: string | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('es-ES');
  }
}

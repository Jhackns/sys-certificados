import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { Role, Permission, RoleResponse, PermissionResponse } from '../../../core/models/role.model';

interface ApiResponse {
  success: boolean;
  message: string;
  data: any;
}

@Component({
  selector: 'app-roles',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './roles.component.html',
  styleUrls: ['./roles.component.css']
})
export class RolesComponent implements OnInit {
  roles = signal<Role[]>([]);
  permissions = signal<Permission[]>([]);
  permissionsGrouped = signal<Record<string, Permission[]>>({});
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');
  searchQuery = signal('');
  selectAllPermissions = signal(false);

  // Modal states
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  selectedRole = signal<Role | null>(null);

  // Forms
  roleForm: FormGroup;
  updateRoleForm: FormGroup;

  constructor(private http: HttpClient, private fb: FormBuilder) {
    this.roleForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2)]],
      description: ['', [Validators.required, Validators.minLength(5)]],
      permissions: [[]]
    });

    this.updateRoleForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2)]],
      description: ['', [Validators.required, Validators.minLength(5)]],
      permissions: [[]]
    });
  }

  ngOnInit(): void {
    this.loadRoles();
    this.loadPermissions();
  }

  loadRoles(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    // First try without with_users_count
    const params: any = {
      per_page: '100',
      _t: Date.now() // Prevent caching
    };

    // Only add with_users_count if the user has the permission
    if (this.hasPermission('users.read')) {
      params.with_users_count = 'true';
    }

    this.http.get<ApiResponse>(`${environment.apiUrl}/roles`, { params }).subscribe({
      next: (response: any) => {
        this.isLoading.set(false);
        try {
          if (response.success) {
            // Handle both direct array and paginated response
            const roles = response.data?.roles || response.data || [];
            this.roles.set(roles);
          } else {
            this.errorMessage.set(response.message || 'Error al cargar roles');
            console.error('API Error:', response);
          }
        } catch (e) {
          console.error('Error processing roles response:', e, response);
          this.errorMessage.set('Error al procesar la respuesta del servidor');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        const errorMessage = error?.error?.message || error?.message || 'Error desconocido';
        const errorDetails = error?.error?.errors || error?.error || {};

        console.error('Error loading roles:', {
          status: error?.status,
          statusText: error?.statusText,
          url: error?.url,
          message: errorMessage,
          details: errorDetails,
          fullError: error
        });

        if (error?.status === 500) {
          this.errorMessage.set('Error del servidor. Por favor, intente nuevamente más tarde.');
        } else {
          this.errorMessage.set(`Error al cargar los roles: ${errorMessage}`);
        }
      }
    });
  }

  loadPermissions(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.http.get<ApiResponse>(`${environment.apiUrl}/permissions`, {
      params: {
        grouped: 'true',
        per_page: '1000',
        _t: Date.now() // Prevent caching
      }
    }).subscribe({
      next: (response: any) => {
        this.isLoading.set(false);
        try {
          if (response.success) {
            // The backend returns permissions grouped by module
            const groupedPermissions = response.data?.permissions_grouped || {};

            // Verificar que groupedPermissions sea un objeto válido
            if (typeof groupedPermissions === 'object' && groupedPermissions !== null) {
              // Flatten the grouped permissions into a single array
              const allPermissions: Permission[] = [];
              Object.values(groupedPermissions).forEach((modulePermissions: any) => {
                if (Array.isArray(modulePermissions)) {
                  allPermissions.push(...modulePermissions);
                }
              });

              this.permissions.set(allPermissions);
              this.permissionsGrouped.set(groupedPermissions);
              
              console.log('Permissions loaded successfully:', {
                grouped: groupedPermissions,
                total: allPermissions.length
              });
            } else {
              console.error('Invalid permissions_grouped structure:', response.data);
              this.errorMessage.set('Estructura de permisos inválida del servidor');
            }
          } else {
            this.errorMessage.set(response.message || 'Error al cargar los permisos');
            console.error('API Error:', response);
          }
        } catch (e) {
          console.error('Error processing permissions response:', e, response);
          this.errorMessage.set('Error al procesar los permisos del servidor');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        const errorMessage = error?.error?.message || error?.message || 'Error desconocido';
        const errorDetails = error?.error?.errors || error?.error || {};

        console.error('Error loading permissions:', {
          status: error?.status,
          statusText: error?.statusText,
          url: error?.url,
          message: errorMessage,
          details: errorDetails,
          fullError: error
        });

        if (error?.status === 500) {
          this.errorMessage.set('Error del servidor al cargar permisos. Por favor, intente nuevamente más tarde.');
        } else {
          this.errorMessage.set(`Error al cargar los permisos: ${errorMessage}`);
        }
      }
    });
  }

  // Modal methods
  openCreateModal(): void {
    this.roleForm.reset();
    this.roleForm.patchValue({
      name: '',
      description: '',
      permissions: []
    });
    this.searchQuery.set('');
    this.selectAllPermissions.set(false);
    this.showCreateModal.set(true);
  }

  openEditModal(role: Role): void {
    this.selectedRole.set(role);
    this.updateRoleForm.patchValue({
      name: role.name,
      description: role.description || '',
      permissions: role.permissions?.map(p => typeof p === 'string' ? p : p.name) || []
    });
    this.searchQuery.set('');
    this.selectAllPermissions.set(false);
    this.showEditModal.set(true);
  }

  openDeleteModal(role: Role): void {
    this.selectedRole.set(role);
    this.showDeleteModal.set(true);
  }

  closeModals(): void {
    this.showCreateModal.set(false);
    this.showEditModal.set(false);
    this.showDeleteModal.set(false);
    this.selectedRole.set(null);
    this.searchQuery.set('');
    this.selectAllPermissions.set(false);
    this.roleForm.reset({
      name: '',
      description: '',
      permissions: []
    });
    this.updateRoleForm.reset({
      name: '',
      description: '',
      permissions: []
    });
  }

  // CRUD operations
  createRole(): void {
    if (this.roleForm.valid) {
      this.isLoading.set(true);
      const formData = this.roleForm.value;

      this.http.post<ApiResponse>(`${environment.apiUrl}/roles`, formData).subscribe({
        next: (response) => {
          this.isLoading.set(false);
          if (response.success) {
            this.successMessage.set('Rol creado correctamente');
            this.loadRoles();
            this.closeModals();
            setTimeout(() => this.successMessage.set(''), 3000);
          } else {
            this.errorMessage.set(response.message || 'Error al crear rol');
          }
        },
        error: (error) => {
          this.isLoading.set(false);
          this.errorMessage.set('Error al crear rol');
          console.error('Error creating role:', error);
        }
      });
    }
  }

  updateRole(): void {
    if (this.updateRoleForm.valid && this.selectedRole()) {
      this.isLoading.set(true);
      const formData = this.updateRoleForm.value;
      const roleId = this.selectedRole()!.id;

      this.http.put<ApiResponse>(`${environment.apiUrl}/roles/${roleId}`, formData).subscribe({
        next: (response) => {
          this.isLoading.set(false);
          if (response.success) {
            this.successMessage.set('Rol actualizado correctamente');
            this.loadRoles();
            this.closeModals();
            setTimeout(() => this.successMessage.set(''), 3000);
          } else {
            this.errorMessage.set(response.message || 'Error al actualizar rol');
          }
        },
        error: (error) => {
          this.isLoading.set(false);
          this.errorMessage.set('Error al actualizar rol');
          console.error('Error updating role:', error);
        }
      });
    }
  }

  deleteRole(): void {
    if (this.selectedRole()) {
      this.isLoading.set(true);
      const roleId = this.selectedRole()!.id;

      this.http.delete<ApiResponse>(`${environment.apiUrl}/roles/${roleId}`).subscribe({
        next: (response) => {
          this.isLoading.set(false);
          if (response.success) {
            this.successMessage.set('Rol eliminado correctamente');
            this.loadRoles();
            this.closeModals();
            setTimeout(() => this.successMessage.set(''), 3000);
          } else {
            this.errorMessage.set(response.message || 'Error al eliminar rol');
          }
        },
        error: (error) => {
          this.isLoading.set(false);
          this.errorMessage.set('Error al eliminar rol');
          console.error('Error deleting role:', error);
        }
      });
    }
  }

  getFieldError(fieldName: string, formType: 'create' | 'edit' = 'create'): string {
    const form = formType === 'create' ? this.roleForm : this.updateRoleForm;
    const field = form.get(fieldName);
    if (field?.errors && field.touched) {
      if (field.errors['required']) {
        return `${fieldName} es requerido`;
      }
      if (field.errors['minlength']) {
        return `${fieldName} debe tener al menos ${field.errors['minlength'].requiredLength} caracteres`;
      }
    }
    return '';
  }

  onPermissionChange(event: any, permissionName: string, formType: 'create' | 'edit' = 'create'): void {
    const form = formType === 'create' ? this.roleForm : this.updateRoleForm;
    const permissions = form.get('permissions')?.value || [];
    
    if (event.target.checked) {
      if (!permissions.includes(permissionName)) {
        permissions.push(permissionName);
      }
    } else {
      const index = permissions.indexOf(permissionName);
      if (index > -1) {
        permissions.splice(index, 1);
      }
    }
    form.patchValue({ permissions });
  }

  formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  getRoleDisplayName(role: Role): string {
    return role.display_name || role.name.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  getRolePermissions(role: Role): string {
    if (!role.permissions) return 'Sin permisos';
    return role.permissions
      .map(p => typeof p === 'string' ? p : p.name)
      .join(', ');
  }

  getPermissionsGrouped(): Array<{key: string, value: any[]}> {
    return Object.entries(this.permissionsGrouped()).map(([key, value]) => ({
      key,
      value
    }));
  }

  /**
   * Check if the current user has a specific permission
   * @param permission The permission to check
   */
  private hasPermission(permission: string): boolean {
    // This is a simplified check. In a real app, you should use a proper auth service
    const user = JSON.parse(localStorage.getItem('currentUser') || '{}');
    return user?.permissions?.includes(permission) || user?.is_admin === true;
  }

  // Filtrar permisos basado en la búsqueda
  getFilteredPermissions(): Array<{key: string, value: Permission[]}> {
    const query = this.searchQuery().toLowerCase();
    const grouped = this.permissionsGrouped();
    
    // Verificar que grouped sea un objeto válido y no esté vacío
    if (!grouped || typeof grouped !== 'object' || Object.keys(grouped).length === 0) {
      return [];
    }
    
    if (!query) {
      return Object.entries(grouped).map(([key, value]) => ({ key, value }));
    }

    const filteredGroups: Array<{key: string, value: Permission[]}> = [];
    
    Object.entries(grouped).forEach(([group, permissions]) => {
      // Verificar que permissions sea un array
      if (!Array.isArray(permissions)) {
        return;
      }
      
      const filtered = permissions.filter(permission => 
        permission.name.toLowerCase().includes(query) ||
        group.toLowerCase().includes(query)
      );
      
      if (filtered.length > 0) {
        filteredGroups.push({ key: group, value: filtered });
      }
    });
    
    return filteredGroups;
  }

  // Seleccionar/deseleccionar todos los permisos
  onSelectAllPermissions(event: any, formType: 'create' | 'edit' = 'create'): void {
    const isChecked = event.target.checked;
    this.selectAllPermissions.set(isChecked);
    
    const form = formType === 'create' ? this.roleForm : this.updateRoleForm;
    const allPermissions = this.permissions();
    
    if (isChecked) {
      // Seleccionar todos los permisos
      const allPermissionNames = allPermissions.map(p => p.name);
      form.patchValue({ permissions: allPermissionNames });
    } else {
      // Deseleccionar todos los permisos
      form.patchValue({ permissions: [] });
    }
  }

  // Verificar si todos los permisos están seleccionados
  areAllPermissionsSelected(formType: 'create' | 'edit' = 'create'): boolean {
    const form = formType === 'create' ? this.roleForm : this.updateRoleForm;
    const selectedPermissions = form.get('permissions')?.value || [];
    const allPermissions = this.permissions();
    
    return allPermissions.length > 0 && selectedPermissions.length === allPermissions.length;
  }

  // Verificar si algunos permisos están seleccionados (para estado indeterminado)
  areSomePermissionsSelected(formType: 'create' | 'edit' = 'create'): boolean {
    const form = formType === 'create' ? this.roleForm : this.updateRoleForm;
    const selectedPermissions = form.get('permissions')?.value || [];
    const allPermissions = this.permissions();
    
    return selectedPermissions.length > 0 && selectedPermissions.length < allPermissions.length;
  }
}

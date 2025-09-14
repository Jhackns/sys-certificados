import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
  updated_at: string;
  roles?: Role[];
}

interface Role {
  id: number;
  name: string;
  display_name?: string;
}

interface ApiResponse {
  success: boolean;
  message: string;
  data: {
    users: User[];
    roles?: Role[];
  };
}

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './users.component.html',
  styleUrl: './users.component.css'
})
export class UsersComponent implements OnInit {
  users = signal<User[]>([]);
  roles = signal<Role[]>([]);
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');

  // Modal states
  showCreateModal = signal(false);
  showEditModal = signal(false);
  showDeleteModal = signal(false);
  selectedUser = signal<User | null>(null);

  // Format user roles for display
  getUserRoles(user: User): string {
    if (!user.roles || user.roles.length === 0) {
      return 'Sin roles asignados';
    }
    return user.roles.map(role => role.display_name || role.name).join(', ');
  }

  // Forms
  userForm: FormGroup;

  isEditing = signal(false);

  // Selected users for bulk actions
  selectedUserIds: number[] = [];

  constructor(
    private http: HttpClient,
    private fb: FormBuilder
  ) {
    this.userForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2)]],
      email: ['', [Validators.required, Validators.email]],
      password: [''],
      password_confirmation: [''],
      roles: [[], [Validators.required]]
    }, {
      validators: [
        this.passwordMatchValidator,
        this.passwordRequiredOnCreate
      ]
    });
  }

  passwordRequiredOnCreate = (form: FormGroup) => {
    if (!this.isEditing() && !form.get('password')?.value) {
      form.get('password')?.setErrors({ required: true });
      form.get('password_confirmation')?.setErrors({ required: true });
    } else if (form.get('password')?.value) {
      // Only validate password strength if password is provided
      const password = form.get('password')?.value;
      if (password.length < 8) {
        form.get('password')?.setErrors({ minlength: true });
      }
    }
    return null;
  }

  // Handle role selection changes
  onRoleChange(event: Event, roleName: string): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    const currentRoles = this.userForm.get('roles')?.value || [];

    if (isChecked) {
      // Add role if not already in the array
      if (!currentRoles.includes(roleName)) {
        this.userForm.get('roles')?.setValue([...currentRoles, roleName]);
      }
    } else {
      // Remove role if it exists in the array
      this.userForm.get('roles')?.setValue(currentRoles.filter((r: string) => r !== roleName));
    }

    // Mark the form as dirty since we're updating it programmatically
    this.userForm.markAsDirty();
  }

  ngOnInit(): void {
    this.loadUsers();
    this.loadRoles();
  }

  passwordMatchValidator(form: FormGroup) {
    const password = form.get('password');
    const confirmPassword = form.get('password_confirmation');

    if (password && confirmPassword && password.value !== confirmPassword.value) {
      confirmPassword.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    }

    if (confirmPassword && confirmPassword.hasError('passwordMismatch')) {
      confirmPassword.setErrors(null);
    }

    return null;
  }

  loadUsers(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.http.get<ApiResponse>(`${environment.apiUrl}/users?per_page=100`).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.users.set(response.data.users);
        } else {
          this.errorMessage.set(response.message || 'Error al cargar usuarios');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error de conexión. Intenta nuevamente.');
        console.error('Error loading users:', error);
      }
    });
  }

  loadRoles(): void {
    this.http.get<ApiResponse>(`${environment.apiUrl}/roles`).subscribe({
      next: (response) => {
        if (response.success) {
          this.roles.set(response.data.roles || []);
        }
      },
      error: (error) => {
        console.error('Error loading roles:', error);
      }
    });
  }

  // Modal methods
  openCreateModal(): void {
    this.userForm.reset();
    this.userForm.patchValue({
      password: '',
      password_confirmation: '',
      roles: []
    });
    this.showCreateModal.set(true);
  }

  openEditModal(user: User): void {
    this.isEditing.set(true);
    this.selectedUser.set(user);
    this.userForm.patchValue({
      name: user.name,
      email: user.email,
      password: '',
      password_confirmation: '',
      roles: user.roles?.map(role => role.name) || []
    });
    // Clear password validators for edit mode
    this.userForm.get('password')?.clearValidators();
    this.userForm.get('password_confirmation')?.clearValidators();
    this.userForm.updateValueAndValidity();
    this.showEditModal.set(true);
  }

  openDeleteModal(user: User): void {
    this.selectedUser.set(user);
    this.showDeleteModal.set(true);
  }

  closeModals(): void {
    this.showCreateModal.set(false);
    this.showEditModal.set(false);
    this.showDeleteModal.set(false);
    this.selectedUser.set(null);
    this.isEditing.set(false);
    this.userForm.reset();
    // Reset validators
    this.userForm.get('password')?.setValidators([Validators.minLength(8)]);
    this.userForm.get('password_confirmation')?.setValidators([]);
    this.userForm.updateValueAndValidity();
  }

  // CRUD operations
  private prepareUserData(formData: any): any {
    const data = { ...formData };
    // Remove password fields if they are empty (edit mode)
    if (this.isEditing() && !data.password) {
      delete data.password;
      delete data.password_confirmation;
    }
    // Ensure roles is an array of role names
    if (Array.isArray(data.roles)) {
      data.roles = data.roles.map((role: any) => typeof role === 'object' ? role.name : role);
    }
    return data;
  }

  createUser(): void {
    if (this.userForm.valid) {
      this.isLoading.set(true);
      const formData = this.prepareUserData(this.userForm.value);

      this.http.post<ApiResponse>(`${environment.apiUrl}/users`, formData).subscribe({
        next: (response) => this.handleUserOperationResponse(response, 'crear'),
        error: (error) => this.handleUserOperationError(error, 'crear')
      });
    }
  }

  updateUser(): void {
    if (this.userForm.valid && this.selectedUser()) {
      this.isLoading.set(true);
      const formData = this.prepareUserData(this.userForm.value);
      const userId = this.selectedUser()!.id;

      this.http.put<ApiResponse>(`${environment.apiUrl}/users/${userId}`, formData).subscribe({
        next: (response) => this.handleUserOperationResponse(response, 'actualizar'),
        error: (error) => this.handleUserOperationError(error, 'actualizar')
      });
    }
  }

  deleteUser(): void {
    if (this.selectedUser()) {
      this.isLoading.set(true);
      const userId = this.selectedUser()!.id;

      this.http.delete<ApiResponse>(`${environment.apiUrl}/users/${userId}`).subscribe({
        next: (response) => this.handleUserOperationResponse(response, 'eliminar'),
        error: (error) => this.handleUserOperationError(error, 'eliminar')
      });
    }
  }

  private handleUserOperationResponse(response: ApiResponse, operation: 'crear' | 'actualizar' | 'eliminar'): void {
    this.isLoading.set(false);
    if (response.success) {
      const messages = {
        'crear': 'Usuario creado correctamente',
        'actualizar': 'Usuario actualizado correctamente',
        'eliminar': 'Usuario eliminado correctamente'
      };

      this.successMessage.set(messages[operation]);
      this.loadUsers();
      this.closeModals();
      setTimeout(() => this.successMessage.set(''), 3000);
    } else {
      this.errorMessage.set(response.message || `Error al ${operation} usuario`);
    }
  }

  private handleUserOperationError(error: any, operation: 'crear' | 'actualizar' | 'eliminar'): void {
    this.isLoading.set(false);
    this.errorMessage.set(`Error al ${operation} usuario`);
    console.error(`Error ${operation} user:`, error);
  }

  // Bulk actions
  isSelected(user: User): boolean {
    return this.selectedUserIds.includes(user.id);
  }

  toggleUserSelection(user: User): void {
    if (this.isSelected(user)) {
      this.selectedUserIds = this.selectedUserIds.filter(id => id !== user.id);
    } else {
      this.selectedUserIds.push(user.id);
    }
  }

  allSelected(): boolean {
    return this.users().length > 0 && this.selectedUserIds.length === this.users().length;
  }

  toggleSelectAll(event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    this.selectedUserIds = checked ? this.users().map(u => u.id) : [];
  }

  selectedUsers(): User[] {
    return this.users().filter(u => this.selectedUserIds.includes(u.id));
  }

  async deleteSelectedUsers(): Promise<void> {
    if (this.selectedUserIds.length === 0) return;

    const confirmed = confirm(`¿Está seguro de eliminar los ${this.selectedUserIds.length} usuarios seleccionados?`);
    if (!confirmed) return;

    this.isLoading.set(true);
    this.errorMessage.set('');

    try {
      // Delete users one by one
      for (const userId of this.selectedUserIds) {
        await this.http.delete<ApiResponse>(`${environment.apiUrl}/users/${userId}`).toPromise();
      }

      this.successMessage.set('Usuarios eliminados correctamente');
      this.loadUsers();
      this.selectedUserIds = [];
      setTimeout(() => this.successMessage.set(''), 3000);
    } catch (error) {
      console.error('Error deleting users:', error);
      this.errorMessage.set('Ocurrió un error al eliminar algunos usuarios');
    } finally {
      this.isLoading.set(false);
    }
  }

  getFieldError(fieldName: string): string {
    const field = this.userForm.get(fieldName);
    if (!field?.errors || !field.touched) return '';

    const errors = {
      required: `${this.getFieldDisplayName(fieldName)} es requerido`,
      email: 'Email inválido',
      minlength: fieldName === 'password'
        ? 'La contraseña debe tener al menos 8 caracteres'
        : `${this.getFieldDisplayName(fieldName)} debe tener al menos ${field.errors['minlength']?.requiredLength} caracteres`,
      passwordMismatch: 'Las contraseñas no coinciden'
    };

    for (const [error, message] of Object.entries(errors)) {
      if (field.errors[error]) return message;
    }

    return '';
  }

  private getFieldDisplayName(fieldName: string): string {
    const names: {[key: string]: string} = {
      'name': 'El nombre',
      'email': 'El correo electrónico',
      'password': 'La contraseña',
      'password_confirmation': 'La confirmación de contraseña',
      'roles': 'Los roles'
    };
    return names[fieldName] || 'Este campo';
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
}


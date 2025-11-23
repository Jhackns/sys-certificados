import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string | null;
  fecha_nacimiento?: string | null;
  pais?: string | null;
  genero?: string | null;
  telefono?: string | null;
  activo?: boolean;
  last_login?: string | null;
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
  availableRoles = signal<Role[]>([]);
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
    // Si user.roles es un array de strings (nombres de roles), únelos por coma
    // Si user.roles es un array de objetos Role, extrae los nombres
    if (Array.isArray(user.roles) && user.roles.length > 0) {
      if (typeof user.roles[0] === 'string') {
        return user.roles.join(', ');
      } else if (typeof user.roles[0] === 'object' && user.roles[0].name) {
        return user.roles.map(role => role.name).join(', ');
      }
    }
    return 'Sin rol';
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
      fecha_nacimiento: [''],
      pais: [''],
      genero: [''],
      telefono: [''],
      activo: [true],
      password: [''],
      password_confirmation: [''],
      role: ['', [Validators.required]]
    }, {
      validators: [
        this.passwordMatchValidator,
        this.passwordRequiredOnCreate
      ]
    });
  }

  passwordRequiredOnCreate = (form: FormGroup) => {
    const password = form.get('password');
    const passwordConfirmation = form.get('password_confirmation');

    if (!this.isEditing()) {
      // In create mode, password is required
      if (!password?.value) {
        return { passwordRequired: true };
      }
      if (password.value.length < 8) {
        return { passwordMinLength: true };
      }
    } else {
      // In edit mode, password is optional but must be valid if provided
      if (password?.value && password.value.length < 8) {
        return { passwordMinLength: true };
      }
    }

    return null;
  }

  // Variables para manejar roles
  selectedRole: string = '';

  // Método para obtener el error del campo role
  getRoleError(): string | null {
    const roleControl = this.userForm.get('role');
    if (roleControl?.errors && roleControl.touched) {
      if (roleControl.errors['required']) {
        return 'Debe seleccionar un rol';
      }
    }
    return null;
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
    this.http.get<ApiResponse>(`${environment.apiUrl}/users/available-roles`).subscribe({
      next: (response) => {
        if (response.success) {
          this.roles.set(response.data.roles || []);
          this.availableRoles.set(response.data.roles || []);
        }
      },
      error: (error) => {
        console.error('Error loading roles:', error);
      }
    });
  }

  // Modal methods
  openCreateModal(): void {
    this.isEditing.set(false);
    // Reiniciar rol seleccionado
    this.selectedRole = '';

    this.userForm.reset({
      activo: true,
      role: ''
    });
    // Reset validators for create mode
    this.userForm.get('password')?.setValidators([Validators.required, Validators.minLength(8)]);
    this.userForm.get('password_confirmation')?.setValidators([Validators.required]);
    this.userForm.get('role')?.setValidators([Validators.required]);
    this.userForm.updateValueAndValidity();
    this.showCreateModal.set(true);
  }

  openEditModal(user: User): void {
    this.selectedUser.set(user);
    this.isEditing.set(true);

    // Inicializar el rol seleccionado (tomar el primer rol si existe)
    let userRole = '';
    if (user.roles && user.roles.length > 0) {
      if (typeof user.roles[0] === 'string') {
        userRole = user.roles[0];
      } else if (typeof user.roles[0] === 'object' && user.roles[0].name) {
        userRole = user.roles[0].name;
      }
    }
    this.selectedRole = userRole;

    // Formatear fecha para input type="date" (YYYY-MM-DD)
    let formattedDate = '';
    if (user.fecha_nacimiento) {
      const date = new Date(user.fecha_nacimiento);
      if (!isNaN(date.getTime())) {
        formattedDate = date.toISOString().split('T')[0];
      }
    }

    this.userForm.patchValue({
      name: user.name,
      email: user.email,
      fecha_nacimiento: formattedDate,
      pais: user.pais || '',
      genero: user.genero || '',
      telefono: user.telefono || '',
      activo: user.activo === undefined ? true : user.activo,
      password: '',
      password_confirmation: '',
      role: this.selectedRole
    });
    // Clear password validators for edit mode
    this.userForm.get('password')?.clearValidators();
    this.userForm.get('password_confirmation')?.clearValidators();
    // Keep role validator for edit mode
    this.userForm.get('role')?.setValidators([Validators.required]);
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
    this.selectedRole = '';
    this.userForm.reset();
    // Reset validators
    this.userForm.get('password')?.setValidators([Validators.minLength(8)]);
    this.userForm.get('password_confirmation')?.setValidators([]);
    this.userForm.get('role')?.setValidators([]);
    this.userForm.updateValueAndValidity();
  }

  // CRUD operations
  private prepareUserData(formData: any): any {
    const userData: any = {
      name: formData.name,
      email: formData.email,
      fecha_nacimiento: formData.fecha_nacimiento || '',
      pais: formData.pais || '',
      genero: formData.genero || '',
      telefono: formData.telefono || '',
      activo: formData.activo === undefined ? true : formData.activo,
      roles: [formData.role] // Convertir el rol único a array para el backend
    };

    // Only include password if it's provided
    if (formData.password) {
      userData.password = formData.password;
      userData.password_confirmation = formData.password_confirmation;
    }

    return userData;
  }

  createUser(): void {
    if (this.userForm.valid) {
      this.isLoading.set(true);
      const formData = this.prepareUserData(this.userForm.value);

      this.http.post<ApiResponse>(`${environment.apiUrl}/users`, formData).subscribe({
        next: (response) => {
          this.handleUserOperationResponse(response, 'crear');
        },
        error: (error) => {
          console.error('Error al crear usuario:', error);
          this.handleUserOperationError(error, 'crear');
        }
      });
    } else {
      console.warn('Formulario inválido:', this.userForm.errors);
      // Marcar todos los campos como touched para mostrar errores
      Object.keys(this.userForm.controls).forEach(key => {
        this.userForm.get(key)?.markAsTouched();
      });
      this.errorMessage.set('Por favor complete todos los campos obligatorios');
      setTimeout(() => this.errorMessage.set(''), 3000);
    }
  }

  updateUser(): void {
    if (this.userForm.valid && this.selectedUser()) {
      this.isLoading.set(true);
      const formData = this.prepareUserData(this.userForm.value);
      const userId = this.selectedUser()!.id;

      this.http.put<ApiResponse>(`${environment.apiUrl}/users/${userId}`, formData).subscribe({
        next: (response) => {
          this.handleUserOperationResponse(response, 'actualizar');
        },
        error: (error) => {
          console.error('Error al actualizar usuario:', error);
          this.handleUserOperationError(error, 'actualizar');
        }
      });
    } else {
      console.warn('Formulario inválido o usuario no seleccionado');
      // Marcar todos los campos como touched para mostrar errores
      Object.keys(this.userForm.controls).forEach(key => {
        this.userForm.get(key)?.markAsTouched();
      });
      this.errorMessage.set('Por favor complete todos los campos obligatorios');
      setTimeout(() => this.errorMessage.set(''), 3000);
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
    const names: { [key: string]: string } = {
      'name': 'El nombre',
      'email': 'El correo electrónico',
      'password': 'La contraseña',
      'password_confirmation': 'La confirmación de contraseña',
      'role': 'El rol'
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


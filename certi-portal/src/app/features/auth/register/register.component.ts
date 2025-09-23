import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { RegisterRequest } from '../../../core/models/user.model';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './register.component.html',
  styleUrl: './register.component.css'
})
export class RegisterComponent {
  registerForm: FormGroup;
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router
  ) {
    this.registerForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2)]],
      email: ['', [Validators.required, Validators.email]],
      birth_date: ['', [Validators.required]],
      country: ['', [Validators.required]],
      gender: ['', [Validators.required]],
      phone: ['', [Validators.required, Validators.pattern(/^[0-9+\-\s()]+$/)]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', [Validators.required]],
      role: ['usuario_final', [Validators.required]]
    }, { validators: this.passwordMatchValidator });
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

  onSubmit(): void {
    if (this.registerForm.valid) {
      this.isLoading.set(true);
      this.errorMessage.set('');
      this.successMessage.set('');

      const registerData: RegisterRequest = {
        name: this.registerForm.value.name,
        email: this.registerForm.value.email,
        birth_date: this.registerForm.value.birth_date,
        country: this.registerForm.value.country,
        gender: this.registerForm.value.gender,
        phone: this.registerForm.value.phone,
        password: this.registerForm.value.password,
        password_confirmation: this.registerForm.value.password_confirmation,
        role: this.registerForm.value.role
      };

      this.authService.register(registerData).subscribe({
        next: (response) => {
          this.isLoading.set(false);
          if (response.success) {
            this.successMessage.set('Usuario registrado correctamente. Redirigiendo...');
            setTimeout(() => {
              this.router.navigate(['/principal']);
            }, 2000);
          } else {
            this.errorMessage.set(response.message || 'Error en el registro');
          }
        },
        error: (error) => {
          this.isLoading.set(false);
          this.errorMessage.set('Error de conexión. Intenta nuevamente.');
          console.error('Register error:', error);
        }
      });
    } else {
      this.markFormGroupTouched();
    }
  }

  private markFormGroupTouched(): void {
    Object.keys(this.registerForm.controls).forEach(key => {
      const control = this.registerForm.get(key);
      control?.markAsTouched();
    });
  }

  getFieldError(fieldName: string): string {
    const field = this.registerForm.get(fieldName);
    if (field?.errors && field.touched) {
      if (field.errors['required']) {
        const fieldLabels: { [key: string]: string } = {
          'name': 'Nombre completo',
          'email': 'Correo electrónico',
          'birth_date': 'Fecha de nacimiento',
          'country': 'País',
          'gender': 'Género',
          'phone': 'Teléfono',
          'password': 'Contraseña',
          'password_confirmation': 'Confirmación de contraseña'
        };
        return `${fieldLabels[fieldName] || fieldName} es requerido`;
      }
      if (field.errors['email']) {
        return 'Email inválido';
      }
      if (field.errors['pattern']) {
        if (fieldName === 'phone') {
          return 'Formato de teléfono inválido';
        }
        return 'Formato inválido';
      }
      if (field.errors['minlength']) {
        if (fieldName === 'password') {
          return 'La contraseña debe tener al menos 8 caracteres';
        }
        return `${fieldName} debe tener al menos ${field.errors['minlength'].requiredLength} caracteres`;
      }
      if (field.errors['passwordMismatch']) {
        return 'Las contraseñas no coinciden';
      }
    }
    return '';
  }

  goToLogin(): void {
    this.router.navigate(['/login']);
  }
}

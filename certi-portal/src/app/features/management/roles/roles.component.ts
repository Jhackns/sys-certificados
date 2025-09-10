import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

interface Role {
  id: number;
  name: string;
  display_name?: string;
  description?: string;
  created_at: string;
  updated_at: string;
}

interface ApiResponse {
  success: boolean;
  message: string;
  data: {
    roles: Role[];
  };
}

@Component({
  selector: 'app-roles',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './roles.component.html',
  styleUrl: './roles.component.css'
})
export class RolesComponent implements OnInit {
  roles = signal<Role[]>([]);
  isLoading = signal(false);
  errorMessage = signal('');

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadRoles();
  }

  loadRoles(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.http.get<ApiResponse>(`${environment.apiUrl}/roles`).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.roles.set(response.data.roles);
        } else {
          this.errorMessage.set(response.message || 'Error al cargar roles');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error de conexión. Intenta nuevamente.');
        console.error('Error loading roles:', error);
      }
    });
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
}

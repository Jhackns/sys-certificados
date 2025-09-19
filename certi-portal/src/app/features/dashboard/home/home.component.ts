import { Component, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../../core/services/auth.service';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
}

interface Certificate {
  id: number;
  unique_code: string;
  activity_name: string;
  user_name: string;
  fecha_emision: string;
  status: string;
}

interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnInit {
  // Signals para datos
  users = signal<User[]>([]);
  certificates = signal<Certificate[]>([]);
  recentCertificates = signal<Certificate[]>([]);
  isLoading = signal(false);

  // Computed signals para estadísticas
  activeCertificates = computed(() => 
    this.certificates().filter(cert => cert.status === 'issued' || cert.status === 'active')
  );

  thisMonthCertificates = computed(() => {
    const currentMonth = new Date().getMonth();
    const currentYear = new Date().getFullYear();
    return this.certificates().filter(cert => {
      const certDate = new Date(cert.fecha_emision);
      return certDate.getMonth() === currentMonth && certDate.getFullYear() === currentYear;
    });
  });

  // Computed para usuario actual
  currentUser = computed(() => this.authService.currentUser());

  constructor(
    private authService: AuthService,
    private http: HttpClient
  ) {}

  ngOnInit(): void {
    this.loadDashboardData();
  }

  private loadDashboardData(): void {
    this.isLoading.set(true);
    
    // Cargar usuarios
    this.http.get<ApiResponse<{ users: User[] }>>(`${environment.apiUrl}/users?per_page=1000`).subscribe({
      next: (response) => {
        if (response.success) {
          this.users.set(response.data.users || []);
        }
      },
      error: (error) => console.error('Error loading users:', error)
    });

    // Cargar certificados
    this.http.get<ApiResponse<{ certificates: Certificate[] }>>(`${environment.apiUrl}/certificates?per_page=1000`).subscribe({
      next: (response) => {
        if (response.success) {
          this.certificates.set(response.data.certificates || []);
          // Obtener los 5 más recientes para la sección de certificados recientes
          const recent = (response.data.certificates || [])
            .sort((a, b) => new Date(b.fecha_emision).getTime() - new Date(a.fecha_emision).getTime())
            .slice(0, 5);
          this.recentCertificates.set(recent);
        }
        this.isLoading.set(false);
      },
      error: (error) => {
        console.error('Error loading certificates:', error);
        this.isLoading.set(false);
      }
    });
  }

  formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }
}

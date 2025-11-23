import { Component, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../../core/services/auth.service';
import { DashboardService, DashboardStats } from '../../../core/services/dashboard.service';
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
  stats = signal<DashboardStats | null>(null);
  isLoading = signal(false);

  // Computed para usuario actual
  currentUser = computed(() => this.authService.currentUser());

  // Computed para verificar si hay estadísticas del sistema
  hasSystemStats = computed(() => {
    const s = this.stats();
    if (!s) return false;
    return s.total_users !== undefined ||
      s.total_certificates !== undefined ||
      s.total_activities !== undefined ||
      s.total_companies !== undefined;
  });

  constructor(
    private authService: AuthService,
    private dashboardService: DashboardService
  ) { }

  ngOnInit(): void {
    this.loadDashboardData();
  }

  private loadDashboardData(): void {
    this.isLoading.set(true);

    this.dashboardService.getDashboardData().subscribe({
      next: (response) => {
        if (response.success) {
          this.stats.set(response.stats);
        }
        this.isLoading.set(false);
      },
      error: (error) => {
        console.error('Error loading dashboard data:', error);
        this.isLoading.set(false);
      }
    });
  }

  hasPermission(permission: string): boolean {
    return this.authService.hasPermission(permission);
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

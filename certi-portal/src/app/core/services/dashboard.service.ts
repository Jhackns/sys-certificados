import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface DashboardStats {
  totalUsers: number;
  totalCertificates: number;
  activeCertificates: number;
  revokedCertificates: number;
  thisMonthCertificates: number;
}

export interface RecentCertificate {
  id: number;
  unique_code: string;
  activity_name: string;
  user_name: string;
  fecha_emision: string;
  status: string;
}

export interface DashboardData {
  stats: DashboardStats;
  recentCertificates: RecentCertificate[];
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  private apiUrl = `${environment.apiUrl}/dashboard`;

  constructor(private http: HttpClient) { }

  /**
   * Obtiene las estadísticas del dashboard
   */
  getDashboardStats(): Observable<ApiResponse<DashboardStats>> {
    return this.http.get<ApiResponse<DashboardStats>>(`${this.apiUrl}/stats`);
  }

  /**
   * Obtiene los certificados recientes
   */
  getRecentCertificates(limit: number = 5): Observable<ApiResponse<RecentCertificate[]>> {
    return this.http.get<ApiResponse<RecentCertificate[]>>(`${this.apiUrl}/recent-certificates?limit=${limit}`);
  }

  /**
   * Obtiene todos los datos del dashboard en una sola llamada
   */
  getDashboardData(): Observable<ApiResponse<DashboardData>> {
    return this.http.get<ApiResponse<DashboardData>>(`${this.apiUrl}/data`);
  }
}
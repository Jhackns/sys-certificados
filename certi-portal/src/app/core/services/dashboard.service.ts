import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface DashboardStats {
  total_users?: number;
  new_users_last_month?: number;
  total_certificates?: number;
  certificates_issued_last_month?: number;
  total_activities?: number;
  active_activities?: number;
  total_companies?: number;
  my_certificates_count: number;
  my_recent_certificates: RecentCertificate[];
}

export interface RecentCertificate {
  id: number;
  code: string;
  activity_name: string;
  issue_date: string;
  file_url: string;
}

export interface DashboardData {
  user: any;
  stats: DashboardStats;
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T; // The backend returns the data directly in the root or inside 'data'?
  // Backend: return response()->json(['success' => true, 'user' => ..., 'stats' => ...]);
  // So the response IS the object with user and stats.
  // But usually ApiResponse wrapper has 'data'.
  // Let's check backend again.
  // Backend: return response()->json(['success' => true, 'user' => ..., 'stats' => ...]);
  // It does NOT wrap in 'data'.
  // So the response structure is { success: boolean, user: ..., stats: ... }
}

// Actually, let's adjust the backend to be consistent with ApiResponse if possible, or adjust frontend.
// Backend code:
// return response()->json([
//     'success' => true,
//     'user' => ...,
//     'stats' => ...,
// ]);

// So the HttpClient.get<any> will return that object.
// I should define the interface for the Response.

export interface DashboardResponse {
  success: boolean;
  user: any;
  stats: DashboardStats;
}

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  private apiUrl = `${environment.apiUrl}/dashboard`;

  constructor(private http: HttpClient) { }

  /**
   * Obtiene todos los datos del dashboard
   */
  getDashboardData(): Observable<DashboardResponse> {
    return this.http.get<DashboardResponse>(this.apiUrl);
  }
}
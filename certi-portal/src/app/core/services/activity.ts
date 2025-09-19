import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

export interface Activity {
  id?: number;
  name: string;
  description?: string;
  type: 'course' | 'event' | 'other';
  duration_hours?: number;
  start_date?: string;
  end_date?: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface ActivityResponse {
  activities: Activity[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

@Injectable({
  providedIn: 'root'
})
export class ActivityService {
  private apiUrl = `${environment.apiUrl}/activities`;

  constructor(private http: HttpClient) { }

  getActivities(): Observable<Activity[]> {
    console.log('ActivityService: Solicitando actividades desde:', this.apiUrl);
    return this.http.get<any>(this.apiUrl).pipe(
      map(response => {
        console.log('ActivityService: Respuesta recibida:', response);
        console.log('ActivityService: response.data:', response.data);
        console.log('ActivityService: response.data.activities:', response.data?.activities);
        
        // La respuesta tiene la estructura: {success: true, message: '...', data: {activities: [...], pagination: {...}}}
        if (response.success && response.data && response.data.activities) {
          return response.data.activities;
        }
        
        return [];
      }),
      catchError(error => {
        console.error('ActivityService: Error al obtener actividades:', error);
        // Retornar array vacío en caso de error
        return of([]);
      })
    );
  }

  getActivity(id: number): Observable<Activity> {
    return this.http.get<Activity>(`${this.apiUrl}/${id}`);
  }

  createActivity(activity: Omit<Activity, 'id' | 'created_at' | 'updated_at'>): Observable<Activity> {
    return this.http.post<Activity>(this.apiUrl, activity);
  }

  updateActivity(id: number, activity: Partial<Activity>): Observable<Activity> {
    return this.http.put<Activity>(`${this.apiUrl}/${id}`, activity);
  }

  deleteActivity(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  toggleActivityStatus(id: number, status: boolean): Observable<Activity> {
    return this.http.patch<Activity>(`${this.apiUrl}/${id}/toggle-status`, { is_active: status });
  }
}

import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Template {
  id: number;
  name: string;
  description?: string;
  file_path?: string;
  file_url?: string;
  canva_design_id?: string;
  activity_type: 'course' | 'event' | 'other';
  status: 'active' | 'inactive';
  qr_position?: any;
  name_position?: any;
  created_at: string;
  updated_at: string;
  certificates_count?: number;
}

export interface TemplateFilters {
  search?: string;
  activity_type?: string;
  status?: string;
  page?: number;
  per_page?: number;
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

export interface PaginatedResponse<T> {
  templates: T[];
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
export class TemplateService {
  private apiUrl = `${environment.apiUrl}/certificate-templates`;

  constructor(private http: HttpClient) { }

  getTemplates(filters: TemplateFilters = {}): Observable<ApiResponse<PaginatedResponse<Template>>> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.activity_type) params = params.set('activity_type', filters.activity_type);
    if (filters.status) params = params.set('status', filters.status);
    if (filters.page) params = params.set('page', filters.page.toString());
    if (filters.per_page) params = params.set('per_page', filters.per_page.toString());

    return this.http.get<ApiResponse<PaginatedResponse<Template>>>(this.apiUrl, { params });
  }

  getTemplate(id: number): Observable<ApiResponse<{ template: Template }>> {
    return this.http.get<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}`);
  }

  createTemplate(data: FormData | Partial<Template>): Observable<ApiResponse<{ template: Template }>> {
    return this.http.post<ApiResponse<{ template: Template }>>(this.apiUrl, data);
  }

  updateTemplate(id: number, data: FormData | Partial<Template>): Observable<ApiResponse<{ template: Template }>> {
    // Si es FormData, usar POST con _method override para manejar archivos correctamente
    if (data instanceof FormData) {
      return this.http.post<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}`, data);
    }
    return this.http.put<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}`, data);
  }

  deleteTemplate(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${this.apiUrl}/${id}`);
  }

  toggleTemplateStatus(id: number, status: 'active' | 'inactive'): Observable<ApiResponse<{ template: Template }>> {
    return this.http.patch<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}/toggle-status`, { status });
  }

  cloneTemplate(id: number, newData: Partial<Template>): Observable<ApiResponse<{ template: Template }>> {
    return this.http.post<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}/clone`, newData);
  }

  uploadTemplateFile(id: number, file: File): Observable<ApiResponse<{ template: Template }>> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}/upload`, formData);
  }

  getTemplatesList(): Observable<ApiResponse<{ templates: { id: number; name: string; description?: string }[] }>> {
    return this.http.get<ApiResponse<{ templates: { id: number; name: string; description?: string }[] }>>(`${this.apiUrl}/list`);
  }

  updateTemplatePositions(id: number, positions: {
    qr_position?: any;
    name_position?: any;
    background_image_size?: any;
  }): Observable<ApiResponse<{ template: Template }>> {
    return this.http.put<ApiResponse<{ template: Template }>>(`${this.apiUrl}/${id}/positions`, positions);
  }
  
  validateCanvaDesign(designId: string): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(`${environment.apiUrl}/canva/validate-design`, { canva_design_id: designId });
  }
}

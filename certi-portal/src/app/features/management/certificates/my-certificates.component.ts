import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { AuthService } from '../../../core/services/auth.service';

interface CertificateItem {
  id: number;
  unique_code: string;
  activity?: { id: number; name: string } | null;
  template?: { id: number; name: string } | null;
  status: string;
  fecha_emision?: string | null;
  created_at: string;
}

interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

@Component({
  selector: 'app-my-certificates',
  standalone: true,
  imports: [CommonModule],
  template: `
  <div class="page">
    <div class="page-header">
      <h1>Mis Certificados</h1>
      <p>Certificados asociados a tu cuenta</p>
    </div>

    <div *ngIf="isLoading()" class="loading-state">Cargando...</div>
    <div *ngIf="errorMessage()" class="error-state">{{ errorMessage() }}</div>

    <div *ngIf="!isLoading() && !errorMessage()" class="content-card">
      <table class="table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Actividad</th>
            <th>Plantilla</th>
            <th>Estado</th>
            <th>Fecha Emisión</th>
          </tr>
        </thead>
        <tbody>
          <tr *ngFor="let c of certificates()">
            <td>{{ c.unique_code }}</td>
            <td>{{ c.activity?.name || '-' }}</td>
            <td>{{ c.template?.name || '-' }}</td>
            <td>{{ c.status }}</td>
            <td>{{ c.fecha_emision ? (c.fecha_emision | date:'short') : '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  `,
  styles: [`
    .page-header { display:flex; flex-direction:column; gap:4px; margin-bottom:16px; }
    .content-card { background:#fff; border-radius:8px; padding:16px; }
    .table { width:100%; border-collapse:collapse; }
    .table th, .table td { padding:8px 10px; border-bottom:1px solid #eee; }
  `]
})
export class MyCertificatesComponent implements OnInit {
  certificates = signal<CertificateItem[]>([]);
  isLoading = signal(false);
  errorMessage = signal('');

  constructor(private http: HttpClient, private auth: AuthService) {}

  ngOnInit(): void {
    this.fetchMyCertificates();
  }

  private fetchMyCertificates(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');
    this.http.get<ApiResponse<{ certificates: CertificateItem[] }>>(
      `${environment.apiUrl}/certificates?scope=mine&per_page=50`
    ).subscribe({
      next: (res) => {
        this.isLoading.set(false);
        if (res.success) {
          this.certificates.set(res.data.certificates || []);
        } else {
          this.errorMessage.set(res.message || 'No se pudieron cargar certificados');
        }
      },
      error: () => {
        this.isLoading.set(false);
        this.errorMessage.set('Error al cargar certificados');
      }
    });
  }
}



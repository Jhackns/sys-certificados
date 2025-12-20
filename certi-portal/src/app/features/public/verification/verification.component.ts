import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

@Component({
    selector: 'app-verification',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './verification.component.html',
    styleUrls: ['./verification.component.css']
})
export class VerificationComponent implements OnInit {
    code: string = '';
    loading = signal<boolean>(true);
    error = signal<string | null>(null);
    certificate = signal<any>(null);

    constructor(
        private route: ActivatedRoute,
        private http: HttpClient
    ) { }

    ngOnInit(): void {
        this.route.params.subscribe(params => {
            this.code = params['code'];
            if (this.code) {
                this.verifyCertificate();
            } else {
                this.error.set('Código no proporcionado');
                this.loading.set(false);
            }
        });
    }

    verifyCertificate(): void {
        this.loading.set(true);
        const apiUrl = environment.apiUrl || 'http://localhost:8000/api';

        this.http.get<any>(`${apiUrl}/public/certificates/${this.code}`)
            .subscribe({
                next: (res) => {
                    if (res.success) {
                        this.certificate.set(res.data.certificate);
                    } else {
                        this.error.set(res.message || 'Certificado inválido');
                    }
                    this.loading.set(false);
                },
                error: (err) => {
                    console.error(err);
                    this.error.set('No se pudo verificar el certificado o no existe.');
                    this.loading.set(false);
                }
            });
    }

    downloadOriginal(): void {
        const apiUrl = environment.apiUrl || 'http://localhost:8000/api';
        // Redirigir para descarga directa
        window.location.href = `${apiUrl}/public/certificates/${this.code}/download`;
    }

    getStatusInfo(): { label: string, cssClass: string, icon: string, message: string } {
        // Soporte para ambas llaves por si acaso
        const rawStatus = this.certificate()?.status || this.certificate()?.estado;
        const status = (rawStatus || 'unknown').toLowerCase();

        console.log('VerificationComponent: Resolviendo estado:', { raw: rawStatus, normalized: status });

        switch (status) {
            case 'issued':
            case 'active':
            case 'emitido':
                return {
                    label: 'Certificado Válido',
                    cssClass: 'status-valid',
                    icon: 'fas fa-check',
                    message: 'El documento ha sido verificado y se encuentra activo en nuestros registros.'
                };
            case 'pending':
            case 'pendiente':
                return {
                    label: 'Certificado Pendiente',
                    cssClass: 'status-unknown',
                    icon: 'fas fa-hourglass-half',
                    message: 'Este certificado está pendiente de revisión o finalización.'
                };
            case 'revoked':
            case 'revocado':
                return {
                    label: 'Certificado Revocado',
                    cssClass: 'status-revoked',
                    icon: 'fas fa-exclamation-triangle',
                    message: 'Este documento ha sido revocado y ya no es válido.'
                };
            case 'cancelled':
            case 'cancelado':
                return {
                    label: 'Certificado Cancelado',
                    cssClass: 'status-cancelled',
                    icon: 'fas fa-ban',
                    message: 'Este certificado ha sido cancelado por la institución emisora.'
                };
            case 'expired':
            case 'expirado':
                return {
                    label: 'Certificado Expirado',
                    cssClass: 'status-expired',
                    icon: 'fas fa-clock',
                    message: 'La vigencia de este certificado ha finalizado.'
                };
            default:
                return {
                    label: 'Estado Desconocido',
                    cssClass: 'status-unknown',
                    icon: 'fas fa-question',
                    message: `No se puede determinar el estado de este documento. (${rawStatus})`
                };
        }
    }
}

import { Component, signal, OnInit, inject } from '@angular/core';
import { RouterOutlet, Router } from '@angular/router';
import { AuthService } from './core/services/auth.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App implements OnInit {
  protected readonly title = signal('certi-portal');

  private authService = inject(AuthService);
  private router = inject(Router);

  ngOnInit(): void {
    // Verificar si hay una sesión válida al iniciar la aplicación
    this.checkAuthenticationState();
  }

  private checkAuthenticationState(): void {
    // 1. Permitir rutas públicas explícitas (Evitar redirecciones forzadas si se accede directamente)
    // Usamos window.location.pathname porque this.router.url suele ser '/' al inicio durante el init
    const pathName = window.location.pathname;
    const publicRoutes = ['/verificar', '/login', '/register', '/404'];
    const isPublic = publicRoutes.some(route => pathName.includes(route));

    if (isPublic) {
      console.log('ℹRuta pública detectada. Saltando comprobación de redirección.');
      return;
    }

    console.log('Verificando estado de autenticación...');

    // Verificar si el usuario está autenticado y tiene un token válido
    if (this.authService.isAuthenticated() && this.authService.isTokenValid()) {
      console.log('Usuario autenticado encontrado. Manteniendo sesión...');
      // Si está en login y ya está autenticado, redirigir al dashboard
      if (this.router.url === '/login' || this.router.url === '/') {
        this.router.navigate(['/principal'], { replaceUrl: true });
      }
    } else {
      console.log('No hay sesión válida. Redirigiendo a login...');
      // Solo limpiar y redirigir si no hay sesión válida
      this.authService.logout();
      this.router.navigate(['/login'], { replaceUrl: true });
    }
  }
}

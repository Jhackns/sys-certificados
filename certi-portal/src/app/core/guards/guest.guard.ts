import { Injectable } from '@angular/core';
import { CanActivate, Router, UrlTree } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class GuestGuard implements CanActivate {

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  canActivate(): Observable<boolean | UrlTree> | Promise<boolean | UrlTree> | boolean | UrlTree {
    const isAuthenticated = this.authService.isAuthenticated();
    const isTokenValid = this.authService.isTokenValid();

    if (isAuthenticated && isTokenValid) {
      // Si ya está autenticado, redirigir al dashboard
      this.router.navigate(['/principal']);
      return false;
    } else {
      // Si no está autenticado, permitir acceso a rutas de invitado
      return true;
    }
  }
}

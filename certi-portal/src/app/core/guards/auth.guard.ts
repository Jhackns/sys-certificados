import { Injectable } from '@angular/core';
import { CanActivate, Router, UrlTree } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  canActivate(): Observable<boolean | UrlTree> | Promise<boolean | UrlTree> | boolean | UrlTree {
    const isAuthenticated = this.authService.isAuthenticated();
    const isTokenValid = this.authService.isTokenValid();

    if (isAuthenticated && isTokenValid) {
      return true;
    } else {
      // Si no está autenticado o el token es inválido, redirigir al login
      this.router.navigate(['/login']);
      return false;
    }
  }
}

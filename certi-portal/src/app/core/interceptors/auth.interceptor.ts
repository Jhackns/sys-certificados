import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Obtener el token del servicio de autenticación
  const token = authService.getAuthToken();

  // Clonar la request y agregar el header de autorización si existe el token
  let authRequest = req;
  
  // Detectar si el body es FormData para no establecer Content-Type
  const isFormData = req.body instanceof FormData;
  
  if (token) {
    const headers: any = {
      Authorization: `Bearer ${token}`,
      'Accept': 'application/json'
    };
    
    // Solo agregar Content-Type si no es FormData
    if (!isFormData) {
      headers['Content-Type'] = 'application/json';
    }
    
    authRequest = req.clone({
      setHeaders: headers
    });
  } else {
    const headers: any = {
      'Accept': 'application/json'
    };
    
    // Solo agregar Content-Type si no es FormData
    if (!isFormData) {
      headers['Content-Type'] = 'application/json';
    }
    
    authRequest = req.clone({
      setHeaders: headers
    });
  }

  return next(authRequest).pipe(
    catchError((error: HttpErrorResponse) => {
      // Manejar errores de autenticación
      if (error.status === 401) {
        // Token expirado o inválido
        authService.logoutAndRedirect();
      }

      return throwError(() => error);
    })
  );
};

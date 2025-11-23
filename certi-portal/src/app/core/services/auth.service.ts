import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap, catchError, of } from 'rxjs';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { User, LoginRequest, RegisterRequest, LoginResponse, AuthState, ApiResponse, ProfileResponse } from '../models/user.model';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly API_URL = environment.apiUrl;
  private readonly TOKEN_KEY = 'auth_token';
  private readonly USER_KEY = 'user_data';

  // Signals para el estado de autenticación
  private authState = signal<AuthState>({
    isAuthenticated: false,
    user: null,
    token: null
  });

  // Computed signals para acceso fácil
  public isAuthenticated = computed(() => this.authState().isAuthenticated);
  public currentUser = computed(() => this.authState().user);
  public authToken = computed(() => this.authState().token);

  constructor(
    private http: HttpClient,
    private router: Router
  ) {
    this.initializeAuth();
  }

  /**
   * Inicializa el estado de autenticación desde localStorage
   */
  private initializeAuth(): void {
    const token = localStorage.getItem(this.TOKEN_KEY);
    const userData = localStorage.getItem(this.USER_KEY);

    if (token && userData) {
      try {
        const user = JSON.parse(userData);
        this.authState.set({
          isAuthenticated: true,
          user,
          token
        });
      } catch (error) {
        this.clearAuth();
      }
    }
  }

  /**
   * Realiza el login del usuario
   */
  login(credentials: LoginRequest): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.API_URL}/auth/login`, credentials)
      .pipe(
        tap(response => {
          if (response.success && response.data) {
            this.setAuth(response.data.user, response.data.access_token);
          }
        }),
        catchError(error => {
          console.error('Error en login:', error);
          return of({
            success: false,
            message: 'Error en el servidor',
            data: { user: null as any, access_token: '', token_type: '', roles: [], permissions: [], company: null, email_verified: false }
          });
        })
      );
  }

  /**
   * Registra un nuevo usuario
   */
  register(userData: RegisterRequest): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.API_URL}/auth/register`, userData)
      .pipe(
        tap(response => {
          if (response.success && response.data) {
            this.setAuth(response.data.user, response.data.access_token);
          }
        }),
        catchError(error => {
          console.error('Error en registro:', error);
          return of({
            success: false,
            message: 'Error en el servidor',
            data: { user: null as any, access_token: '', token_type: '', roles: [], permissions: [], company: null, email_verified: false }
          });
        })
      );
  }

  /**
   * Cierra la sesión del usuario
   */
  logout(): void {
    this.clearAuth();
    // No redirigir automáticamente aquí para evitar conflictos con la inicialización
  }

  /**
   * Cierra la sesión del usuario y redirige al login
   */
  logoutAndRedirect(): void {
    this.clearAuth();
    this.router.navigate(['/login']);
  }

  /**
   * Obtiene el perfil del usuario autenticado
   */
  getProfile(): Observable<ProfileResponse> {
    return this.http.get<ProfileResponse>(`${this.API_URL}/auth/me`);
  }

  /**
   * Actualiza el perfil del usuario
   */
  updateProfile(userData: Partial<User>): Observable<ProfileResponse> {
    return this.http.put<ProfileResponse>(`${this.API_URL}/auth/profile`, userData)
      .pipe(
        tap(response => {
          if (response.success && response.data) {
            this.setAuth(response.data, this.authToken()!);
          }
        })
      );
  }

  /**
   * Establece el estado de autenticación
   */
  private setAuth(user: User, token: string): void {
    // Seguridad: no loggear token ni datos sensibles en consola
    localStorage.setItem(this.TOKEN_KEY, token);
    localStorage.setItem(this.USER_KEY, JSON.stringify(user));

    this.authState.set({
      isAuthenticated: true,
      user,
      token
    });
  }

  /**
   * Limpia el estado de autenticación
   */
  private clearAuth(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);

    this.authState.set({
      isAuthenticated: false,
      user: null,
      token: null
    });
  }

  /**
   * Verifica si el token es válido
   */
  isTokenValid(): boolean {
    const token = this.authToken();
    if (!token) return false;

    try {
      // Para tokens de Sanctum, simplemente verificar que existe
      // Los tokens de Sanctum no son JWT, son tokens opacos
      return token.length > 0;
    } catch {
      return false;
    }
  }

  /**
   * Obtiene el token para usar en headers
   */
  getAuthToken(): string | null {
    return this.authToken();
  }

  /**
   * Verifica si el usuario tiene un permiso específico
   */
  hasPermission(permission: string): boolean {
    const user = this.currentUser();
    if (!user || !user.permissions) return false;

    // Superadmin wildcard check (if roles are available)
    if (user.roles && user.roles.includes('super_admin')) return true;

    // Direct permission check
    if (user.permissions.includes(permission)) return true;

    // Wildcard check (e.g. 'users.*' matches 'users.read')
    const parts = permission.split('.');
    if (parts.length > 1) {
      const wildcard = parts[0] + '.*';
      if (user.permissions.includes(wildcard)) return true;
    }

    return false;
  }
}

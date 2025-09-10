import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap, catchError, of } from 'rxjs';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { User, LoginRequest, LoginResponse, AuthState, ApiResponse, ProfileResponse } from '../models/user.model';

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
            this.setAuth(response.data.user, response.data.token);
          }
        }),
        catchError(error => {
          console.error('Error en login:', error);
          return of({
            success: false,
            message: 'Error en el servidor',
            data: { user: null as any, token: '' }
          });
        })
      );
  }

  /**
   * Cierra la sesión del usuario
   */
  logout(): void {
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
      // Decodificar el JWT para verificar expiración
      const payload = JSON.parse(atob(token.split('.')[1]));
      const currentTime = Date.now() / 1000;
      return payload.exp > currentTime;
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
}

export interface User {
  id: number;
  name: string;
  email: string;
  roles?: string[];        // agregado: roles opcionales
  permissions?: string[];  // agregado: permissions opcionales
  created_at: string;
  updated_at: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation?: string;
  role?: string;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  data: {
    user: User;
    access_token: string;
    token_type: string;
    roles: string[];
    permissions: string[];
    email_verified: boolean;
  };
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

export interface ProfileResponse {
  success: boolean;
  message: string;
  data: User;
}

export interface AuthState {
  isAuthenticated: boolean;
  user: User | null;
  token: string | null;
}

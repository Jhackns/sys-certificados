export interface Permission {
  id: number;
  name: string;
  guard_name: string;
  created_at: string;
  updated_at: string;
}

export interface Role {
  id: number;
  name: string;
  guard_name: string;
  display_name?: string;
  description?: string;
  created_at: string;
  updated_at: string;
  permissions_count?: number;
  users_count?: number;
  permissions?: string[] | Permission[];
  // Add any other properties that might come from the backend
  [key: string]: any;
}

export interface RoleResponse {
  success: boolean;
  message: string;
  data: {
    roles: Role[];
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}

export interface PermissionResponse {
  success: boolean;
  message: string;
  data: Permission[];
}

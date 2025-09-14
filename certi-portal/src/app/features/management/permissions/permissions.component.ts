import { Component, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

interface Permission {
  id: number;
  name: string;
  guard_name: string;
  created_at: string;
  updated_at: string;
}

interface ApiResponse {
  success: boolean;
  message: string;
  data: {
    permissions: Permission[];
    permissions_grouped?: Record<string, Permission[]>;
  };
}

@Component({
  selector: 'app-permissions',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './permissions.component.html',
  styleUrls: ['./permissions.component.css']
})
export class PermissionsComponent implements OnInit {
  permissions = signal<Permission[]>([]);
  permissionsGrouped = signal<Record<string, Permission[]>>({});
  isLoading = signal(true);
  errorMessage = signal('');
  searchQuery = signal('');

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadPermissions();
  }

  loadPermissions(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.http.get<ApiResponse>(`${environment.apiUrl}/permissions`).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        if (response.success) {
          this.permissions.set(response.data.permissions || []);
          
          // Group permissions by their prefix (e.g., 'user.', 'role.')
          const grouped = (response.data.permissions || []).reduce((acc: Record<string, Permission[]>, permission) => {
            const [prefix] = permission.name.split('.');
            if (!acc[prefix]) {
              acc[prefix] = [];
            }
            acc[prefix].push(permission);
            return acc;
          }, {});
          
          this.permissionsGrouped.set(grouped);
        } else {
          this.errorMessage.set(response.message || 'Error al cargar los permisos');
        }
      },
      error: (error) => {
        this.isLoading.set(false);
        this.errorMessage.set('Error de conexión. Intenta nuevamente.');
        console.error('Error loading permissions:', error);
      }
    });
  }

  getFilteredPermissions(): Record<string, Permission[]> {
    const query = this.searchQuery().toLowerCase();
    if (!query) return this.permissionsGrouped();

    const filteredGroups: Record<string, Permission[]> = {};
    
    Object.entries(this.permissionsGrouped()).forEach(([group, permissions]) => {
      const filtered = permissions.filter(permission => 
        permission.name.toLowerCase().includes(query) ||
        group.toLowerCase().includes(query)
      );
      
      if (filtered.length > 0) {
        filteredGroups[group] = filtered;
      }
    });
    
    return filteredGroups;
  }

  formatPermissionName(name: string): string {
    // Convert 'user.create' to 'User Create'
    return name
      .split('.')
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  }

  hasNoFilteredPermissions(): boolean {
    const filtered = this.getFilteredPermissions();
    return Object.keys(filtered).length === 0;
  }
}

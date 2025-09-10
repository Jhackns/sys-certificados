import { Routes } from '@angular/router';
import { AuthGuard } from './core/guards/auth.guard';
import { GuestGuard } from './core/guards/guest.guard';

export const routes: Routes = [
  {
    path: '',
    redirectTo: '/principal',
    pathMatch: 'full'
  },
  {
    path: 'login',
    loadComponent: () => import('./features/auth/login/login.component').then(m => m.LoginComponent),
    canActivate: [GuestGuard]
  },
  {
    path: 'register',
    loadComponent: () => import('./features/auth/register/register.component').then(m => m.RegisterComponent),
    canActivate: [GuestGuard]
  },
  {
    path: 'principal',
    loadComponent: () => import('./features/dashboard/principal/principal.component').then(m => m.PrincipalComponent),
    canActivate: [AuthGuard],
    children: [
      {
        path: '',
        loadComponent: () => import('./features/dashboard/home/home.component').then(m => m.HomeComponent)
      },
      {
        path: 'usuarios',
        loadComponent: () => import('./features/management/users/users.component').then(m => m.UsersComponent)
      },
      {
        path: 'roles',
        loadComponent: () => import('./features/management/roles/roles.component').then(m => m.RolesComponent)
      }
    ]
  },
  {
    path: 'usuarios',
    loadComponent: () => import('./features/dashboard/principal/principal.component').then(m => m.PrincipalComponent),
    canActivate: [AuthGuard],
    children: [
      {
        path: '',
        loadComponent: () => import('./features/management/users/users.component').then(m => m.UsersComponent)
      }
    ]
  },
  {
    path: 'roles',
    loadComponent: () => import('./features/dashboard/principal/principal.component').then(m => m.PrincipalComponent),
    canActivate: [AuthGuard],
    children: [
      {
        path: '',
        loadComponent: () => import('./features/management/roles/roles.component').then(m => m.RolesComponent)
      }
    ]
  },
  {
    path: '404',
    loadComponent: () => import('./shared/components/not-found/not-found.component').then(m => m.NotFoundComponent)
  },
  {
    path: '**',
    redirectTo: '/404'
  }
];

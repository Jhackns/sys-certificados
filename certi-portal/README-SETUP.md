# Sistema de Certificados - Frontend Angular

## Configuración del Proyecto

### Prerrequisitos
- Node.js (versión 18 o superior)
- npm o yarn
- Angular CLI
- Backend Laravel ejecutándose en `http://localhost:8000`

### Instalación

1. **Instalar dependencias:**
   ```bash
   npm install
   ```

2. **Configurar variables de entorno:**
   - El archivo `src/environments/environment.ts` ya está configurado para desarrollo
   - Para producción, actualiza `src/environments/environment.prod.ts`

3. **Ejecutar el proyecto:**
   ```bash
   ng serve
   ```
   El proyecto estará disponible en `http://localhost:4200`

### Estructura del Proyecto

```
src/
├── app/
│   ├── core/                    # Servicios y lógica central
│   │   ├── guards/             # Guards de autenticación
│   │   ├── interceptors/       # Interceptores HTTP
│   │   ├── models/             # Interfaces y modelos
│   │   └── services/           # Servicios principales
│   ├── features/               # Módulos de funcionalidades
│   │   ├── auth/              # Autenticación
│   │   └── dashboard/         # Panel principal
│   ├── shared/                # Componentes compartidos
│   └── environments/          # Variables de entorno
```

### Funcionalidades Implementadas

#### ✅ Autenticación
- **Login:** Formulario con validación
- **Logout:** Cierre de sesión seguro
- **Guards:** Protección de rutas
- **Interceptor:** Manejo automático de tokens JWT
- **Estado:** Gestión de estado con Angular Signals

#### ✅ Rutas
- `/login` - Página de inicio de sesión
- `/principal` - Dashboard principal (protegida)
- `/404` - Página de error 404

#### ✅ Servicios
- **AuthService:** Manejo completo de autenticación
- **AuthInterceptor:** Interceptor HTTP para tokens
- **AuthGuard:** Protección de rutas autenticadas
- **GuestGuard:** Redirección para usuarios autenticados

### Configuración del Backend

Asegúrate de que tu backend Laravel tenga:

1. **CORS configurado** en `config/cors.php`:
   ```php
   'allowed_origins' => [
       'http://localhost:4200',
       'http://127.0.0.1:4200',
   ],
   ```

2. **Sanctum configurado** en `config/sanctum.php`

3. **Rutas API** disponibles en `/api/auth/login`

### Uso del Sistema

1. **Acceder al login:** Navega a `http://localhost:4200`
2. **Iniciar sesión:** Usa las credenciales de tu backend
3. **Dashboard:** Después del login, serás redirigido a `/principal`
4. **Cerrar sesión:** Usa el botón "Cerrar Sesión" en el dashboard

### Desarrollo

#### Agregar nuevas rutas protegidas:
```typescript
{
  path: 'nueva-ruta',
  loadComponent: () => import('./features/nueva/nueva.component').then(m => m.NuevaComponent),
  canActivate: [AuthGuard]
}
```

#### Agregar nuevas rutas públicas:
```typescript
{
  path: 'publica',
  loadComponent: () => import('./features/publica/publica.component').then(m => m.PublicaComponent),
  canActivate: [GuestGuard]
}
```

### Troubleshooting

#### Error de CORS:
- Verifica que el backend esté ejecutándose en `http://localhost:8000`
- Confirma la configuración CORS en Laravel

#### Error de autenticación:
- Verifica que las rutas API estén disponibles
- Revisa la consola del navegador para errores HTTP

#### Problemas de compilación:
- Ejecuta `npm install` para reinstalar dependencias
- Verifica que tengas Node.js 18+ instalado

### Próximos Pasos

1. Implementar registro de usuarios
2. Agregar más funcionalidades al dashboard
3. Implementar gestión de certificados
4. Agregar tests unitarios
5. Configurar CI/CD

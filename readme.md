# Sistema de Certificados Digitales - Guía de Pruebas Backend

## Tabla de Contenidos
1. [Introducción](#introducción)
2. [Configuración del Entorno de Pruebas](#configuración-del-entorno-de-pruebas)
3. [Tipos de Pruebas](#tipos-de-pruebas)
4. [Estructura de Pruebas](#estructura-de-pruebas)
5. [Configuración Inicial](#configuración-inicial)
6. [Ejecutar Pruebas](#ejecutar-pruebas)
7. [Pruebas de Integración](#pruebas-de-integración)
8. [Pruebas Unitarias](#pruebas-unitarias)
9. [Pruebas de Funcionalidad (Feature)](#pruebas-de-funcionalidad-feature)
10. [Troubleshooting](#troubleshooting)
11. [Mejores Prácticas](#mejores-prácticas)

---

## Introducción

Esta guía proporciona un paso a paso completo para configurar y ejecutar pruebas en el backend Laravel del Sistema de Certificados Digitales. Las pruebas están diseñadas para ser rápidas, confiables y fáciles de mantener.

### Características del Sistema de Pruebas:
- ✓ Base de datos SQLite en memoria para velocidad
- ✓ Cache basado en archivos para evitar conflictos
- ✓ Pruebas de integración con autenticación Sanctum
- ✓ Cobertura de endpoints críticos
- ✓ Validación de permisos y roles con Spatie

---

## Configuración del Entorno de Pruebas

### Paso 1: Verificar Dependencias

Asegúrate de tener instaladas las siguientes dependencias:

```bash
# Navegar al directorio del backend
cd certi-backend

# Verificar dependencias de testing
composer show | grep -E "(phpunit|laravel/sanctum|spatie/laravel-permission)"
```



### Paso 2: Configuración de Archivos

#### 2.1 Archivo phpunit.xml

El archivo `phpunit.xml` ya está configurado con las siguientes variables de entorno para testing:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="PULSE_ENABLED" value="false"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
</php>
```

#### 2.2 Archivo TestCase.php

La clase base `TestCase.php` está configurada para:
- Usar SQLite en memoria
- Ejecutar migraciones automáticamente
- Limpiar la base de datos entre pruebas

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Forzar SQLite en memoria para todas las pruebas
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);
    
    // Ejecutar migraciones
    $this->artisan('migrate');
}
```



---

## Tipos de Pruebas

### 1. Pruebas Unitarias (Unit Tests)
- **Ubicación:** `tests/Unit/`
- **Propósito:** Probar componentes individuales (modelos, servicios)
- **Velocidad:** Muy rápidas (< 100ms por prueba)

### 2. Pruebas de Funcionalidad (Feature Tests)
- **Ubicación:** `tests/Feature/`
- **Propósito:** Probar funcionalidades completas con base de datos
- **Velocidad:** Rápidas (100-500ms por prueba)

### 3. Pruebas de Integración
- **Ubicación:** `tests/Feature/Auth/`, `tests/Feature/Public/`
- **Propósito:** Probar flujos completos con autenticación y permisos
- **Velocidad:** Moderadas (200-1000ms por prueba)

---

## Estructura de Pruebas

```
tests/
├── Feature/
│   ├── Auth/
│   │   └── AuthIntegrationTest.php      # Pruebas de autenticación
│   ├── Models/
│   │   ├── CertificateTemplateIntegrationTest.php
│   │   └── UserIntegrationTest.php
│   ├── Public/
│   │   ├── CertificateByCodeTest.php    # API pública
│   │   └── ValidationPublicTest.php
│   ├── Services/
│   │   ├── CertificateTemplateServiceTest.php
│   │   └── ValidationServiceTest.php
│   └── ExampleTest.php
├── Unit/
│   ├── Models/
│   ├── Services/
│   └── ExampleTest.php
└── TestCase.php                         # Clase base para todas las pruebas
```



---

## Configuración Inicial

### Paso 1: Preparar el Entorno

```bash
# 1. Navegar al directorio del backend
cd certi-backend

# 2. Instalar dependencias si no están instaladas
composer install

# 3. Verificar configuración de cache (debe ser 'file' en .env)
php artisan config:show cache.default
```

### Paso 2: Limpiar Cache de Configuración

```bash
# Limpiar cache de configuración
php artisan config:clear

# Limpiar cache de aplicación
php artisan cache:clear
```



### Paso 3: Verificar Base de Datos de Testing

```bash
# Las pruebas usan SQLite en memoria, no requiere configuración adicional
# Verificar que las migraciones están actualizadas
php artisan migrate:status
```

---

## Ejecutar Pruebas

### Comandos Básicos

#### 1. Ejecutar Todas las Pruebas
```bash
php artisan test
```

#### 2. Ejecutar Pruebas con Salida Detallada
```bash
php artisan test --verbose
```

#### 3. Ejecutar Pruebas en Modo Silencioso
```bash
php artisan test -q
```



### Comandos por Tipo de Prueba

#### 1. Solo Pruebas Unitarias
```bash
php artisan test --testsuite=Unit
```

#### 2. Solo Pruebas de Funcionalidad
```bash
php artisan test --testsuite=Feature
```

#### 3. Pruebas Específicas por Filtro
```bash
# Ejecutar solo pruebas de autenticación
php artisan test --filter=Auth

# Ejecutar una clase específica
php artisan test --filter=AuthIntegrationTest
```



### Comandos con Métricas de Rendimiento

#### 1. Mostrar Tiempo de Ejecución
```bash
php artisan test --profile
```

#### 2. Ejecutar con Cobertura (si tienes Xdebug)
```bash
php artisan test --coverage
```

### Ejemplos de salida esperada (referencial)
- Al ejecutar `php artisan test`:
```
PASS  Tests: 30, Assertions: 120
Time: 5.21s, Memory: 48.0 MB
```
- Al limpiar configuración/caché:
```
Configuration cache cleared!
Application cache cleared!
```
- Al generar cobertura Clover:
```
Generating code coverage report in Clover XML format ... done
Coverage: 72.35%
```

---

## Pruebas de Integración

### Pruebas de Autenticación (AuthIntegrationTest)

Esta suite prueba el flujo completo de autenticación con Sanctum:

#### Casos de Prueba Incluidos:

1. **Registro de Usuario**
   - Crea usuario con rol por defecto
   - Valida estructura de respuesta
   - Verifica token de acceso

2. **Login Exitoso**
   - Autentica con credenciales válidas
   - Retorna token y datos de usuario

3. **Login Fallido**
   - Maneja credenciales inválidas
   - Retorna error 401

4. **Información de Usuario (/me)**
   - Requiere token válido
   - Retorna datos completos del usuario

5. **Logout**
   - Revoca token de acceso
   - Limpia sesión

6. **Endpoints Protegidos**
   - Verifica permisos en `/api/users`
   - Verifica permisos en `/api/certificates`

#### Ejecutar Pruebas de Autenticación:
```bash
php artisan test --filter=AuthIntegrationTest
```



### Ejemplo de Estructura de Prueba de Integración:

```php
public function test_login_success_returns_token_and_user(): void
{
    // Arrange: Crear usuario de prueba
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('Secret123!'),
    ]);

    // Act: Realizar login
    $response = $this->postJson('/api/auth/login', [
        'email' => 'john@example.com',
        'password' => 'Secret123!'
    ]);

    // Assert: Verificar respuesta
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'access_token',
                'token_type'
            ]
        ]);
}
```

---

## Pruebas Unitarias

### Características de las Pruebas Unitarias:

- **Sin base de datos:** Usan mocks y stubs
- **Muy rápidas:** < 50ms por prueba
- **Aisladas:** Prueban una sola unidad de código

### Ejemplo de Estructura:

```
tests/Unit/
├── Models/
│   ├── UserTest.php
│   ├── CertificateTest.php
│   └── ActivityTest.php
├── Services/
│   ├── AuthServiceTest.php
│   └── CertificateServiceTest.php
└── Helpers/
    └── ValidationHelperTest.php
```

#### Ejecutar Solo Pruebas Unitarias:
```bash
php artisan test --testsuite=Unit
```



---

## Pruebas de Funcionalidad (Feature)

### Pruebas de API Pública

#### 1. Validación de Certificados por Código
```bash
# Ejecutar pruebas de API pública
php artisan test tests/Feature/Public/CertificateByCodeTest.php
```

#### 2. Pruebas de Servicios
```bash
# Ejecutar pruebas de servicios
php artisan test tests/Feature/Services/
```

### Estructura de Prueba Feature:

```php
public function test_certificate_by_code_returns_valid_data(): void
{
    // Arrange: Crear certificado de prueba
    $certificate = Certificate::factory()->create([
        'unique_code' => 'TEST123',
        'status' => 'issued'
    ]);

    // Act: Consultar por código
    $response = $this->getJson("/api/public/certificate/TEST123");

    // Assert: Verificar respuesta
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'unique_code' => 'TEST123',
                'status' => 'issued'
            ]
        ]);
}
```



---

## Troubleshooting

### Problemas Comunes y Soluciones

#### 1. Error: "Cache table doesn't exist"

**Problema:** La base de datos de cache no está configurada correctamente.

**Solución:**
```bash
# Verificar configuración de cache
php artisan config:show cache.default

# Si es 'database', cambiar a 'file' en .env
CACHE_STORE=file

# Limpiar configuración
php artisan config:clear
php artisan cache:clear
```

#### 2. Error: "GuardDoesNotMatch"

**Problema:** Conflicto entre guards de Sanctum y Spatie Permission.

**Solución:**
```php
// En las pruebas, usar guard 'web' para roles y permisos
Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
Permission::firstOrCreate(['name' => 'users.read', 'guard_name' => 'web']);
```

#### 3. Pruebas Lentas

**Problema:** Las pruebas tardan mucho en ejecutarse.

**Solución:**
```bash
# Verificar que se usa SQLite en memoria
php artisan config:show database.connections.sqlite.database
# Debe mostrar: ":memory:"

# Ejecutar solo pruebas específicas
php artisan test --filter=AuthIntegrationTest
```

#### 4. Error: "Role does not exist"

**Problema:** Los roles no se crean antes de las pruebas.

**Solución:**
```php
// En setUp() o en la prueba específica
protected function setUp(): void
{
    parent::setUp();
    
    // Crear roles necesarios
    Role::firstOrCreate(['name' => 'usuario_final', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
}
```



---

## Mejores Prácticas

### 1. Organización de Pruebas

#### Nomenclatura:
- **Clases:** `NombreTest.php` (Unit) o `NombreIntegrationTest.php` (Feature)
- **Métodos:** `test_should_do_something_when_condition()`

#### Estructura AAA (Arrange-Act-Assert):
```php
public function test_user_can_create_certificate(): void
{
    // Arrange: Preparar datos
    $user = User::factory()->create();
    $template = CertificateTemplate::factory()->create();
    
    // Act: Ejecutar acción
    $response = $this->actingAs($user)
        ->postJson('/api/certificates', [
            'template_id' => $template->id,
            'user_id' => $user->id
        ]);
    
    // Assert: Verificar resultado
    $response->assertStatus(201);
    $this->assertDatabaseHas('certificates', [
        'user_id' => $user->id,
        'template_id' => $template->id
    ]);
}
```

### 2. Uso de Factories

#### Crear datos de prueba consistentes:
```php
// En lugar de crear manualmente
$user = new User([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password')
]);

// Usar factories
$user = User::factory()->create([
    'email' => 'test@example.com'
]);
```

### 3. Limpieza de Datos

#### Usar RefreshDatabase:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
    
    // La base de datos se limpia automáticamente
}
```

### 4. Pruebas de Permisos

#### Verificar autorización correctamente:
```php
public function test_endpoint_requires_permission(): void
{
    $user = User::factory()->create();
    
    // Sin permiso - debe fallar
    $response = $this->actingAs($user)
        ->getJson('/api/protected-endpoint');
    $response->assertStatus(403);
    
    // Con permiso - debe funcionar
    $user->givePermissionTo('required.permission');
    $response = $this->actingAs($user)
        ->getJson('/api/protected-endpoint');
    $response->assertStatus(200);
}
```

### 5. Optimización de Velocidad

#### Técnicas para pruebas rápidas:
- Usar SQLite en memoria
- Minimizar operaciones de I/O
- Usar cache en array
- Evitar seeders innecesarios
- Agrupar assertions relacionadas



---

## Comandos de Referencia Rápida

### Ejecución de Pruebas
```bash
# Todas las pruebas
php artisan test

# Solo unitarias
php artisan test --testsuite=Unit

# Solo feature
php artisan test --testsuite=Feature

# Filtro específico
php artisan test --filter=AuthIntegrationTest

# Con perfil de tiempo
php artisan test --profile

# Modo silencioso
php artisan test -q

# Con cobertura
php artisan test --coverage
```

### Mantenimiento
```bash
# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Verificar migraciones
php artisan migrate:status

# Verificar configuración
php artisan config:show cache.default
php artisan config:show database.default
```



---

## Conclusión

Esta guía proporciona todo lo necesario para ejecutar pruebas efectivas en el backend Laravel del Sistema de Certificados Digitales. Las pruebas están optimizadas para ser rápidas y confiables, utilizando SQLite en memoria y configuraciones específicas para testing.

### Métricas de Rendimiento Esperadas:
- **Pruebas Unitarias:** < 50ms por prueba
- **Pruebas de Integración:** 200-1000ms por prueba
- **Suite completa:** < 30 segundos

### Cobertura de Pruebas:
- ✓ Autenticación y autorización
- ✓ Endpoints públicos
- ✓ Gestión de permisos
- ✓ Validación de datos
- ✓ Respuestas de API

Para soporte adicional o preguntas específicas, consulta la documentación de Laravel Testing o los archivos de prueba existentes como referencia.

---



## Informe de Configuración de Pruebas y Análisis de Calidad (Laravel + SonarQube)

Este documento complementa la guía de pruebas, detallando los pasos técnicos para implementar un pipeline de pruebas automatizadas y análisis estático de código, culminando en la visualización de métricas en SonarQube.

### 1. Estrategia de Pruebas (PHPUnit)

- `tests/Unit` (Pruebas Unitarias)
  - Objetivo: Probar componentes de lógica pequeños y aislados (métodos de un modelo, clases de servicio, reglas de validación).
  - Reglas: No deben interactuar con base de datos, APIs externas, ni sistema de archivos.

- `tests/Feature` (Pruebas de Integración/Característica)
  - Objetivo: Probar flujos completos (CRUD de un controlador, ejecución de un Job).
  - Reglas: Cargan el framework completo e interactúan con una base de datos de prueba. Las APIs externas deben ser simuladas con `Http::fake()`.

### 2. Configuración de Xdebug (Requisito para Cobertura)

Para que SonarQube muestre el porcentaje de código cubierto por pruebas, es obligatorio instalar y configurar Xdebug.

- Paso 1: Verificación inicial
  - Ejecutar: `php -v` y confirmar que no aparece Xdebug si no está instalado.

- Paso 2: Instalación (Wizard de Xdebug)
  - Generar volcado de configuración: `php -i > phpinfo.txt`
  - Subir contenido a el Wizard de Xdebug: `https://xdebug.org/wizard`
  - Seguir instrucciones: descargar `.dll` y pasos de instalación específicos.

- Paso 3: Configuración en `php.ini`
  - Añadir `zend_extension="ruta\a\xdebug.dll"`
  - Añadir `xdebug.mode=coverage`
  - Nota: Configurar solo el modo cobertura para evitar ralentizar la app en uso normal.

- Paso 4: Verificación final
  - Abrir nueva terminal y ejecutar `php -v`.
  - Debe mostrarse: `with Xdebug v3.x.x, Copyright (c) 2002-20xx, by Derick Rethans`.

### 3. Generación del Reporte de Cobertura (clover.xml)

Con Xdebug instalado, ejecutar PHPUnit y generar el reporte de cobertura en formato Clover.

- Crear directorio de salida:
  - `mkdir build/logs`

- Ejecutar pruebas con cobertura (desde `certi-backend`):
  - `php artisan test --coverage --coverage-clover="build/logs/clover.xml"`

- Notas:
  - `--coverage` activa el modo de cobertura de Xdebug.
  - `--coverage-clover` especifica formato y ruta del reporte.
  - La ejecución se vuelve más lenta al rastrear cada línea (comportamiento esperado).

### 4. Configuración de SonarQube

El análisis de calidad requiere el servidor SonarQube y el cliente SonarScanner.

- Servidor SonarQube
  - Operativo y accesible en `http://localhost:9000` (por Docker).

- SonarScanner (Cliente)
  - Descargar y descomprimir en una carpeta (ejemplo: `D:\Pruebas y despliegue de software\sonar-scanner...`).

- Archivo `sonar-project.properties` (en la raíz del proyecto `certi-backend`):

```
# Identificador único del proyecto en SonarQube
sonar.projectKey=proyecto-certificados

# URL del servidor SonarQube (usando IP para evitar problemas de resolución)
sonar.host.url=http://miip:9000

# Token de autenticación (generado en SonarQube)
sonar.login=sqp_...tu_token_aqui...

# Rutas a analizar
sonar.sources=app
sonar.tests=tests

# Ruta al reporte de cobertura (Paso 3)
sonar.php.coverage.reportPaths=build/logs/clover.xml

# Archivos y carpetas a excluir del análisis
sonar.exclusions=**/vendor/**,**/storage/**,**/bootstrap/**,*.blade.php
```

### 5. Ejecución del Análisis y Visualización

- Ejecución del Scanner (Windows)
  - Debe ejecutarse desde la raíz del proyecto (`certi-backend`), no desde la carpeta `bin` del scanner.
  - En rutas con espacios, encierra la ruta entre comillas.
  - Comando ejemplo:
    - `"D:\Pruebas y despliegue de software\...\bin\sonar-scanner.bat"`

- Visualización de Resultados
  - Tras `EXECUTION SUCCESS`, acceder al dashboard de SonarQube y revisar métricas (cobertura, duplicaciones, vulnerabilidades y code smells).
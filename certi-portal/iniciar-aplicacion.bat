@echo off
echo ========================================
echo    SISTEMA DE CERTIFICADOS - ANGULAR
echo ========================================
echo.

echo [1/3] Instalando dependencias...
call npm install
if %errorlevel% neq 0 (
    echo ERROR: No se pudieron instalar las dependencias
    pause
    exit /b 1
)

echo.
echo [2/3] Verificando compilacion...
call ng build --configuration development
if %errorlevel% neq 0 (
    echo ERROR: La compilacion fallo
    pause
    exit /b 1
)

echo.
echo [3/3] Iniciando servidor de desarrollo...
echo La aplicacion estara disponible en: http://localhost:4200
echo.
echo Presiona Ctrl+C para detener el servidor
echo.
call ng serve

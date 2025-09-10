@echo off
echo Instalando dependencias de Angular...
echo.

echo Instalando @angular/animations...
call npm install @angular/animations@^20.3.0

echo.
echo Instalando @angular/platform-browser/animations...
call npm install @angular/platform-browser@^20.3.0

echo.
echo Instalando todas las dependencias...
call npm install

echo.
echo Dependencias instaladas correctamente.
echo Ahora puedes ejecutar: ng serve
pause

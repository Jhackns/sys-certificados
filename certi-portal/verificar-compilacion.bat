@echo off
echo Verificando compilacion del proyecto Angular...
echo.

echo Instalando dependencias...
call npm install

echo.
echo Compilando proyecto...
call ng build --configuration development

echo.
echo Compilacion completada. Revisa los mensajes anteriores para errores.
pause

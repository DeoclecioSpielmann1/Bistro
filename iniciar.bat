@echo off
REM Inicia o servidor embutido do PHP nesta pasta.
cd /d "%~dp0"

if exist "C:\xampp\php\php.exe" (
  set "PHP=C:\xampp\php\php.exe"
) else (
  where php >nul 2>nul
  if errorlevel 1 (
    echo PHP nao encontrado. Instale o XAMPP ou adicione o PHP ao PATH.
    pause & exit /b 1
  )
  set "PHP=php"
)

echo Abrindo http://127.0.0.1:8000/
echo (Ctrl+C para parar o servidor)
start "" /b cmd /c "ping 127.0.0.1 -n 3 >nul & start "" http://127.0.0.1:8000/"
"%PHP%" -S 127.0.0.1:8000

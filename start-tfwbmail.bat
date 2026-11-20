@echo off
setlocal
cd /d "%~dp0tfwbmail"
set "PHP="
for /r "%LOCALAPPDATA%\Microsoft\WinGet\Packages" %%F in (php.exe) do (
  if exist "%%~dpFext\php_imap.dll" set "PHP=%%F"
)
if not exist "%PHP%" set "PHP=%LOCALAPPDATA%\Microsoft\WinGet\Links\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=C:\php\php.exe"
if not exist "%PHP%" (
  echo PHP 8.3 is niet gevonden.
  pause
  exit /b 1
)
start "" "http://127.0.0.1:8090"
for %%D in ("%PHP%") do set "PHP_DIR=%%~dpD"
"%PHP%" -d "extension_dir=%PHP_DIR%ext" -d extension=php_openssl.dll -d extension=php_mbstring.dll -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll -d extension=php_imap.dll -S 127.0.0.1:8090

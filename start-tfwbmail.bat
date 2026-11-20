@echo off
setlocal
if exist "%~dp0tfwbmail\downloads\tfwbmail.exe" (
  start "" "%~dp0tfwbmail\downloads\tfwbmail.exe"
  exit /b 0
)
cd /d "%~dp0tfwbmail"
set "URL=http://127.0.0.1:8090/"
curl.exe --silent --max-time 1 "%URL%" >nul 2>nul
if not errorlevel 1 start "" "%URL%" & exit /b 0
set "PHP="
for /r "%LOCALAPPDATA%\Microsoft\WinGet\Packages" %%F in (php.exe) do (
  if exist "%%~dpFext\php_imap.dll" set "PHP=%%F"
)
if not exist "%PHP%" set "PHP=%LOCALAPPDATA%\Microsoft\WinGet\Links\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=C:\php\php.exe"
if not exist "%PHP%" (
  echo PHP met IMAP kon niet worden gevonden. Start tfwbmail.exe voor de installatie-wizard.
  pause
  exit /b 1
)
for %%D in ("%PHP%") do set "PHP_DIR=%%~dpD"
start "" /b "%PHP%" -d "extension_dir=%PHP_DIR%ext" -d extension=php_openssl.dll -d extension=php_mbstring.dll -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll -d extension=php_imap.dll -S 127.0.0.1:8090
for /l %%N in (1,1,80) do (
  curl.exe --silent --max-time 1 "%URL%" >nul 2>nul
  if not errorlevel 1 (
    start "" "%URL%"
    exit /b 0
  )
  timeout /t 1 /nobreak >nul
)
echo tfwbmail kon niet starten op poort 8090.
pause

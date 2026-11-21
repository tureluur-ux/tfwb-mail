@echo off
setlocal
if exist "%~dp0tfwbmail\downloads\tfwbmail.exe" (
  start "" "%~dp0tfwbmail\downloads\tfwbmail.exe"
  exit /b 0
)
echo Native tfwbmail.exe niet gevonden.
pause
exit /b 1

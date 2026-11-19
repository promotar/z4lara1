@echo off
REM Build Vite assets for production (Windows)

setlocal enabledelayedexpansion

echo Building Vite assets...
echo ========================

REM Check if we're in the right directory
if not exist package.json (
    echo Error: package.json not found. Run this script from the project root.
    exit /b 1
)

REM Check if node_modules exists
if not exist node_modules (
    echo Installing npm dependencies...
    call npm install
    if errorlevel 1 exit /b 1
)

REM Build assets
echo Running: npm run build
call npm run build
if errorlevel 1 exit /b 1

echo.
echo Build complete!
echo Build artifacts: .\public\build\
dir /s /b public\build\

REM Verify manifest.json was created
if not exist public\build\manifest.json (
    echo ERROR: manifest.json was not created!
    exit /b 1
)

echo.
echo [OK] Assets built successfully
echo [OK] Vite manifest.json is ready

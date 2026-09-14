@echo off
title Diabetes Clinic - Train + Start AI
color 0E

echo ============================================
echo   Diabetes Clinic AI Engine
echo   Step 1: Train Models + Step 2: Start Server
echo ============================================
echo.

:: Change to project directory
cd /d "%~dp0"

:: Check Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python is not installed or not in PATH!
    pause
    exit /b 1
)

:: Set encoding for Arabic text
set PYTHONIOENCODING=utf-8

:: Step 1: Run data pipeline
echo [STEP 1/3] Extracting and processing data from database...
python -m data_pipeline.main
if %errorlevel% neq 0 (
    echo [WARNING] Data pipeline had issues. Continuing anyway...
) else (
    echo [OK] Data pipeline complete.
)
echo.

:: Step 2: Train AI models
echo [STEP 2/3] Training AI models (Risk Prediction + Healing Estimation)...
python -m ai_engine.trainer
if %errorlevel% neq 0 (
    echo [WARNING] Model training had issues. Continuing anyway...
) else (
    echo [OK] AI models trained successfully.
)
echo.

:: Step 3: Start Flask server
echo [STEP 3/3] Starting Flask AI server...
echo.
echo ============================================
echo   Server: http://127.0.0.1:5000
echo   Dashboard: http://localhost/clinic/www/modules/ai/dashboard.php
echo   Health: http://127.0.0.1:5000/api/health
echo.
echo   Press Ctrl+C to stop the server
echo ============================================
echo.

:: Start Flask in same window
color 0A
python ai_api\app.py

:: On exit
echo.
echo [INFO] AI Engine stopped.
pause

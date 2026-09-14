@echo off
title Diabetes Clinic - AI Engine
color 0A

echo ============================================
echo   Diabetes Clinic AI Engine - Starting...
echo ============================================
echo.

:: Change to project directory
cd /d "%~dp0"

:: Verify Python is available
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python is not installed or not in PATH!
    echo Please install Python 3.7+ from https://www.python.org/
    pause
    exit /b 1
)

:: Verify Flask server script exists
if not exist "ai_api\app.py" (
    echo [ERROR] ai_api\app.py not found!
    echo Make sure you are in the correct project folder.
    pause
    exit /b 1
)

:: Verify model files exist
if not exist "ai_api\models\risk_rf.pkl" (
    echo [WARNING] Model files not found in ai_api\models\
    echo.
    echo The AI models have not been trained yet.
    echo To train them, run:
    echo   python -m data_pipeline.main
    echo   python -m ai_engine.trainer
    echo.
    echo Starting server with rule-based fallback only...
    echo.
)

echo [INFO] Starting Flask AI server on http://127.0.0.1:5000
echo [INFO] Open your browser and go to:
echo        http://localhost/clinic/www/modules/ai/dashboard.php
echo.
echo [INFO] Press Ctrl+C to stop the server
echo ============================================
echo.

:: Set Python encoding for Arabic text support
set PYTHONIOENCODING=utf-8

:: Start the Flask server
python ai_api\app.py

:: If Flask exits, show message
echo.
echo [INFO] AI Engine has stopped.
pause

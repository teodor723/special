@echo off
echo ========================================
echo  Laravel API - Quick Setup Script
echo ========================================
echo.

REM Check if PHP is available
php -v >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP is not installed or not in PATH!
    echo.
    echo Please install PHP 8.2+ and add it to your PATH.
    echo.
    echo Options:
    echo   1. Install XAMPP: https://www.apachefriends.org/
    echo   2. Install Laragon: https://laragon.org/
    echo   3. Install standalone PHP: https://windows.php.net/download/
    echo.
    pause
    exit /b 1
)

echo [OK] PHP is installed
php -v
echo.

REM Check if Composer is available
composer --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Composer is not installed or not in PATH!
    echo.
    echo Please install Composer from: https://getcomposer.org/download/
    echo.
    pause
    exit /b 1
)

echo [OK] Composer is installed
composer --version
echo.

echo ========================================
echo  Installing Dependencies...
echo ========================================
echo.

REM Install Composer dependencies
composer install

if errorlevel 1 (
    echo.
    echo [ERROR] Composer install failed!
    echo Please check the error messages above.
    pause
    exit /b 1
)

echo.
echo [OK] Dependencies installed successfully!
echo.

REM Check if .env exists, if not copy from example
if not exist ".env" (
    echo ========================================
    echo  Creating .env file...
    echo ========================================
    copy .env.example .env
    echo [OK] .env file created
    echo.
    
    echo ========================================
    echo  Generating Application Key...
    echo ========================================
    php artisan key:generate
    echo.
)

echo ========================================
echo  Setup Complete!
echo ========================================
echo.
echo Next steps:
echo   1. Edit .env file with your database credentials
echo   2. Configure Pusher, AWS, Firebase keys in .env
echo   3. Run: php artisan serve
echo   4. Test: http://localhost:8000/api/health
echo.
echo For detailed instructions, see SETUP_GUIDE.md
echo.
pause


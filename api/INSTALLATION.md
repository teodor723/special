# Installation Guide - Dating Site Laravel API

## Prerequisites

Before you begin, ensure you have the following installed on your Windows system:

### 1. PHP 8.2+

**Download & Install:**
- Download from: https://windows.php.net/download/
- Extract to `C:\php`
- Add to PATH: `C:\php`

**Configure PHP:**
1. Copy `php.ini-development` to `php.ini`
2. Enable required extensions in `php.ini`:
```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=curl
extension=gd
```

### 2. Composer

**Download & Install:**
- Download from: https://getcomposer.org/download/
- Run the installer
- Verify: `composer --version`

### 3. MySQL/MariaDB

You already have this configured with your existing database.

## Installation Steps

### Step 1: Navigate to API Directory

```powershell
cd E:\workspace\dating-site\httpdocs\api
```

### Step 2: Install PHP Dependencies

```powershell
composer install
```

This will install:
- Laravel Framework 12.x
- Laravel Sanctum (authentication)
- Pusher PHP Server
- AWS SDK for PHP
- Redis client (Predis)
- And all other dependencies

### Step 3: Create Environment File

```powershell
copy env.example .env
```

### Step 4: Configure Environment

Open `.env` in your editor and configure:

```env
# Application
APP_NAME="Dating Site API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (use your existing database)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dating_site
DB_USERNAME=root
DB_PASSWORD=your_password_here

# Redis (optional - install from https://redis.io/download)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Pusher (get from https://pusher.com)
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_key
PUSHER_APP_SECRET=your_pusher_secret
PUSHER_APP_CLUSTER=mt1

# AWS (get from AWS Console)
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_s3_bucket

# Firebase (get from Firebase Console)
FIREBASE_API_KEY=your_firebase_api_key

# Geolocation API
GEOLOCATION_API_KEY=your_geoapify_key
```

### Step 5: Generate Application Key

```powershell
php artisan key:generate
```

This creates a secure encryption key for your application.

### Step 6: Create Storage Directories

```powershell
# Create required directories
New-Item -ItemType Directory -Path "storage\logs" -Force
New-Item -ItemType Directory -Path "storage\framework\cache\data" -Force
New-Item -ItemType Directory -Path "storage\framework\sessions" -Force
New-Item -ItemType Directory -Path "storage\framework\views" -Force
New-Item -ItemType Directory -Path "bootstrap\cache" -Force

# Set permissions (PowerShell as Administrator)
icacls "storage" /grant Everyone:F /T
icacls "bootstrap\cache" /grant Everyone:F /T
```

### Step 7: Test Database Connection

```powershell
php artisan tinker
```

Then run:
```php
DB::connection()->getPdo();
exit
```

If successful, you'll see the PDO connection object.

### Step 8: Start Development Server

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

The API will be available at: `http://localhost:8000`

### Step 9: Test the API

Open a new PowerShell window and test:

```powershell
# Test health endpoint
curl http://localhost:8000/api/health

# Test config endpoint
curl http://localhost:8000/api/config
```

## Directory Structure Verification

Ensure you have this structure:

```
E:\workspace\dating-site\httpdocs\api\
├── app\
│   ├── Http\Controllers\
│   ├── Models\
│   ├── Services\
│   └── Helpers\
├── bootstrap\
│   ├── app.php
│   └── cache\
├── config\
│   ├── app.php
│   ├── database.php
│   ├── sanctum.php
│   └── cors.php
├── database\
│   ├── factories\
│   └── seeders\
├── public\
│   ├── index.php
│   └── .htaccess
├── routes\
│   ├── api.php
│   └── console.php
├── storage\
│   ├── app\
│   ├── framework\
│   └── logs\
├── vendor\
├── .env
├── .env.example
├── artisan
├── composer.json
└── README.md
```

## Troubleshooting

### Problem: `composer: command not found`

**Solution:**
```powershell
# Add Composer to PATH
$env:PATH += ";C:\ProgramData\ComposerSetup\bin"

# Or install Composer globally
```

### Problem: `Class 'PDO' not found`

**Solution:**
1. Open `C:\php\php.ini`
2. Uncomment: `extension=pdo_mysql`
3. Restart PHP server

### Problem: Permission denied on storage

**Solution:**
```powershell
# Run PowerShell as Administrator
icacls "E:\workspace\dating-site\httpdocs\api\storage" /grant Everyone:F /T
icacls "E:\workspace\dating-site\httpdocs\api\bootstrap\cache" /grant Everyone:F /T
```

### Problem: Database connection refused

**Solution:**
1. Verify MySQL is running
2. Check DB credentials in `.env`
3. Test connection:
```powershell
mysql -u root -p -e "SELECT 1"
```

### Problem: Port 8000 already in use

**Solution:**
```powershell
# Use a different port
php artisan serve --port=8001
```

## Next Steps

1. **Configure Mobile App:**
   Update `mobile-react/src/services/api.service.ts`:
   ```typescript
   const API_URL = 'http://localhost:8000/api';
   ```

2. **Test Authentication:**
   ```powershell
   curl -X POST http://localhost:8000/api/auth/login `
     -H "Content-Type: application/json" `
     -d '{\"email\":\"test@example.com\",\"password\":\"password\"}'
   ```

3. **Enable Redis (Optional):**
   - Download: https://github.com/microsoftarchive/redis/releases
   - Install and start Redis
   - Update `.env`: `CACHE_STORE=redis`

4. **Production Deployment:**
   - See DEPLOYMENT.md for production setup
   - Configure web server (Apache/Nginx)
   - Set up SSL certificate
   - Enable caching

## Getting Help

- Check logs: `storage\logs\laravel.log`
- Enable debug: `APP_DEBUG=true` in `.env`
- Laravel docs: https://laravel.com/docs/12.x
- Sanctum docs: https://laravel.com/docs/12.x/sanctum

## Success! 🎉

If you can access `http://localhost:8000/api/health` and get a JSON response, your installation is complete!

Next: Read `README.md` for API usage and endpoint documentation.


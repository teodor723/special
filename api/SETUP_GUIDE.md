# 🚀 Laravel API Setup Guide

## Prerequisites

Before running the Laravel project, you need:
- **PHP 8.2 or higher** (with required extensions)
- **Composer** (PHP dependency manager)
- **MySQL** (database server)
- **Web Server** (Apache/Nginx or PHP built-in server)

---

## Step 1: Install PHP (if not already installed)

### Option A: Using XAMPP (Recommended for Windows)
1. Download XAMPP from: https://www.apachefriends.org/
2. Install XAMPP (includes PHP, MySQL, Apache)
3. Add PHP to PATH:
   - Open System Environment Variables
   - Add `C:\xampp\php` to your PATH variable

### Option B: Using Laragon
1. Download Laragon from: https://laragon.org/
2. Install Laragon (includes everything)
3. PHP will be automatically available

### Option C: Standalone PHP
1. Download PHP 8.2+ from: https://windows.php.net/download/
2. Extract to `C:\php`
3. Add `C:\php` to PATH
4. Copy `php.ini-development` to `php.ini`
5. Enable required extensions in `php.ini`:
   ```ini
   extension=openssl
   extension=pdo_mysql
   extension=mbstring
   extension=curl
   extension=fileinfo
   extension=tokenizer
   extension=xml
   ```

---

## Step 2: Verify PHP Installation

Open **Command Prompt** or **PowerShell** and run:

```bash
php -v
```

You should see something like:
```
PHP 8.2.x (cli) (built: ...)
```

---

## Step 3: Install Composer

### Download and Install:
1. Go to: https://getcomposer.org/download/
2. Download **Composer-Setup.exe** for Windows
3. Run the installer
4. Follow the setup wizard (it will detect your PHP installation)

### Verify Installation:
```bash
composer --version
```

You should see:
```
Composer version 2.x.x
```

---

## Step 4: Install Laravel Dependencies

Navigate to your API directory:

```bash
cd E:\workspace\dating-site\httpdocs\api
```

Install all Composer dependencies:

```bash
composer install
```

This will install:
- Laravel Framework 12.x
- Laravel Sanctum (API authentication)
- Pusher (real-time messaging)
- AWS SDK (S3, Rekognition)
- Guzzle HTTP Client
- And more...

**Note:** This may take 2-5 minutes depending on your internet speed.

---

## Step 5: Configure Environment

### Create .env file:

```bash
# Copy the example file
copy .env.example .env
```

### Edit `.env` file with your settings:

```env
# Application
APP_NAME="Dating Site API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
APP_LOCALE=en

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Pusher (Real-time)
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_key
PUSHER_APP_SECRET=your_pusher_secret
PUSHER_APP_CLUSTER=your_cluster

# AWS (S3 & Rekognition)
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
AWS_REKOGNITION_MIN_CONFIDENCE=75

# Firebase
FIREBASE_API_KEY=your_firebase_api_key

# Geolocation
GEOLOCATION_API_KEY=your_geoapify_key

# Cache & Session
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

---

## Step 6: Generate Application Key

```bash
php artisan key:generate
```

This will set a unique `APP_KEY` in your `.env` file.

---

## Step 7: Test Database Connection

Make sure your database exists and credentials are correct:

```bash
php artisan migrate:status
```

If connection is successful, you'll see the migration status.

---

## Step 8: Start the Development Server

```bash
php artisan serve
```

You should see:
```
INFO  Server running on [http://127.0.0.1:8000].
Press Ctrl+C to stop the server.
```

---

## Step 9: Test Your API

### Test Health Check:
Open your browser or use curl:

```bash
curl http://localhost:8000/api/health
```

Expected response:
```json
{
  "status": "ok",
  "timestamp": "2025-12-18T12:00:00+00:00"
}
```

### Test Site Config (Public endpoint):
```bash
curl http://localhost:8000/api/config
```

### Test Login:
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

Expected response:
```json
{
  "token": "1|abc123xyz...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    ...
  }
}
```

### Test Protected Endpoint:
```bash
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Step 10: Production Deployment

### For Production, also run:

```bash
# Optimize configuration
php artisan config:cache

# Optimize routes
php artisan route:cache

# Optimize views (if you add any)
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### Set Production Environment:
In `.env`:
```env
APP_ENV=production
APP_DEBUG=false
```

---

## 📁 Project Structure

```
httpdocs/api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── AuthController.php
│   │       ├── ProfileController.php
│   │       ├── PhotoController.php
│   │       ├── DiscoveryController.php
│   │       ├── SpotlightController.php
│   │       ├── ChatController.php
│   │       ├── StoryController.php
│   │       └── ReelController.php
│   ├── Models/
│   │   ├── User.php
│   │   └── ... (16 models total)
│   └── Helpers/
│       └── helpers.php
├── config/
│   ├── app.php
│   ├── database.php
│   ├── sanctum.php
│   └── cors.php
├── routes/
│   └── api.php
├── public/
│   └── index.php (entry point)
├── .env (your configuration)
├── composer.json
└── artisan (CLI tool)
```

---

## 🔧 Common Commands

### Development:
```bash
# Start server
php artisan serve

# Start on specific port
php artisan serve --port=8080

# Start on specific host
php artisan serve --host=0.0.0.0 --port=8000

# Clear all cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# View routes
php artisan route:list

# Run tests (if you add them)
php artisan test
```

### Maintenance:
```bash
# Put application in maintenance mode
php artisan down

# Bring application back online
php artisan up

# View logs
tail -f storage/logs/laravel.log
```

---

## 🐛 Troubleshooting

### Issue: "Class not found"
**Solution:**
```bash
composer dump-autoload
```

### Issue: "Permission denied" (Linux/Mac)
**Solution:**
```bash
chmod -R 755 storage bootstrap/cache
```

### Issue: "Key not set"
**Solution:**
```bash
php artisan key:generate
```

### Issue: "Database connection failed"
**Solution:**
- Check database credentials in `.env`
- Ensure MySQL is running
- Verify database exists

### Issue: "Route not found"
**Solution:**
```bash
php artisan route:clear
php artisan config:clear
```

---

## 🌐 Web Server Configuration

### Apache (.htaccess already included)
Point your document root to: `E:\workspace\dating-site\httpdocs\api\public`

### Nginx Configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/httpdocs/api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## ✅ Verification Checklist

- [ ] PHP 8.2+ installed
- [ ] Composer installed
- [ ] Dependencies installed (`vendor/` folder exists)
- [ ] `.env` file configured
- [ ] `APP_KEY` generated
- [ ] Database credentials set
- [ ] Development server running
- [ ] Health check endpoint works (`/api/health`)
- [ ] Config endpoint works (`/api/config`)
- [ ] Login endpoint works (`/api/auth/login`)

---

## 📚 Additional Resources

- Laravel Documentation: https://laravel.com/docs/12.x
- Laravel Sanctum: https://laravel.com/docs/12.x/sanctum
- Pusher PHP SDK: https://github.com/pusher/pusher-http-php
- AWS SDK for PHP: https://docs.aws.amazon.com/sdk-for-php/

---

## 🆘 Need Help?

If you encounter issues:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode: Set `APP_DEBUG=true` in `.env`
3. Check Laravel documentation
4. Review error messages carefully

---

**🎉 You're all set! Your Laravel API is ready to use!**


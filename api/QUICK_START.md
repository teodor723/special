# ⚡ Quick Start - Laravel API

## 🚀 **3-Step Setup** (5 minutes)

### **Step 1: Install Prerequisites**

Download and install (if not already installed):

1. **XAMPP** (easiest): https://www.apachefriends.org/
   - Includes PHP + MySQL + Apache
   - OR use Laragon: https://laragon.org/

2. **Composer**: https://getcomposer.org/download/
   - Download Composer-Setup.exe
   - Run installer

---

### **Step 2: Run Setup Script**

Open **Command Prompt** or **PowerShell**:

```bash
cd E:\workspace\dating-site\httpdocs\api
setup.bat
```

This will automatically:
- ✅ Check PHP and Composer
- ✅ Install all dependencies
- ✅ Create .env file
- ✅ Generate application key

---

### **Step 3: Configure Database**

Edit `.env` file (created in step 2):

```env
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

**Important:** Use your existing database (no migrations needed)

---

## 🎯 **Start the Server**

```bash
php artisan serve
```

Server runs on: **http://localhost:8000**

---

## ✅ **Test It Works**

Open browser or curl:

```bash
# Health check
curl http://localhost:8000/api/health

# Expected: {"status":"ok","timestamp":"..."}
```

---

## 🔐 **Test Authentication**

### 1. Login:
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

### 2. Use Token:
```bash
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 📝 **Common Commands**

```bash
# Start server
php artisan serve

# Clear cache
php artisan cache:clear

# View routes
php artisan route:list

# Check logs
type storage\logs\laravel.log
```

---

## 🆘 **Troubleshooting**

### Issue: "php not recognized"
**Solution:** Add PHP to PATH or use full path:
```bash
C:\xampp\php\php.exe artisan serve
```

### Issue: "composer not recognized"
**Solution:** Reinstall Composer from getcomposer.org

### Issue: "Database connection failed"
**Solution:** Check `.env` database credentials

---

## 📚 **Full Documentation**

For detailed setup instructions, see: **SETUP_GUIDE.md**

---

## 🎉 **You're Done!**

Your Laravel API is now running and ready to use!

**API Base URL:** `http://localhost:8000/api`

**Available Endpoints:**
- `/api/health` - Health check
- `/api/config` - Site configuration
- `/api/auth/*` - Authentication endpoints
- `/api/profile/*` - Profile management
- `/api/chat/*` - Messaging
- `/api/discovery/*` - User discovery
- `/api/stories/*` - Stories
- `/api/reels/*` - Video reels
- And more... (56 endpoints total)

See **LARAVEL_REFACTORING_COMPLETE.md** for full API documentation.


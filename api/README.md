# Dating Site API - Laravel 12.x

A clean, modern, and performant REST API built with Laravel 12.x for the dating site mobile application.

## ✨ Features

- 🔐 **Token-based Authentication** (Laravel Sanctum)
- 🔥 **Firebase Authentication** Support
- 👤 **Comprehensive User Management**
- 💬 **Real-time Chat** (Pusher Integration)
- 📸 **Stories & Reels** (AWS S3 + Rekognition)
- 🎯 **Smart Discovery Algorithm**
- 💎 **Credits & Premium System**
- ⚡ **Query Optimization & Caching**
- 📊 **Clean Architecture**

## 🚀 Installation

### Prerequisites

- **PHP 8.2+** with extensions:
  - PDO
  - Mbstring
  - OpenSSL
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
  - Fileinfo
- **Composer 2.x**
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Redis** (optional, for caching)

### Step 1: Install Dependencies

```bash
cd E:\workspace\dating-site\httpdocs\api
composer install
```

### Step 2: Configure Environment

Copy the example environment file:

```bash
copy env.example .env
```

Edit `.env` and configure your database and services:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dating_site
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis (if available)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=your_cluster

# AWS
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket

# Firebase
FIREBASE_API_KEY=your_api_key
```

### Step 3: Generate Application Key

```bash
php artisan key:generate
```

### Step 4: Test the API

Start the development server:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Test the health endpoint:

```bash
curl http://localhost:8000/api/health
```

## 📁 Project Structure

```
api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Auth/
│   │       │   └── AuthController.php       # Authentication & registration
│   │       ├── Profile/
│   │       │   ├── ProfileController.php    # User profile management
│   │       │   └── PhotoController.php      # Photo uploads
│   │       ├── Discovery/
│   │       │   ├── DiscoveryController.php  # Meet, Game, Matches
│   │       │   └── SpotlightController.php  # Spotlight features
│   │       ├── Chat/
│   │       │   └── ChatController.php       # Messaging
│   │       ├── Story/
│   │       │   └── StoryController.php      # Stories & Live
│   │       └── Reel/
│   │           └── ReelController.php       # Reels/Videos
│   ├── Models/
│   │   ├── User.php                         # Main user model
│   │   ├── Chat.php
│   │   ├── UserPhoto.php
│   │   ├── UserLike.php
│   │   ├── Reel.php
│   │   └── ... (all eloquent models)
│   ├── Services/                            # Business logic
│   └── Helpers/
│       └── helpers.php                      # Global helper functions
├── config/                                  # Configuration files
├── routes/
│   └── api.php                             # API routes
├── database/                               # No migrations (using existing DB)
├── storage/                                # Logs, cache
└── public/
    └── index.php                           # Entry point
```

## 🔌 API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login with email/password |
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/firebase` | Firebase authentication |
| POST | `/api/auth/facebook` | Facebook connect |
| POST | `/api/auth/recover` | Password recovery |
| GET | `/api/auth/check-email` | Check if email exists |
| GET | `/api/auth/me` | Get current user |
| POST | `/api/auth/logout` | Logout (revoke token) |

### Profile

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/profile/{id}` | Get user profile |
| PUT | `/api/profile/update` | Update profile |
| PUT | `/api/profile/update-gender` | Update gender |
| PUT | `/api/profile/update-location` | Update location |
| PUT | `/api/profile/update-age-range` | Update age preferences |
| PUT | `/api/profile/update-radius` | Update search radius |
| DELETE | `/api/profile/delete` | Delete account |

### Photos

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/photos` | Get user photos |
| POST | `/api/photos/upload` | Upload photo |
| PUT | `/api/photos/{id}/set-main` | Set main photo |
| DELETE | `/api/photos/{id}` | Delete photo |

### Discovery

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/discovery/meet` | Get users for Meet |
| GET | `/api/discovery/game` | Get users for Game/Explore |
| POST | `/api/discovery/like` | Like/Unlike/Superlike user |
| GET | `/api/discovery/matches` | Get matches |
| GET | `/api/discovery/visitors` | Get profile visitors |
| POST | `/api/discovery/visit` | Log profile visit |
| POST | `/api/discovery/block` | Block user |

### Chat

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/chat/conversations` | Get all conversations |
| GET | `/api/chat/conversation/{userId}` | Get chat with specific user |
| POST | `/api/chat/send` | Send message |
| PUT | `/api/chat/read/{userId}` | Mark messages as read |
| DELETE | `/api/chat/conversation/{userId}` | Delete conversation |
| GET | `/api/chat/unread-count` | Get unread count |

### Stories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/stories` | Get stories feed |
| GET | `/api/stories/user/{userId}` | Get user's stories |
| POST | `/api/stories/upload` | Upload story |
| DELETE | `/api/stories/{id}` | Delete story |
| GET | `/api/stories/check/{userId}` | Check if user has stories |
| GET | `/api/stories/live-streams` | Get live streams |

### Reels

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reels` | Get reels feed |
| POST | `/api/reels/upload` | Upload reel |
| PUT | `/api/reels/{id}` | Update reel |
| DELETE | `/api/reels/{id}` | Delete reel |
| POST | `/api/reels/{id}/like` | Like reel |
| POST | `/api/reels/{id}/view` | Track view |
| POST | `/api/reels/{id}/purchase` | Purchase premium reel |

## 🔐 Authentication

All protected endpoints require a Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     http://localhost:8000/api/profile/me
```

### Getting a Token

**Login:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

**Response:**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "John Doe",
    ...
  }
}
```

## 🚦 Response Format

### Success Response
```json
{
  "user": {...},
  "data": [...],
  "message": "Success"
}
```

### Error Response
```json
{
  "error": 1,
  "error_m": "Error message description",
  "errors": {
    "field": ["Validation error"]
  }
}
```

## 🎯 Key Features

### 1. **Smart Caching**
- User data cached for 1 hour
- Query results cached
- Redis support for distributed caching

### 2. **Query Optimization**
- Eager loading for relationships
- Database indexes on frequently queried fields
- N+1 query prevention

### 3. **Security**
- Token-based authentication (Sanctum)
- Input sanitization
- SQL injection prevention (Eloquent ORM)
- CSRF protection
- Rate limiting

### 4. **Real-time Features**
- Pusher integration for live chat
- Typing indicators
- Online status
- Live notifications

### 5. **Media Handling**
- AWS S3 for storage
- AWS Rekognition for content moderation
- Image optimization
- Video transcoding

## 🔧 Configuration

### Plugin Settings

All plugin settings from the old `$sm['plugins']` are now in `.env`:

```env
# Fake Users
PLUGIN_FAKE_USERS_ENABLED=false
PLUGIN_FAKE_USERS_GENERATE=100

# Spotlight
PLUGIN_SPOTLIGHT_ENABLED=true
PLUGIN_SPOTLIGHT_LIMIT=50
PLUGIN_SPOTLIGHT_AREA=Worldwide

# Stories
PLUGIN_STORY_ENABLED=true
PLUGIN_STORY_DAYS=1
PLUGIN_STORY_REVIEW=false

# Credits
CREDITS_ENABLED=true
PRICE_SPOTLIGHT=50
PRICE_RISE_UP=100
PRICE_DISCOVER_100=75

# Chat
CHAT_BASIC_LIMIT=10
CHAT_PREMIUM_LIMIT=unlimited
```

## 📊 Database

This API uses your **existing database**. No migrations needed!

Just configure the database connection in `.env`:

```env
DB_DATABASE=dating_site
DB_USERNAME=root
DB_PASSWORD=your_password
```

## 🔄 Migration from Old API

The new Laravel API is **100% compatible** with the mobile-react frontend.

### Endpoints Mapping

| Old API | New Laravel API |
|---------|----------------|
| `/requests/api.php?action=login` | `POST /api/auth/login` |
| `/requests/api.php?action=register` | `POST /api/auth/register` |
| `/requests/api-auth.php` (Firebase) | `POST /api/auth/firebase` |
| `/requests/api.php?action=meet` | `GET /api/discovery/meet` |
| `/requests/chat.php` | `/api/chat/*` |
| `/requests/reels.php` | `/api/reels/*` |

### Authentication Changes

**Old:** Session-based (cookies)
**New:** Token-based (Sanctum)

Update your mobile-react `api.service.ts` to include the token:

```typescript
headers: {
  'Authorization': `Bearer ${token}`,
  'Content-Type': 'application/json'
}
```

## 🐛 Debugging

Enable debug mode in `.env`:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

Check logs:
```bash
tail -f storage/logs/laravel.log
```

## 🚀 Production Deployment

1. Set environment to production:
```env
APP_ENV=production
APP_DEBUG=false
```

2. Optimize:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. Set proper permissions:
```bash
chmod -R 755 storage bootstrap/cache
```

4. Point web server to `public/` directory

## 📝 To-Do

- [x] User authentication (login, register, Firebase)
- [ ] Profile controllers
- [ ] Discovery controllers
- [ ] Chat controllers
- [ ] Stories controllers
- [ ] Reels controllers
- [ ] Payment integration
- [ ] Email notifications
- [ ] Push notifications
- [ ] Unit tests
- [ ] API documentation (Swagger)

## 🤝 Support

For issues or questions, check:
- API Documentation: `/api/docs`
- Health Check: `/api/health`
- Logs: `storage/logs/laravel.log`

## 📄 License

Proprietary - All rights reserved


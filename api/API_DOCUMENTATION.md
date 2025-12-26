# Dating Site API Documentation

Base URL: `http://your-domain.com/api`

## Authentication

All authenticated endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## 🔐 Auth Endpoints

### Login
```http
POST /api/auth/login
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "profile_photo": "https://..."
  },
  "token": "1|abc123..."
}
```

---

### Register
```http
POST /api/auth/register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "gender": 1,
  "looking": 2,
  "birthday": "1990-01-15",
  "city": "New York",
  "country": "United States",
  "lat": 40.7128,
  "lng": -74.0060
}
```

**Response:**
```json
{
  "user": { ... },
  "token": "1|abc123..."
}
```

---

### Firebase Auth (Social Login)
```http
POST /api/auth/firebase
```

**Request Body:**
```json
{
  "token": "firebase_id_token",
  "email": "user@example.com",
  "name": "John Doe",
  "photo": "https://photo-url.com/photo.jpg",
  "provider": "google"
}
```

---

### Get Current User
```http
GET /api/auth/me
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "credits": 100,
    "premium": false
  }
}
```

---

### Check Email Availability
```http
GET /api/auth/check-email?email=user@example.com
```

**Response:**
```json
{
  "available": true
}
```

---

### Recover Password
```http
POST /api/auth/recover
```

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

---

### Logout
```http
POST /api/auth/logout
```
🔒 **Requires Authentication**

---

### Get Site Config
```http
GET /api/config
```

**Response:**
```json
{
  "config": { ... },
  "lang": { ... },
  "prices": {
    "spotlight": 50,
    "rise_up": 100,
    "discover_100": 75,
    "super_like": 10
  }
}
```

---

## 👤 Profile Endpoints

### Get Profile
```http
GET /api/profile/{id}
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "profile": {
    "id": 1,
    "name": "John Doe",
    "age": 28,
    "city": "New York",
    "bio": "Hello world",
    "photos": [...],
    "distance": 5.2
  }
}
```

---

### Update Profile
```http
PUT /api/profile/update
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "name": "John Doe",
  "bio": "Updated bio"
}
```

---

### Update Gender
```http
PUT /api/profile/update-gender
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "gender": 1
}
```

---

### Update Looking For
```http
PUT /api/profile/update-looking
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "looking": 2
}
```

---

### Update Location
```http
PUT /api/profile/update-location
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "lat": 40.7128,
  "lng": -74.0060,
  "city": "New York",
  "country": "United States"
}
```

---

### Update Age Range
```http
PUT /api/profile/update-age-range
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "age_from": 18,
  "age_to": 35
}
```

---

### Update Search Radius
```http
PUT /api/profile/update-radius
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "radius": 50
}
```

---

### Update Bio
```http
PUT /api/profile/update-bio
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "bio": "My new bio text"
}
```

---

### Update Extended Profile (Questions)
```http
PUT /api/profile/update-extended
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "question_id": 1,
  "answer": "My answer"
}
```

---

### Update Notification Settings
```http
PUT /api/profile/update-notification
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "type": "push_like",
  "value": true
}
```

---

### Delete Profile
```http
DELETE /api/profile/delete
```
🔒 **Requires Authentication**

---

## 🔍 Discovery Endpoints

### Get Meet Users (Browse)
```http
GET /api/discovery/meet?page=1
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "users": [
    {
      "id": 1,
      "name": "Jane",
      "age": 25,
      "photo": "https://...",
      "distance": 3.5
    }
  ],
  "has_more": true
}
```

---

### Get Game Users (Swipe Mode)
```http
GET /api/discovery/game
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "users": [...]
}
```

---

### Like User
```http
POST /api/discovery/like
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "user_id": 123,
  "type": "like"
}
```
*Type can be: `like`, `dislike`, `superlike`*

**Response:**
```json
{
  "success": true,
  "match": true,
  "match_data": { ... }
}
```

---

### Get Matches
```http
GET /api/discovery/matches
```
🔒 **Requires Authentication**

---

### Get Visitors
```http
GET /api/discovery/visitors
```
🔒 **Requires Authentication**

---

### Add Visit
```http
POST /api/discovery/visit
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "user_id": 123
}
```

---

### Block User
```http
POST /api/discovery/block
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "user_id": 123
}
```

---

## 💬 Chat Endpoints

### Get Conversations List
```http
GET /api/chat/conversations
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "conversations": [
    {
      "user_id": 123,
      "name": "Jane",
      "photo": "https://...",
      "last_message": "Hello!",
      "unread_count": 2,
      "timestamp": 1703001234
    }
  ]
}
```

---

### Get Conversation Messages
```http
GET /api/chat/conversation/{userId}?page=1
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "messages": [
    {
      "id": 1,
      "from": 123,
      "message": "Hello!",
      "type": "text",
      "timestamp": 1703001234
    }
  ],
  "has_more": true
}
```

---

### Send Message
```http
POST /api/chat/send
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "to": 123,
  "message": "Hello!",
  "type": "text"
}
```
*Type can be: `text`, `image`, `gif`, `sticker`*

---

### Mark as Read
```http
PUT /api/chat/read/{userId}
```
🔒 **Requires Authentication**

---

### Get Unread Count
```http
GET /api/chat/unread-count
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "count": 5
}
```

---

### Delete Conversation
```http
DELETE /api/chat/conversation/{userId}
```
🔒 **Requires Authentication**

---

## 📷 Photo Endpoints

### Get Photos
```http
GET /api/photos
```
🔒 **Requires Authentication**

---

### Upload Photo
```http
POST /api/photos/upload
```
🔒 **Requires Authentication**

**Request Body (multipart/form-data):**
- `photo`: File (image)

---

### Set Main Photo
```http
PUT /api/photos/{id}/set-main
```
🔒 **Requires Authentication**

---

### Delete Photo
```http
DELETE /api/photos/{id}
```
🔒 **Requires Authentication**

---

## 📖 Story Endpoints

### Get Stories
```http
GET /api/stories
```
🔒 **Requires Authentication**

---

### Get User Stories
```http
GET /api/stories/user/{userId}
```
🔒 **Requires Authentication**

---

### Check if User Has Story
```http
GET /api/stories/check/{userId}
```
🔒 **Requires Authentication**

---

### Upload Story
```http
POST /api/stories/upload
```
🔒 **Requires Authentication**

**Request Body (multipart/form-data):**
- `file`: File (image/video)

---

### Delete Story
```http
DELETE /api/stories/{id}
```
🔒 **Requires Authentication**

---

## 🎬 Reels Endpoints

### Get Reels
```http
GET /api/reels?page=1
```
🔒 **Requires Authentication**

---

### Upload Reel
```http
POST /api/reels/upload
```
🔒 **Requires Authentication**

**Request Body (multipart/form-data):**
- `video`: File (video)
- `description`: String
- `price`: Integer (0 for free)

---

### Update Reel
```http
PUT /api/reels/{id}
```
🔒 **Requires Authentication**

---

### Delete Reel
```http
DELETE /api/reels/{id}
```
🔒 **Requires Authentication**

---

### Like Reel
```http
POST /api/reels/{id}/like
```
🔒 **Requires Authentication**

---

### Add View to Reel
```http
POST /api/reels/{id}/view
```
🔒 **Requires Authentication**

---

### Purchase Reel
```http
POST /api/reels/{id}/purchase
```
🔒 **Requires Authentication**

---

## ⭐ Spotlight Endpoints

### Get Spotlight Users
```http
GET /api/spotlight
```
🔒 **Requires Authentication**

---

### Add to Spotlight
```http
POST /api/spotlight/add
```
🔒 **Requires Authentication**

*Costs credits based on config*

---

## 💳 Credits Endpoints

### Rise Up (Boost Profile)
```http
POST /api/credits/rise-up
```
🔒 **Requires Authentication**

---

### Discover Boost
```http
POST /api/credits/discover-boost
```
🔒 **Requires Authentication**

---

### Update Credits
```http
POST /api/credits/update
```
🔒 **Requires Authentication**

---

## 💰 Payment Endpoints

### Get Packages
```http
GET /api/payment/packages
```

**Response:**
```json
{
  "credits": [
    { "id": 1, "credits": 100, "price": 4.99 },
    { "id": 2, "credits": 500, "price": 19.99 }
  ],
  "premium": [
    { "id": 1, "days": 30, "price": 9.99 },
    { "id": 2, "days": 90, "price": 24.99 }
  ],
  "currency": "EUR",
  "currency_symbol": "€",
  "gateways": [
    { "id": "paypal", "name": "PayPal" }
  ]
}
```

---

### Initiate Payment
```http
POST /api/payment/initiate
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "type": "credits",
  "package_id": 1,
  "gateway": "paypal"
}
```

**Response:**
```json
{
  "order_id": "ORD-ABC123XYZ",
  "gateway": "paypal",
  "redirect_url": "https://paypal.com/...",
  "method": "redirect"
}
```

---

### Check Payment Status
```http
GET /api/payment/status/{orderId}
```
🔒 **Requires Authentication**

**Response:**
```json
{
  "order_id": "ORD-ABC123XYZ",
  "status": "success",
  "type": "credits",
  "amount": 4.99
}
```

---

### PayPal Callback
```http
GET /api/payment/callback/paypal
```
*Called by PayPal after payment*

---

### PayPal IPN
```http
POST /api/payment/ipn/paypal
```
*PayPal Instant Payment Notification webhook*

---

### Stripe Webhook
```http
POST /api/payment/webhook/stripe
```
*Stripe payment webhook*

---

## 📤 Upload Endpoints

### Upload File to S3
```http
POST /api/upload
```
🔒 **Requires Authentication**

**Request Body (multipart/form-data):**
- `file`: File (image/video)

**Response:**
```json
{
  "status": "ok",
  "path": "https://s3.../uploads/file.jpg",
  "thumb": "https://s3.../uploads/thumb_file.jpg",
  "filename": "file.jpg",
  "video": 0
}
```

---

### Delete File from S3
```http
DELETE /api/upload
```
🔒 **Requires Authentication**

**Request Body:**
```json
{
  "filename": "file.jpg"
}
```

---

## 🔗 Webhook Endpoints

### AWS Lambda Webhook
```http
POST /api/webhook/aws-lambda
```

**Request Body:**
```json
{
  "file_name": "video.mp4",
  "hls_url": "https://...",
  "moderation_scores": { ... }
}
```

---

## Error Responses

All endpoints return errors in this format:

```json
{
  "error": "Error message here"
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Rate Limiting

API requests are rate limited. Headers included in response:
- `X-RateLimit-Limit`: Max requests per minute
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Time until reset

---

## Real-time Events (Pusher)

The API uses Pusher for real-time updates. Subscribe to these channels:

**Private User Channel:** `private-user.{userId}`
- `new-message` - New chat message received
- `new-match` - New match created
- `new-like` - Someone liked you
- `new-visit` - Someone visited your profile

---

*Generated for Dating Site API v1.0*




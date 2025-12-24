<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\SpotlightController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/firebase', [AuthController::class, 'firebaseAuth']);
    Route::post('/recover', [AuthController::class, 'recoverPassword']);
    Route::post('/facebook', [AuthController::class, 'facebookConnect']);
    Route::post('/check-email', [AuthController::class, 'checkEmail']);
    Route::get('/check-email', [AuthController::class, 'checkEmail']); // Also support GET for backward compatibility
});

// Get site configuration (public or authenticated)
Route::get('/config', [AuthController::class, 'getConfig']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/complete-registration', [AuthController::class, 'completeRegistration']);
    
    // Profile Management
    Route::prefix('profile')->group(function () {
        Route::get('/{id}', [ProfileController::class, 'show']);
        Route::put('/update', [ProfileController::class, 'update']);
        Route::put('/update-field', [ProfileController::class, 'updateField']);
        Route::put('/update-gender', [ProfileController::class, 'updateGender']);
        Route::put('/update-looking', [ProfileController::class, 'updateLooking']);
        Route::put('/update-location', [ProfileController::class, 'updateLocation']);
        Route::put('/update-age-range', [ProfileController::class, 'updateAgeRange']);
        Route::put('/update-radius', [ProfileController::class, 'updateRadius']);
        Route::put('/update-language', [ProfileController::class, 'updateLanguage']);
        Route::put('/update-bio', [ProfileController::class, 'updateBio']);
        Route::put('/update-extended', [ProfileController::class, 'updateExtended']);
        Route::put('/update-notification', [ProfileController::class, 'updateNotification']);
        Route::post('/interests/add', [ProfileController::class, 'addInterest']);
        Route::post('/interests/remove', [ProfileController::class, 'removeInterest']);
        Route::post('/claim-register-reward', [ProfileController::class, 'claimRegisterReward']);
        Route::delete('/delete', [ProfileController::class, 'deleteProfile']);
    });
    
    // Photos
    Route::prefix('photos')->group(function () {
        Route::get('/', [PhotoController::class, 'index']);
        Route::post('/upload', [PhotoController::class, 'upload']);
        Route::put('/{id}/set-main', [PhotoController::class, 'setMain']);
        Route::delete('/{id}', [PhotoController::class, 'delete']);
    });
    
    // Discovery & Matching
    Route::prefix('discovery')->group(function () {
        Route::get('/meet', [DiscoveryController::class, 'getMeetUsers']);
        Route::get('/popular', [DiscoveryController::class, 'getPopularUsers']);
        Route::get('/game', [DiscoveryController::class, 'getGameUsers']);
        Route::post('/like', [DiscoveryController::class, 'likeUser']);
        Route::get('/matches', [DiscoveryController::class, 'getMatches']);
        Route::get('/visitors', [DiscoveryController::class, 'getVisitors']);
        Route::post('/visit', [DiscoveryController::class, 'addVisit']);
        Route::post('/block', [DiscoveryController::class, 'blockUser']);
    });
    
    // Spotlight
    Route::prefix('spotlight')->group(function () {
        Route::get('/', [SpotlightController::class, 'getSpotlight']);
        Route::post('/add', [SpotlightController::class, 'addToSpotlight']);
    });
    
    // Chat
    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [ChatController::class, 'getConversations']);
        Route::get('/conversation/{userId}', [ChatController::class, 'getConversation']);
        Route::post('/send', [ChatController::class, 'sendMessage']);
        Route::put('/read/{userId}', [ChatController::class, 'markAsRead']);
        Route::delete('/conversation/{userId}', [ChatController::class, 'deleteConversation']);
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
    });
    
    // Stories
    Route::prefix('stories')->group(function () {
        Route::get('/', [StoryController::class, 'getStories']);
        Route::get('/user/{userId}', [StoryController::class, 'getUserStories']);
        Route::post('/upload', [StoryController::class, 'upload']);
        Route::delete('/{id}', [StoryController::class, 'delete']);
        Route::get('/check/{userId}', [StoryController::class, 'checkHasStory']);
    });
    
    // Reels
    Route::prefix('reels')->group(function () {
        Route::get('/', [ReelController::class, 'getReels']);
        Route::post('/upload', [ReelController::class, 'upload']);
        Route::put('/{id}', [ReelController::class, 'update']);
        Route::delete('/{id}', [ReelController::class, 'delete']);
        Route::post('/{id}/like', [ReelController::class, 'like']);
        Route::post('/{id}/view', [ReelController::class, 'addView']);
        Route::post('/{id}/purchase', [ReelController::class, 'purchase']);
    });
    
    // Credits & Boost
    Route::prefix('credits')->group(function () {
        Route::post('/update', [ProfileController::class, 'updateCredits']);
        Route::post('/rise-up', [ProfileController::class, 'riseUp']);
        Route::post('/discover-boost', [ProfileController::class, 'discoverBoost']);
    });
    
    // File Upload (S3)
    Route::prefix('upload')->group(function () {
        Route::post('/', [UploadController::class, 'upload']);
        Route::delete('/', [UploadController::class, 'delete']);
    });
    
    // Payments (authenticated)
    Route::prefix('payment')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate']);
        Route::get('/status/{orderId}', [PaymentController::class, 'checkStatus']);
    });
});

// Webhooks (no auth required - external services)
Route::prefix('webhook')->group(function () {
    Route::post('/aws-lambda', [WebhookController::class, 'awsLambda']);
    Route::post('/{service}', [WebhookController::class, 'handle']);
});

// Payment callbacks (no auth - external gateway redirects)
Route::prefix('payment')->group(function () {
    Route::get('/callback/paypal', [PaymentController::class, 'paypalCallback']);
    Route::post('/ipn/paypal', [PaymentController::class, 'paypalIPN']);
    Route::post('/webhook/stripe', [PaymentController::class, 'stripeWebhook']);
    Route::get('/cancel', [PaymentController::class, 'cancelled']);
});

// Payment packages (public)
Route::get('/payment/packages', [PaymentController::class, 'getPackages']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});


<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserPremium;
use App\Models\UserVideoCall;
use App\Models\UserExtended;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * User login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !password_verify($request->password, $user->pass)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update last access
        $user->update([
            'last_access' => time(),
        ]);

        // Create token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    

    /**
     * Firebase authentication
     */
    public function firebaseAuth(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'name' => 'nullable|string',
            'photo' => 'nullable|url',
            'provider' => 'nullable|string',
            'gender' => 'nullable|in:1,2',
            'birthday' => 'nullable|date',
            'city' => 'nullable|string',
            'country' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        // Verify Firebase token
        $firebaseUser = $this->verifyFirebaseToken($request->token);
        
        if (!$firebaseUser) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Invalid Firebase token',
            ], 401);
        }

        // Check if user exists
        $user = User::where('email', $request->email)
            ->orWhere('firebase_uid', $firebaseUser['uid'])
            ->first();

        if ($user) {
            // Existing user - login
            $user->update([
                'firebase_uid' => $firebaseUser['uid'],
                'last_access' => time(),
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $this->formatUserResponse($user),
                'isNewUser' => false,
            ]);
        }

        // New user - register
        $location = $this->getLocationFromIP($request);
        
        // Generate username from email
        $username = preg_replace('/([^@]*).*/', '$1', $request->email);
        if (User::where('username', $username)->exists()) {
            $username = preg_replace('/([^@]*).*/', '$1', $request->email) . uniqid();
        }
        
        $name = $request->name ?? explode('@', $request->email)[0];       
        
        $age = $request->birthday ? now()->diffInYears($request->birthday) : 18;        
        
        // Get user IP
        $ip = $this->getUserIpAddress($request);
        
        // Get language (default to 1)
        $lang = $request->input('lang', 1);
        
        // Get referral from cookie if exists
        $referral = $request->cookie('ref', '');
        
        // Get country code from location
        $countryCode = $location['country_code'] ?? '';        
        // Set s_age default: '18,60,1'
        $sAge = '18,60,1';                
        
        $currentTime = time();
        $joinDate = date('Y-m-d');
        
        // Determine firebase_uid and google_id based on provider
        $firebaseUid = '';
        $googleId = '';
        if ($request->provider == 'google') {
            $googleId = $firebaseUser['uid'];
        } else {
            $firebaseUid = $firebaseUser['uid'];
        }

        DB::beginTransaction();
        
        try {
            $user = User::create([
                'email' => $request->email,
                'pass' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'name' => $name,
                'username' => $username,
                'firebase_uid' => $firebaseUid,
                'google_id' => $googleId,                
                'city' => $request->city ?? $location['city'],
                'country' => $request->country ?? $location['country'],
                'lat' => $request->lat ?? $location['lat'],
                'lng' => $request->lng ?? $location['lng'],
                'lang' => $lang,
                'join_date' => $joinDate,                
                'age' => 0,
                's_age' => $sAge,
                'credits' => 0,
                'online_day' => 0,
                'ip' => $ip,
                'last_access' => $currentTime,
                'join_date_time' => $currentTime,
                'referral' => $referral,
                'country_code' => $countryCode,
                'verified' => 1, // Auto-verify Firebase users
            ]);

            // Create user photo if provided (store in users_photos table, not users table)
            if (!empty($request->photo)) {
                \App\Models\UserPhoto::create([
                    'u_id' => $user->id,
                    'photo' => $request->photo,
                    'thumb' => $request->photo, // Use same photo as thumb if no separate thumb provided
                    'profile' => 1, // Mark as profile photo
                    'approved' => 1, // Auto-approve Firebase users
                    'time' => time(),
            ]);
            }

            // Create videocall record (matching original API)
            UserVideoCall::create([
                'u_id' => $user->id,
                'peer_id' => 0,
            ]);

            // Create notifications settings (just insert uid, defaults will be used)
            UserNotification::create([
                'uid' => $user->id,
            ]);

            // Create premium record (matching original API structure)
            // Check for free premium based on gender (as in original API)
            $freePremium = 0;
            $lang = $user->lang ?? 1;
            $allG = count(config('dating.genders', [1, 2])) + 1; // Total genders + 1
            
            // Check if user qualifies for free premium (matching original logic)
            $freePremiumGender = config('dating.free_premium_gender', 0);
            if ($freePremiumGender == $user->gender || $freePremiumGender == $allG) {
                $freePremium = (int) config('dating.free_premium_days', 0);
            }
            
            $premiumValue = 0;
            if ($freePremium > 0) {
                $time = time();
                $extra = 86400 * $freePremium;
                $premiumValue = $time + $extra;
            }

            UserPremium::create([
                'uid' => $user->id,
                'premium' => $premiumValue,
            ]);

            // Create extended record (matching original API)
            UserExtended::create([
                'uid' => $user->id,
            ]);

            DB::commit();

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $this->formatUserResponse($user),
                'isNewUser' => true,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 1,
                'error_m' => 'Firebase registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }
   

    /**
     * Complete user registration (update profile after Firebase registration)
     * Requires authentication token to verify user can only update their own profile
     */
    public function completeRegistration(Request $request)
    {
        // Get authenticated user first
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'gender' => 'required|in:1,2',
            'birthday' => 'required|date',
            'looking' => 'nullable|in:1,2,3',
            'photo' => 'nullable|url',
            'thumb' => 'nullable|url',
        ]);

        // Calculate age from birthday
        $birthday = \Carbon\Carbon::parse($request->birthday);
        $age = $birthday->diffInYears(now());
        
        // Format birthday as "February 07, 2001"
        $birthdayFormatted = $birthday->format('F d, Y');
        
        // Generate bio like original API: "Hi, im {name}, {age} years old and im from {city} {country}"
        $name = $user->name ?? $user->username ?? 'User';
        $city = $user->city ?? '';
        $country = $user->country ?? '';
        $bio = "Hi, im " . $name . ", " . $age . " years old and im from " . $city . " " . $country;

        // Update user profile
        $updateData = [
            'gender' => $request->gender,
            'birthday' => $birthdayFormatted,
            'age' => $age,
            'looking' => $request->looking ?? ($request->gender == 1 ? 2 : 1),
            'bio' => $bio,
        ];

        if ($request->username) {
            $updateData['username'] = $request->username;
        }

        $user->update($updateData);

        // Create/update user photo if provided (store in users_photos table, not users table)
        if ($request->photo) {
            // Remove existing profile photo flag
            \App\Models\UserPhoto::where('u_id', $user->id)
                ->where('profile', 1)
                ->update(['profile' => 0]);
            
            // Create new profile photo
            \App\Models\UserPhoto::create([
                'u_id' => $user->id,
                'photo' => $request->photo,
                'thumb' => $request->thumb ?? $request->photo, // Use thumb if provided, otherwise use photo
                'profile' => 1, // Mark as profile photo
                'approved' => 1,
                'time' => time(),
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Check if email exists
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'validEmail' => $exists ? 'No' : 'Yes',
            'validEmailMsg' => $exists ? 'Email already exists' : 'Email is available',
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUserResponse($request->user()),
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get site configuration
     * Only returns user information if token is valid
     */
    public function getConfig(Request $request)
    {
        
        $location = $this->getLocationFromIP($request);
        
        $siteLang = $request->input('lang', 1);

        // Get user IP address
        $userIp = $this->getUserIpAddress($request);

        $siteConfig = $this->getSiteConfig();
        // Add interests to config
        $siteConfig['interests'] = $this->getSiteInterests();
        
        $config = [
            'config' => $siteConfig,
            'app' => $this->getAppConfig(),
            'apikeys' => $this->getApiKeys(),
            'lang' => $this->getLanguageStrings($siteLang),
            'alang' => $this->getAppLanguageStrings($siteLang),
            'prices' => $this->getPrices(),
            'gifts' => $this->getGifts(),
            'firebase' => $this->getFirebaseConfig(),
            'pusher' => $this->getPusherConfig(),
            'userIp' => $userIp,
            'user' => '',
        ];

        // Only return user information if token is valid
        $user = $request->user();
        if ($user) {
            $config['user'] = $this->formatUserResponse($user);
        }

        return response()->json($config);
    }

    /**
     * Format user response - matches original getUserInfo structure
     */
    private function formatUserResponse(User $user): array
    {
        $first_name = explode(' ', trim($user->name));
        $first_name = explode('_', trim($first_name[0]));
        $first_name = $first_name[0];
        
        // Get profile questions if user is not fake
        $question = [];
        if ($user->fake == 0) {
            $questions = DB::table('config_profile_questions')
                ->where(function ($query) use ($user) {
                    $query->where('lang_id', $user->lang)
                        ->where(function ($q) use ($user) {
                            $q->where('gender', $user->gender)
                              ->orWhere('gender', 0);
                        });
                })
                ->orderBy('q_order', 'asc')
                ->get();
            
            foreach ($questions as $q) {
                $userAnswer = DB::table('users_profile_questions')
                    ->where('uid', $user->id)
                    ->where('qid', $q->id)
                    ->value('answer');
                
                $answers = DB::table('config_profile_answers')
                    ->where('lang_id', $user->lang)
                    ->where('qid', $q->id)
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'answer' => $a->answer,
                            'text' => $a->answer,
                        ];
                    })
                    ->toArray();
                
                $question[] = [
                    'id' => $q->id,
                    'question' => $q->question,
                    'method' => $q->method,
                    'gender' => $q->gender,
                    'q_order' => $q->q_order,
                    'userAnswer' => $userAnswer ?? '',
                    'answers' => $answers,
                ];
            }
        }
        
        // Get blocked profiles
        $blockedProfiles = DB::table('reports')
            ->where('reported_by', $user->id)
            ->orderBy('id', 'desc')
            ->pluck('id')
            ->toArray();
        
        // Calculate likes stats
        $total_likers = getUserTotalLikers($user->id);
        $total_nolikers = getUserTotalNoLikers($user->id);
        $totalLikes = $total_likers + $total_nolikers;
        $likes_percentage = getUserLikePercent($total_likers, $totalLikes);
        
        // Get s_age max value
        $sage = 60;
        if ($user->s_age) {
            $sAgeParts = explode(',', $user->s_age);
            $sage = isset($sAgeParts[1]) ? (int) $sAgeParts[1] : 60;
        }
        
        // Format username
        $username = $user->username ?: $user->name;
        
        // Format first_name based on onlyUsername setting
        $onlyUsername = config('dating.only_username', 'No');
        if ($onlyUsername === 'Yes') {
            if (empty($user->username)) {
                $first_name = (string) $user->id;
                $name = (string) $user->id;
            } else {
                $first_name = $user->username;
                $name = $user->username;
            }
        } else {
            $name = $user->name;
        }
        
        // Get premium status
        $premium = checkUserPremium($user->id);
        
        // Get photos with full details
        $photos = userAppPhotos($user->id);
        $photosFormatted = array_map(function ($photo) {
            return [
                'id' => (string) $photo['id'],
                'thumb' => $photo['thumb'] ?? $photo['photo'],
                'photo' => $photo['photo'],
                'approved' => '1',
                'profile' => (string) ($photo['profile'] ?? 0),
                'private' => (string) ($photo['private'] ?? 0),
                'blocked' => (string) ($photo['blocked'] ?? 0),
            ];
        }, $photos);
        
        // Get link (cleaned first name)
        $link = clean($first_name);
        if (empty($link)) {
            $link = 'user';
        }
        
        // Format join_date
        $join_date = $user->join_date ? date('d/m/Y', strtotime($user->join_date)) : '';
        
        return [
            'question' => $question,
            'blockedProfiles' => $blockedProfiles,
            'id' => (string) $user->id,
            'email' => $user->email,
            'pendingPayout' => '0',
            'gender' => (string) $user->gender,
            'guest' => (string) ($user->guest ?? 0),
            'bio_url' => $user->bio_url,
            'moderator' => $user->moderator ?? '',
            'subscribe' => (string) ($user->subscribe ?? 0),
            'first_name' => $first_name,
            'name' => $name,
            'profile_photo' => profilePhoto($user->id),
            'profile_photo_big' => profilePhotoBig($user->id),
            'random_photo' => randomPhoto($user->id),
            'unreadMessagesCount' => (string) checkUnreadMessages($user->id),
            'story' => '0', // TODO: Implement story count
            'stories' => '[]', // TODO: Implement stories
            'total_photos' => (string) getUserTotalPhotos($user->id),
            'total_photos_public' => (string) getUserTotalPhotosPublic($user->id),
            'total_photos_private' => (string) getUserTotalPhotosPrivate($user->id),
            'total_likers' => (string) $total_likers,
            'total_nolikers' => (string) $total_nolikers,
            'mylikes' => (string) getUserTotalLikes($user->id),
            'totalLikes' => $totalLikes,
            'likes_percentage' => $likes_percentage,
            'galleria' => getUserPhotosAllProfile($user->id),
            'total_likes' => (string) getUserTotalLikes($user->id),
            'interest' => userInterest($user->id),
            'status_info' => userFilterStatus($user->id),
            'status' => userStatus($user->id),
            'city' => $user->city ?: '',
            'email_verified' => (string) $user->verified,
            'country' => $user->country ?: '',
            'age' => (string) $user->age,
            'phone' => $user->telephone ?? '',
            'country_code' => $user->country_code ?? '',
            'lang_prefix' => getLangPrefix($user->lang ?? 1),
            'rnd_f' => getRandomFakeOnline('id', $user->looking ?? 1),
            'lat' => (string) $user->lat,
            'lng' => (string) $user->lng,
            'birthday' => $user->birthday ?? '',
            'registerReward' => getRegisterReward($user->id),
            'last_access' => (string) ($user->last_access ?? time()),
            'admin' => (string) ($user->admin ?? 0),
            'username' => $username,
            'lang' => (string) ($user->lang ?? 1),
            'language' => getLangName($user->lang ?? 1),
            'looking' => (string) ($user->looking ?? 0),
            'premium' => $premium,
            'newFans' => (string) DB::table('users_likes')->where('u2', $user->id)->where('notification', 0)->count(),
            'newVisits' => (string) DB::table('users_visits')->where('u1', $user->id)->where('notification', 0)->count(),
            'totalVisits' => (string) DB::table('users_visits')->where('u1', $user->id)->count(),
            'totalMyLikes' => (string) DB::table('users_likes')->where('u1', $user->id)->where('love', 1)->count(),
            'totalFans' => (string) DB::table('users_likes')->where('u2', $user->id)->where('love', 1)->count(),
            'totalMatches' => userMatchesCount($user->id),
            'ip' => $user->ip ?? '0',
            'premium_check' => adminCheckUserPremium($user->id),
            'verified' => (string) $user->verified,
            'popular' => (string) ($user->popular ?? 0),
            'credits' => (string) $user->credits,
            'link' => $link,
            'online' => userStatusIcon($user->id),
            'fake' => (string) ($user->fake ?? 0),
            'join_date' => $join_date,
            'bio' => $user->bio ?? '',
            'meet' => (string) ($user->meet ?? 0),
            'discover' => (string) ($user->discover ?? 0),
            's_gender' => (string) ($user->s_gender ?? ''),
            's_radius' => (string) ($user->s_radius ?? 500),
            's_age' => $user->s_age ?? '',
            'online_day' => (string) ($user->online_day ?? 0),
            'slike' => (string) getUserSuperLikes($user->id),
            'sage' => (string) $sage,
            'photos' => $photosFormatted,
            'notification' => userNotifications($user->id),
        ];
    }

    /**
     * Verify Firebase token
     * Decodes the JWT token and extracts user info
     */
    private function verifyFirebaseToken(string $token): ?array
    {
        try {
            // Decode JWT token (without verification for now - we'll verify via API)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                \Log::error('Invalid JWT token format');
                return null;
            }

            // Decode payload (second part)
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            
            if (!$payload) {
                \Log::error('Failed to decode JWT payload');
                return null;
            }

            // Verify the token is from our Firebase project
            $projectId = config('services.firebase.project_id');
            $audience = $payload['aud'] ?? null;
            
            if ($audience !== $projectId && $audience !== "special-dating-479e7") {
                \Log::error('Token audience mismatch', [
                    'expected' => $projectId,
                    'got' => $audience,
            ]);
                // Still proceed - might be using project name instead of ID
            }

            // Extract user info from token
            $uid = $payload['user_id'] ?? $payload['sub'] ?? null;
            $email = $payload['email'] ?? null;

            if (!$uid) {
                \Log::error('No user ID found in token', ['payload' => $payload]);
                return null;
            }

            // Verify token is not expired
            $exp = $payload['exp'] ?? null;
            if ($exp && $exp < time()) {
                \Log::error('Token has expired', ['exp' => $exp, 'now' => time()]);
            return null;
            }

            return [
                'uid' => $uid,
                'email' => $email,
            ];
        } catch (\Exception $e) {
            \Log::error('Firebase token verification exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Get user IP address
     */
    private function getUserIpAddress(Request $request): string
    {
        $ip = $request->ip();
        
        // Handle proxy headers
        if ($request->hasHeader('X-Forwarded-For')) {
            $ips = explode(',', $request->header('X-Forwarded-For'));
            $ip = trim($ips[0]);
        } elseif ($request->hasHeader('X-Real-Ip')) {
            $ip = $request->header('X-Real-Ip');
        }
        
        // Fallback for localhost
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            $ip = '192.196.0.1';
        }
        
        return $ip;
    }

    /**
     * Get location from IP
     */
    private function getLocationFromIP(Request $request): array
    {
        $ip = $this->getUserIpAddress($request);
        $apiKey = config('services.geolocation.api_key');

        try {
            $response = Http::get("https://api.geoapify.com/v1/ipinfo?ip={$ip}&apiKey={$apiKey}");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'city' => $data['city']['name'] ?? '',
                    'country' => $data['country']['name_native'] ?? '',
                    'country_code' => $data['country']['iso_code'] ?? '',
                    'lat' => $data['location']['latitude'] ?? 0,
                    'lng' => $data['location']['longitude'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            // Fallback to default
        }

        return [
            'city' => '',
            'country' => '',
            'country_code' => '',
            'lat' => 0,
            'lng' => 0,
        ];
    }

    private function getSiteConfig(): array
    {
        return Cache::remember('site_config', 3600, function () {
            $config = DB::table('config')->first();
            return $config ? (array) $config : [];
        });
    }

    private function getAppConfig(): array
    {
        return [
            'name' => config('app.name'),
            'logo' => env('APP_LOGO_URL', 'https://special-dating.com/medias/50538a6ca0d376c3cb8bd1c05c83b902.png'),
            'first_color' => '#ff4458',
            'second_color' => '#ff736e',
            'newAccountFreeCredit' => (int) env('NEW_ACCOUNT_FREE_CREDIT', 120),
            'minRegisterAge' => (int) env('MIN_REGISTER_AGE', 18),
            'forcePhotoUpload' => env('FORCE_PHOTO_UPLOAD', 'No'),
            'storyOnlyPremium' => env('STORY_ONLY_PREMIUM', 'No'),
            'liveEnabled' => env('LIVE_ENABLED', 'No'),
            'chatOnlyPremium' => env('CHAT_ONLY_PREMIUM', 'No'),
            'chatOnlyPremiumMessage' => env('CHAT_ONLY_PREMIUM_MESSAGE', 'Premium required'),
            'chatCreditsPerMessageEnabled' => env('CHAT_CREDITS_PER_MESSAGE_ENABLED', 'Yes'),
            'chatCreditsPerMessage' => (int) env('CHAT_CREDITS_PER_MESSAGE', 5),
            'chatCreditsPerMessageGender' => (int) env('CHAT_CREDITS_PER_MESSAGE_GENDER', 3),
            'chatViewUserCredits' => env('CHAT_VIEW_USER_CREDITS', 'No'),
            'chatSpamPrevention' => env('CHAT_SPAM_PREVENTION', 'Yes'),
            'chatTransferCreditsGiftToReceiver' => env('CHAT_TRANSFER_CREDITS_GIFT_TO_RECEIVER', 'No'),
            'meetViewOnlyPremiumOnline' => env('MEET_VIEW_ONLY_PREMIUM_ONLINE', 'No'),
            'userLikeCredits' => (int) env('USER_LIKE_CREDITS', 1),
        ];
    }

    private function getApiKeys(): array
    {
        return [
            'geolocationApiKey' => env('GEOLOCATION_API_KEY', '')
        ];
    }

    private function getLanguageStrings(int $lang): array
    {
        return Cache::remember("lang_{$lang}", 3600, function () use ($lang) {
            return DB::table('site_lang')->where('lang_id', $lang)->get()->pluck('text', 'id')->toArray();
        });
    }

    private function getAppLanguageStrings(int $lang): array
    {
        return Cache::remember("alang_{$lang}", 3600, function () use ($lang) {
            return DB::table('app_lang')->where('lang_id', $lang)->get()->pluck('text', 'id')->toArray();
        });
    }

    private function getPrices(): array
    {
        return Cache::remember('prices', 3600, function () {
            $prices = DB::table('config_prices')
                ->where('visible', 1)
                ->pluck('price', 'feature')
                ->toArray();
            
            // Convert all prices to integers, keeping original feature names as keys
            $result = [];
            foreach ($prices as $feature => $price) {
                $result[$feature] = (int) $price;
            }
            
            return $result;
        });
    }

    private function getGifts(): array
    {
        return Cache::remember('gifts', 3600, function () {
            return DB::table('gifts')->get()->toArray();
        });
    }

    private function getFirebaseConfig(): array
    {
        return [
            'apiKey' => config('services.firebase.api_key', ''),
            'authDomain' => config('services.firebase.auth_domain', ''),
            'projectId' => config('services.firebase.project_id', ''),
            'storageBucket' => config('services.firebase.storage_bucket', ''),
            'messagingSenderId' => config('services.firebase.messaging_sender_id', ''),
            'appId' => config('services.firebase.app_id', ''),
        ];
    }

    private function getPusherConfig(): array
    {
        return [
            'key' => config('broadcasting.connections.pusher.key', ''),
            'cluster' => config('broadcasting.connections.pusher.options.cluster', 'eu'),
        ];
    }

    private function getSiteInterests(): array
    {
        return Cache::remember('site_interests', 3600, function () {
            return DB::table('interest')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($interest) {
                    return [
                        'id' => $interest->id,
                        'name' => $interest->name ?? '',
                        'icon' => $interest->icon ?? '',
                        'text' => $interest->name ?? '',
                    ];
                })
                ->toArray();
        });
    }
}



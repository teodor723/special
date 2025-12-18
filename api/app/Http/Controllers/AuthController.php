<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserPremium;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

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

        if (!$user || !Hash::check($request->password, $user->password)) {
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
     * User registration
     */
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:1,2',
            'birthday' => 'nullable|date',
            'looking' => 'nullable|in:1,2,3',
            'username' => 'nullable|string|unique:users,username',
            'photo' => 'nullable|url',
            'thumb' => 'nullable|url',
            'city' => 'nullable|string',
            'country' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        // Get location if not provided
        $location = $this->getLocationFromIP($request);

        DB::beginTransaction();
        
        try {
            // Create user
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'username' => $request->username,
                'gender' => $request->gender,
                'looking' => $request->looking ?? ($request->gender == 1 ? 2 : 1),
                'birthday' => $request->birthday,
                'age' => $request->birthday ? now()->diffInYears($request->birthday) : 18,
                'city' => $request->city ?? $location['city'],
                'country' => $request->country ?? $location['country'],
                'lat' => $request->lat ?? $location['lat'],
                'lng' => $request->lng ?? $location['lng'],
                'profile_photo' => $request->photo,
                'credits' => (int) config('dating.initial_credits', 100),
                'sexy' => (int) config('dating.initial_super_likes', 5),
                'last_access' => time(),
            ]);

            // Create user photo if provided
            if ($request->photo && $request->thumb) {
                $user->photos()->create([
                    'photo' => $request->photo,
                    'thumb' => $request->thumb,
                    'profile' => 1,
                    'approved' => 1,
                    'time' => time(),
                ]);
            }

            // Create notifications settings
            UserNotification::create([
                'uid' => $user->id,
                'fan' => '1,1,1',
                'match' => '1,1,1',
                'message' => '1,1,1',
                'visit' => '1,1,1',
                'gift' => '1,1,1',
            ]);

            // Create premium record
            UserPremium::create([
                'uid' => $user->id,
                'premium' => 0,
                'days' => 0,
                'months' => 0,
                'credits' => 0,
                'time' => time(),
            ]);

            DB::commit();

            // Create token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $this->formatUserResponse($user),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 1,
                'error_m' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
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

        DB::beginTransaction();
        
        try {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'name' => $request->name ?? explode('@', $request->email)[0],
                'firebase_uid' => $firebaseUser['uid'],
                'gender' => $request->gender ?? 1,
                'looking' => $request->gender ? ($request->gender == 1 ? 2 : 1) : 2,
                'birthday' => $request->birthday,
                'age' => $request->birthday ? now()->diffInYears($request->birthday) : 18,
                'city' => $request->city ?? $location['city'],
                'country' => $request->country ?? $location['country'],
                'lat' => $request->lat ?? $location['lat'],
                'lng' => $request->lng ?? $location['lng'],
                'profile_photo' => $request->photo,
                'credits' => (int) config('dating.initial_credits', 100),
                'sexy' => (int) config('dating.initial_super_likes', 5),
                'verified' => 1, // Auto-verify Firebase users
                'last_access' => time(),
            ]);

            // Create notifications settings
            UserNotification::create([
                'uid' => $user->id,
                'fan' => '1,1,1',
                'match' => '1,1,1',
                'message' => '1,1,1',
                'visit' => '1,1,1',
                'gift' => '1,1,1',
            ]);

            UserPremium::create([
                'uid' => $user->id,
                'premium' => 0,
                'days' => 0,
                'months' => 0,
                'credits' => 0,
                'time' => time(),
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
     */
    public function getConfig(Request $request)
    {
        $userId = $request->input('user_id', -1);
        $siteLang = $request->input('lang', 1);

        $config = [
            'config' => $this->getSiteConfig(),
            'app' => $this->getAppConfig(),
            'lang' => $this->getLanguageStrings($siteLang),
            'alang' => $this->getAppLanguageStrings($siteLang),
            'prices' => $this->getPrices(),
            'gifts' => $this->getGifts(),
            'user' => '',
        ];

        if ($userId > 0) {
            $user = User::find($userId);
            if ($user) {
                $config['user'] = $this->formatUserResponse($user);
            }
        }

        return response()->json($config);
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'username' => $user->username,
            'first_name' => explode(' ', $user->name)[0],
            'age' => $user->age,
            'birthday' => $user->birthday?->format('Y-m-d'),
            'gender' => $user->gender,
            'looking' => $user->looking,
            's_gender' => $user->s_gender,
            's_age' => $user->s_age,
            's_radius' => $user->s_radius,
            'city' => $user->city,
            'country' => $user->country,
            'lat' => (float) $user->lat,
            'lng' => (float) $user->lng,
            'bio' => $user->bio,
            'profile_photo' => $user->profile_photo_url,
            'credits' => $user->credits,
            'premium' => $user->premium,
            'verified' => $user->verified,
            'lang' => $user->lang,
            'slike' => getUserSuperLikes($user->id),
            'sage' => $user->age_range['max'],
            'photos' => userAppPhotos($user->id),
            'notification' => $user->notifications?->getAttributes() ?? [],
            'is_online' => $user->is_online,
        ];
    }

    /**
     * Verify Firebase token
     */
    private function verifyFirebaseToken(string $token): ?array
    {
        try {
            $apiKey = config('services.firebase.api_key');
            $url = "https://www.googleapis.com/identitytoolkit/v3/relyingparty/getAccountInfo?key={$apiKey}";

            $response = Http::post($url, [
                'idToken' => $token,
            ]);

            if ($response->successful() && isset($response['users'][0])) {
                return [
                    'uid' => $response['users'][0]['localId'],
                    'email' => $response['users'][0]['email'] ?? null,
                ];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get location from IP
     */
    private function getLocationFromIP(Request $request): array
    {
        $ip = getUserIpAddress();
        $apiKey = config('services.geolocation.api_key');

        try {
            $response = Http::get("https://api.geoapify.com/v1/ipinfo?ip={$ip}&apiKey={$apiKey}");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'city' => $data['city']['name'] ?? '',
                    'country' => $data['country']['name_native'] ?? '',
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
            'logo' => asset('themes/default/img/logo.png'),
            'first_color' => '#ff4458',
            'second_color' => '#ff736e',
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
        return [
            'spotlight' => (int) config('dating.price_spotlight', 50),
            'rise_up' => (int) config('dating.price_rise_up', 100),
            'discover_100' => (int) config('dating.price_discover_100', 75),
            'super_like' => (int) config('dating.price_super_like', 10),
        ];
    }

    private function getGifts(): array
    {
        return Cache::remember('gifts', 3600, function () {
            return DB::table('gifts')->get()->toArray();
        });
    }
}



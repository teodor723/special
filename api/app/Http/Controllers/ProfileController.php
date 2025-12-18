<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfileQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(int $id)
    {
        $user = User::with(['photos.approved', 'profileQuestions'])
            ->findOrFail($id);

        $currentUser = auth()->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'first_name' => explode(' ', $user->name)[0],
                'age' => $user->age,
                'city' => $user->city,
                'country' => $user->country,
                'bio' => $user->bio ?: 'No bio yet',
                'profile_photo' => $user->profile_photo_url,
                'photos' => userAppPhotos($user->id),
                'videos' => userAppPhotos($user->id, 1),
                'status' => $user->is_online ? 'y' : 'n',
                'isFan' => isFan($currentUser->id, $user->id),
                'isMatch' => $user->isMatchWith($currentUser->id) ? 1 : 0,
                'isBlocked' => blockedUser($currentUser->id, $user->id),
                'premium' => $user->premium,
                'verified' => $user->verified,
                'distance' => $this->calculateUserDistance($currentUser, $user),
            ],
        ]);
    }

    /**
     * Update profile field
     */
    public function updateField(Request $request)
    {
        $request->validate([
            'field' => 'required|string',
            'value' => 'required',
        ]);

        $user = $request->user();
        $field = $request->field;
        $value = $request->value;

        // Validate allowed fields
        $allowedFields = ['name', 'username', 'bio', 'city', 'country'];
        
        if (!in_array($field, $allowedFields)) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Invalid field',
            ], 400);
        }

        // Special handling for bio
        if ($field === 'bio') {
            $value = nl2br($value);
        }

        // Check username uniqueness
        if ($field === 'username') {
            $exists = User::where('username', $value)
                ->where('id', '!=', $user->id)
                ->exists();
            
            if ($exists) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Username already taken',
                ], 422);
            }
        }

        $user->update([$field => $value]);
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update user gender
     */
    public function updateGender(Request $request)
    {
        $request->validate([
            'gender' => 'required|in:1,2',
        ]);

        $user = $request->user();
        $user->update(['gender' => $request->gender]);
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update looking for preference
     */
    public function updateLooking(Request $request)
    {
        $request->validate([
            'looking' => 'required|in:1,2,3',
        ]);

        $user = $request->user();
        $user->update([
            'looking' => $request->looking,
            's_gender' => $request->looking,
        ]);
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update location
     */
    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'city' => 'required|string',
            'country' => 'required|string',
        ]);

        $user = $request->user();
        $user->update([
            'lat' => $request->lat,
            'lng' => $request->lng,
            'city' => $request->city,
            'country' => $request->country,
        ]);
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update age range preference
     */
    public function updateAgeRange(Request $request)
    {
        $request->validate([
            'min_age' => 'required|integer|min:18|max:100',
            'max_age' => 'required|integer|min:18|max:100',
        ]);

        if ($request->min_age > $request->max_age) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Min age cannot be greater than max age',
            ], 422);
        }

        $user = $request->user();
        $user->update([
            's_age' => "{$request->min_age},{$request->max_age},1",
        ]);

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update search radius
     */
    public function updateRadius(Request $request)
    {
        $request->validate([
            'radius' => 'required|integer|min:1|max:500',
        ]);

        $user = $request->user();
        $user->update(['s_radius' => $request->radius]);

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update language
     */
    public function updateLanguage(Request $request)
    {
        $request->validate([
            'lang' => 'required|integer',
        ]);

        $user = $request->user();
        $user->update(['lang' => $request->lang]);
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Update bio
     */
    public function updateBio(Request $request)
    {
        $request->validate([
            'bio' => 'required|string|max:500',
            'bio_url' => 'nullable|url',
        ]);

        $user = $request->user();
        $bio = strip_tags($request->bio);
        
        $user->update([
            'bio' => nl2br($bio),
            'bio_url' => $request->bio_url,
        ]);
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'bio' => $user->bio,
        ]);
    }

    /**
     * Update extended profile (questions)
     */
    public function updateExtended(Request $request)
    {
        $request->validate([
            'question_id' => 'required|integer',
            'answer' => 'required|string',
        ]);

        $user = $request->user();

        UserProfileQuestion::updateOrCreate(
            [
                'uid' => $user->id,
                'qid' => $request->question_id,
            ],
            [
                'answer' => $request->answer,
            ]
        );

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotification(Request $request)
    {
        $request->validate([
            'type' => 'required|in:fan,match,message,visit,gift',
            'value' => 'required|in:0,1',
        ]);

        $user = $request->user();
        $notification = $user->notifications;

        if (!$notification) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Notification settings not found',
            ], 404);
        }

        // Format: "inapp,email,push" -> "1,1,1"
        $currentValue = $notification->{$request->type} ?? '1,1,1';
        $parts = explode(',', $currentValue);
        $parts[2] = $request->value; // Update push notification setting
        
        $notification->update([
            $request->type => implode(',', $parts),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Delete profile
     */
    public function deleteProfile(Request $request)
    {
        $user = $request->user();

        if ($user->admin) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Cannot delete admin account',
            ], 403);
        }

        DB::beginTransaction();
        
        try {
            // Delete related records
            $user->photos()->delete();
            $user->likes()->delete();
            $user->likedBy()->delete();
            $user->visits()->delete();
            $user->visitors()->delete();
            $user->sentMessages()->delete();
            $user->receivedMessages()->delete();
            $user->stories()->delete();
            $user->reels()->delete();
            $user->notifications()->delete();
            $user->premiumSubscription()->delete();
            $user->profileQuestions()->delete();
            
            // Delete user
            $user->delete();

            // Clear cache
            Cache::forget("user_profile_{$user->id}");
            Cache::forget("user_photo_{$user->id}");

            // Log activity
            logActivity('system', ['uid' => $user->id], "User {$user->id} deleted profile");

            DB::commit();

            // Revoke all tokens
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Profile deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 1,
                'error_m' => 'Failed to delete profile',
            ], 500);
        }
    }

    /**
     * Update credits
     */
    public function updateCredits(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer',
            'type' => 'required|in:1,2,reward',
            'reason' => 'nullable|string',
        ]);

        $user = $request->user();
        $amount = $request->amount;
        $type = $request->type;
        $reason = $request->reason ?? 'Credits updated';

        if ($type == 1) {
            // Deduct credits
            if (!$user->deductCredits($amount, $reason)) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Insufficient credits',
                ], 422);
            }
        } elseif ($type == 2 || $type == 'reward') {
            // Add credits
            $user->addCredits($amount, $reason);
        }

        return response()->json([
            'success' => true,
            'credits' => $user->fresh()->credits,
        ]);
    }

    /**
     * Rise up (boost profile visibility)
     */
    public function riseUp(Request $request)
    {
        $user = $request->user();
        $cost = (int) config('dating.price_rise_up', 100);

        if (!$user->deductCredits($cost, 'Rise Up boost')) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Insufficient credits',
            ], 422);
        }

        // Boost for 5 days
        $boostUntil = now()->addDays(5)->timestamp;
        $user->update([
            'last_access' => $boostUntil,
            'meet' => 1,
        ]);

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Discover 100 boost
     */
    public function discoverBoost(Request $request)
    {
        $user = $request->user();
        $cost = (int) config('dating.price_discover_100', 75);

        if (!$user->deductCredits($cost, 'Discover 100 boost')) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Insufficient credits',
            ], 422);
        }

        // Boost for 5 days
        $boostUntil = now()->addDays(5)->timestamp;
        $user->update([
            'last_access' => $boostUntil,
            'meet' => 1,
        ]);

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'first_name' => explode(' ', $user->name)[0],
            'age' => $user->age,
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
        ];
    }

    /**
     * Calculate distance between users
     */
    private function calculateUserDistance(User $user1, User $user2): ?float
    {
        if (!$user1->lat || !$user1->lng || !$user2->lat || !$user2->lng) {
            return null;
        }

        return calculateDistance(
            (float) $user1->lat,
            (float) $user1->lng,
            (float) $user2->lat,
            (float) $user2->lng
        );
    }
}



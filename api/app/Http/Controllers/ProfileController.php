<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\FormatsUserResponse;
use App\Models\User;
use App\Models\UserProfileQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller
{
    use FormatsUserResponse;
    /**
     * Get user profile
     * Returns the same format as /auth/me for consistency
     */
    public function show(int $id)
    {
        $user = User::with(['profileQuestions'])
            ->findOrFail($id);

        $currentUser = auth()->user();

        // Use the same formatUserResponse method as /auth/me
        $userData = $this->formatUserResponse($user);
        
        // Add additional fields specific to viewing other users' profiles
        $userData['isFan'] = isFan($currentUser->id, $user->id);
        $userData['isMatch'] = $user->isMatchWith($currentUser->id) ? 1 : 0;
        $userData['isBlocked'] = blockedUser($currentUser->id, $user->id);
        $userData['distance'] = $this->calculateUserDistance($currentUser, $user);

        return response()->json([
            'user' => $userData,
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
        $allowedFields = ['name', 'username', 'bio', 'city', 'country', 'age', 'email'];
        
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

        // Check email uniqueness
        if ($field === 'email') {
            $exists = User::where('email', $value)
                ->where('id', '!=', $user->id)
                ->exists();
            
            if ($exists) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Email already taken',
                ], 422);
            }
        }

        // Validate age
        if ($field === 'age') {
            $age = (int) $value;
            if ($age < 18 || $age > 100) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Age must be between 18 and 100',
                ], 422);
            }
            $value = $age;
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
            'radius' => 'required|integer|min:1|max:1000',
        ]);

        $user = $request->user();
        // Use the actual database column name 's_radious' (database typo)
        $user->update(['s_radious' => $request->radius]);

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
            'bio' => $bio,
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

        // Use DB::table() directly since table has composite key (uid, qid) without id column
        $exists = DB::table('users_profile_questions')
            ->where('uid', $user->id)
            ->where('qid', $request->question_id)
            ->exists();

        if ($exists) {
            // Update existing answer - use where()->update() to avoid id column lookup
            DB::table('users_profile_questions')
                ->where('uid', $user->id)
                ->where('qid', $request->question_id)
                ->update(['answer' => $request->answer]);
        } else {
            // Create new answer record
            DB::table('users_profile_questions')->insert([
                'uid' => $user->id,
                'qid' => $request->question_id,
                'answer' => $request->answer,
            ]);
        }

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Add interest
     */
    public function addInterest(Request $request)
    {
        $request->validate([
            'interest_id' => 'required|integer',
        ]);

        $user = $request->user();
        $interestId = $request->interest_id;

        // Check if interest already exists
        $exists = DB::table('users_interest')
            ->where('u_id', $user->id)
            ->where('i_id', $interestId)
            ->exists();

        if (!$exists) {
            DB::table('users_interest')->insert([
                'u_id' => $user->id,
                'i_id' => $interestId,
            ]);
        }

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Remove interest
     */
    public function removeInterest(Request $request)
    {
        $request->validate([
            'interest_id' => 'required|integer',
        ]);

        $user = $request->user();
        $interestId = $request->interest_id;

        DB::table('users_interest')
            ->where('u_id', $user->id)
            ->where('i_id', $interestId)
            ->delete();

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
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
            // Create notification record if it doesn't exist
            $notification = \App\Models\UserNotification::create([
                'uid' => $user->id,
                'fan' => '1,1,1',
                'match_me' => '1,1,1',
                'message' => '1,1,1',
                'visit' => '1,1,1',
                'near_me' => '1,1,1',
            ]);
        }

        // Map 'match' to 'match_me' for backward compatibility
        $column = ($request->type === 'match') ? 'match_me' : $request->type;

        // Format: "inapp,email,push" -> "1,1,1"
        $currentValue = $notification->{$column} ?? '1,1,1';
        $parts = explode(',', $currentValue);
        if (count($parts) < 3) {
            $parts = ['1', '1', '1'];
        }
        $parts[2] = $request->value; // Update push notification setting (index 2 = push)
        
        // Update using where clause to avoid primary key issues
        \App\Models\UserNotification::where('uid', $user->id)
            ->update([
                $column => implode(',', $parts),
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
            //$user->addCredits($amount, $reason);
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

    /**
     * Claim register reward (newAccountFreeCredit)
     * User can only claim this reward once
     */
    public function claimRegisterReward(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Unauthorized',
            ], 401);
        }

        // Check if user already claimed the reward
        $existingReward = DB::table('users_rewards')
            ->where('uid', $user->id)
            ->where('reward', 'newAccountFreeCredit')
            ->first();

        if ($existingReward) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Reward already claimed',
            ], 400);
        }

        // Get reward amount from config
        $rewardAmount = (int) env('NEW_ACCOUNT_FREE_CREDIT', 120);
        
        if ($rewardAmount <= 0) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Reward not available',
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            $time = time();
            $reason = 'Reward credits for register';
            $reward = 'newAccountFreeCredit';

            // Add credits to user
            $user->increment('credits', $rewardAmount);
            $user->refresh();

            // Record in users_rewards table
            DB::table('users_rewards')->insert([
                'uid' => $user->id,
                'reward' => $reward,
                'reward_type' => 'credits',
                'reward_date' => $time,
                'reward_amount' => $rewardAmount,
            ]);

            // Record in users_credits table
            DB::table('users_credits')->insert([
                'uid' => $user->id,
                'credits' => $rewardAmount,
                'reason' => $reason,
                'time' => $time,
                'type' => 'added',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'credits_added' => $rewardAmount,
                'credits_remaining' => $user->credits,
                'user' => [
                    'id' => $user->id,
                    'credits' => $user->credits,
                    'registerReward' => 'newAccountFreeCredit',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 1,
                'error_m' => 'Failed to claim reward: ' . $e->getMessage(),
            ], 500);
        }
    }
}



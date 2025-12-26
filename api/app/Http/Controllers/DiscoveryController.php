<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLike;
use App\Models\UserVisit;
use App\Models\UserBlock;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Pusher\Pusher;
use GuzzleHttp\Client;

class DiscoveryController extends Controller
{
    /**
     * Get users for Meet page
     */
    public function getMeetUsers(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get parameters: offset, online
        $limit = (int) ($request->input('offset', 0)); // Page/offset
        $onlineStatus = (int) ($request->input('online', 0)); // 0=all, 1=online only

        $check2 = 10;
        $searchResult = $check2;
        
        // Get age range
        $ageRange = $user->age_range;
        $radius = $user->s_radius;
        $lookingFor = $user->s_gender;
        $allGenders = count(config('dating.genders', [1, 2])) + 1;

        // Build query for main results
        $query = User::query()
            ->where('id', '!=', $user->id)
            ->byAgeRange($ageRange['min'], $ageRange['max'])
            ->nearby($user->lat, $user->lng, $radius);

        // Filter by gender
        if ($lookingFor != $allGenders) {
            $query->byGender($lookingFor);
        }

        // Filter online status
        $timeNow = time() - 300;
        $today = date('w');
        if ($onlineStatus == 1) {
            // Online only: last_access >= time_now OR (fake=1 AND online_day=today)
            $query->where(function ($q) use ($timeNow, $today, $ageRange) {
                $q->where('last_access', '>=', $timeNow)
                  ->orWhere(function ($q2) use ($today, $ageRange, $lookingFor, $allGenders) {
                      $q2->where('fake', 1)
                         ->where('online_day', $today)
                         ->whereBetween('age', [$ageRange['min'], $ageRange['max']]);
                      if ($lookingFor != $allGenders) {
                          $q2->where('gender', $lookingFor);
                      }
                  });
            });
        }

        // Country filter (if radius < 950)
        if ($radius < 950 && !empty($user->country)) {
            $query->where('country', $user->country);
        }

        // Exclude blocked users
        $blockedIds = UserBlock::where('uid1', $user->id)
            ->pluck('uid2')
            ->toArray();
        
        if (!empty($blockedIds)) {
            $query->whereNotIn('id', $blockedIds);
        }

        // Order by random, then fake ASC (matching legacy)
        //$query->inRandomOrder()->orderBy('fake', 'asc');

        // Get users (limit based on page)
        $limitCount = $limit * $check2;
        $query->orderBy('distance', 'desc');
        $query->orderBy('name', 'asc');
        $users = $query->limit($check2)->offset($limitCount)->get();

        // Format results
        $result = [];
        $i = 0;
        foreach ($users as $u) {
            $i++;
            $result[] = $this->formatMeetUser($u, $user, $i, $timeNow);
        }

        // Calculate pages
        $totalUsers = $query->count();
        $meetResult = max(0, $totalUsers - $limitCount);
        $pages = $meetResult >= 1 ? max(0, floor($meetResult / $searchResult) - 1) : 0;

        return response()->json([
            'result' => !empty($result) ? $result : '',
            'pages' => $pages,
        ]);
    }

    /**
     * Get popular users for Meet page
     */
    public function getPopularUsers(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get pagination parameters
        $offset = (int) ($request->input('offset', 0));
        $searchResult = (int) config('dating.plugin_populars_search_result', 10);
        $limit = $searchResult;
        
        $allGenders = count(config('dating.genders', [1, 2])) + 1;
        $lookingFor = $user->s_gender;
        
        $query = User::query()
            ->where('id', '!=', $user->id)
            ->orderBy('popular', 'desc')
            ->orderBy('last_access', 'desc');

        // Filter by gender based on plugin settings
        $popularSearchFilterGender = config('dating.plugin_populars_popular_search_filter_gender', 'By User Criteria');
        if ($popularSearchFilterGender === 'By User Criteria') {
            if ($lookingFor != $allGenders) {
                $query->where('gender', $lookingFor);
            }
        } else {
            if ($popularSearchFilterGender != $allGenders) {
                $query->where('gender', $popularSearchFilterGender);
            }
        }

        // Filter by location
        $popularSearchFilter = config('dating.plugin_populars_popular_search_filter', 'Worldwide');
        if ($popularSearchFilter === 'Country') {
            $query->where('country', $user->country);
        } elseif ($popularSearchFilter === 'City') {
            $query->where('city', $user->city);
        }

        // Exclude blocked users
        $blockedIds = UserBlock::where('uid1', $user->id)
            ->pluck('uid2')
            ->toArray();
        
        if (!empty($blockedIds)) {
            $query->whereNotIn('id', $blockedIds);
        }

        // Get total count for pagination
        $totalUsers = $query->count();
        
        // Get users with pagination
        $users = $query->limit($limit)->offset($offset * $limit)->get();
        
        $popular = [];
        $x = 0;
        $timeNow = time() - 300;
        $viewOnlyPremium = config('dating.plugin_populars_view_only_premium', 'No') === 'Yes';
        
        foreach ($users as $u) {
            $allowed = true;
            if ($viewOnlyPremium && !$user->premium) {
                $allowed = false;
            }
            
            $popular[] = $this->formatMeetUser($u, $user, $x, $timeNow, $allowed);
            $x++;
        }

        // Calculate if there are more pages
        $hasMore = ($offset + 1) * $limit < $totalUsers;

        return response()->json([
            'popular' => $popular,
            'hasMore' => $hasMore,
            'total' => $totalUsers,
        ]);
    }


    /**
     * Format meet user - matches legacy format exactly
     */
    private function formatMeetUser(User $u, User $currentUser, int $index, int $timeNow, ?bool $allowedOverride = null): array
    {
        // Format name based on onlyUsername setting
        $first_name = explode(' ', trim($u->name));
        $first_name = $first_name[0];
        
        $onlyUsername = config('dating.only_username', 'No');
        if ($onlyUsername === 'Yes') {
            if (empty($u->username)) {
                $first_name = (string) $u->id;
                $name = (string) $u->id;
            } else {
                $first_name = $u->username;
                $name = $u->username;
            }
        } else {
            $name = $u->name;
        }

        // Determine city
        $city = !empty($u->city) ? $u->city : $u->country;

        // Check online status
        $on = ($u->last_access >= $timeNow || $u->fake == 1) ? 1 : 0;

        // Check match
        $match = ($this->checkMatch($currentUser->id, $u->id)) ? 1 : 0;

        // Check if allowed (premium check for online users)
        $allowed = $allowedOverride;
        if ($allowed === null) {
            $allowed = true;
            $viewOnlyPremiumOnline = config('dating.plugin_meet_view_only_premium_online', 'No') === 'Yes';
            if ($on != 0 && $viewOnlyPremiumOnline && !$currentUser->premium) {
                $allowed = false;
            }
        }

        // Margin logic (matching legacy: 2, 5, 8, 11, 14, 17, 20, 23)
        $margin = (in_array($index, [2, 5, 8, 11, 14, 17, 20, 23])) ? 'search-margin' : 'search-no-margin';

        return [
            'id' => $u->id,
            'name' => $name,
            'firstName' => $first_name,
            'age' => $u->age,
            'gender' => (string) $u->gender,
            'city' => $city,
            'distance' => $u->distance,
            'photo' => profilePhoto($u->id, 0), // Thumb
            'photoBig' => profilePhoto($u->id, 1), // Big photo
            'error' => 0,
            'show' => $index,
            'status' => $on,
            'allowed' => $allowed,
            'blocked' => blockedUser($currentUser->id, $u->id),
            'total_photos' => count(userAppPhotos($u->id)),
            'margin' => $margin,
            'story' => '0',
            'stories' => [],
            'fan' => isFan($currentUser->id, $u->id),
            'match' => $match,
        ];
    }

    /**
     * Get users for Game/Explore (swipe mode)
     */
    public function getGameUsers(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get age range
        $ageRange = $user->age_range;
        $lookingFor = $user->s_gender;

        // Get users already liked
        $alreadyLiked = UserLike::where('u1', $user->id)
            ->pluck('u2')
            ->toArray();

        // Build query - get up to 50 users first (matching legacy behavior)
        $query = User::query()
            ->where('id', '!=', $user->id)
            ->byAgeRange($ageRange['min'], $ageRange['max'])
            ->nearby($user->lat, $user->lng,$user->s_radius);
        
        // Filter by gender
        $allGenders = count(config('dating.genders', [1, 2])) + 1;
        if ($lookingFor != $allGenders) {
            $query->byGender($lookingFor);
        }

        // Order by distance ASC, then last_access DESC (matching legacy)
        $query->orderBy('last_access', 'desc');

        // Get up to 50 users first
        $users = $query->limit(50)->get();

        // Filter out users without photos and already liked users (matching legacy logic)
        $filteredUsers = $users->filter(function ($u) use ($alreadyLiked) {
            // Check if user has a profile photo (not the default no-user image)
            $photo = profilePhoto($u->id);
            $hasPhoto = !str_contains($photo, 'themes') && !str_contains($photo, 'no-user');
            
            // Exclude if no photo or already liked
            return $hasPhoto && !in_array($u->id, $alreadyLiked);
        })->take(30); // Take max 30 users

        if ($filteredUsers->isEmpty()) {
            return response()->json([
                'game' => 'error',
            ]);
        }

        $result = $filteredUsers->map(function ($u) use ($user) {
            return $this->formatGameUser($u, $user);
        })->values(); // Reindex to get proper array instead of object with numeric keys

        return response()->json([
            'game' => $result,
        ]);
    }

    /**
     * Like/Unlike/Superlike a user
     */
    public function likeUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'action' => 'nullable|in:like,dislike,superlike', // New format
            'type' => 'nullable|in:0,1,3', // Legacy format: 0=unlike, 1=like, 3=superlike
        ]);

        // Support both new format (action) and legacy format (type)
        $action = $request->input('action');
        $type = $request->input('type');
        
        // Map legacy type to action
        if ($type !== null) {
            $actionMap = [0 => 'dislike', 1 => 'like', 3 => 'superlike'];
            $action = $actionMap[$type] ?? 'like';
        } elseif (!$action) {
            $action = 'like'; // Default
        }

        $currentUser = $request->user();
        $targetUserId = $request->user_id;

        $currentUser->updateLastAccess();

        // Check if superlike and has enough
        if ($action === 'superlike') {
            $superLikes = $currentUser->superlike ?? 0;
            if ($superLikes <= 0) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'No super likes available',
                ], 422);
            }
            
            $currentUser->decrement('superlike');
        }

        // Check credits for like action (not for dislike)        
        $likeCreditsCost = config('dating.price_user_like', 1);
        $creditsDeducted = 0;
        
        if ($action === 'like' && $likeCreditsCost > 0) {
            // Refresh user to get latest credits
            $currentUser->refresh();
            
            if (!$currentUser->hasEnoughCredits($likeCreditsCost)) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Not enough credits',
                    'credits_required' => $likeCreditsCost,
                    'credits_available' => $currentUser->credits,
                    'show_credits_modal' => true,
                ], 422);
            }
            
            // Deduct credits and log to users_credits table
            $deducted = $currentUser->deductCredits(
                $likeCreditsCost, 
                'Credits for like',
                'spend'
            );
            
            if (!$deducted) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Failed to deduct credits',
                    'show_credits_modal' => true,
                ], 422);
            }
            
            $creditsDeducted = $likeCreditsCost;
        }

        // Map action to love value
        $loveMap = ['dislike' => 0, 'like' => 1, 'superlike' => 3];
        $love = $loveMap[$action] ?? 1;
        
        // Create or update like
        $time = ($action === 'superlike') ? time() + 288000 : time(); // Superlike gets priority
        
        UserLike::updateOrCreate(
            [
                'u1' => $currentUser->id,
                'u2' => $targetUserId,
            ],
            [
                'love' => $love,
                'time' => $time,
            ]
        );

        // Update popular count
        if ($action === 'like' || $action === 'superlike') {
            User::where('id', $targetUserId)->increment('popular');
        }

        // Check if notification should be sent
        // Pass $love (int) instead of $action (string) to match method signature
        $this->sendLikeNotification($currentUser, $targetUserId, $love);

        // Check if it's a match
        $isMatch = $this->checkMatch($currentUser->id, $targetUserId);

        if ($isMatch) {
            $this->sendMatchNotification($currentUser, $targetUserId);
        }

        // Refresh user to get updated credits
        $currentUser->refresh();

        return response()->json([
            'success' => true,
            'is_match' => $isMatch ? 1 : 0,
            'credits_deducted' => $creditsDeducted,
            'credits_remaining' => $currentUser->credits,
            'user' => [
                'id' => $currentUser->id,
                'credits' => $currentUser->credits,
            ],
        ]);
    }

    /**
     * Get matches
     */
    public function getMatches(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get pagination parameters
        $offset = (int) ($request->input('offset', 0));
        $limit = 20; // Items per page
        $offsetCount = $offset * $limit;

        // Get users who liked this user (fans)
        $fans = UserLike::where('u2', $user->id)
            ->where('love', 1)
            ->pluck('u1')
            ->toArray();

        if (empty($fans)) {
            return response()->json([
                'matches' => [],
                'hasMore' => false,
            ]);
        }

        // Get users this user also liked (mutual matches) with pagination
        $likesQuery = UserLike::where('u1', $user->id)
            ->whereIn('u2', $fans)
            ->where('love', 1)
            ->orderBy('time', 'desc');

        $totalCount = $likesQuery->count();
        $likes = $likesQuery->limit($limit)->offset($offsetCount)->get();

        $matches = [];

        foreach ($likes as $like) {
            $targetUser = User::find($like->u2);            
            if (!$targetUser) continue;
            
            $matches[] = [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'first_name' => explode(' ', $targetUser->name)[0],
                'age' => $targetUser->age,
                'city' => $targetUser->city,
                'photo' => profilePhoto($targetUser->id),                
                'premium' => $targetUser->premium,
                'verified' => $targetUser->verified,
                'status' => $targetUser->is_online ? 'y' : 'n',
                'story' => 0, // TODO: Check stories
                'last_m' => getTimeDifference($like->time),
                'credits' => $targetUser->credits,
                'unreadCount' => $this->getUnreadCount($user->id, $targetUser->id),
            ];
        }

        $hasMore = ($offsetCount + $limit) < $totalCount;

        return response()->json([
            'matches' => $matches,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * Get users who liked me (fans)
     */
    public function likeme(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get pagination parameters
        $offset = (int) ($request->input('offset', 0));
        $limit = 20; // Items per page
        $offsetCount = $offset * $limit;

        // Get users who liked this user (fans), ordered by time desc with pagination
        $likesQuery = UserLike::where('u2', $user->id)
            ->where('love', 1)
            ->orderBy('time', 'desc');

        $totalCount = $likesQuery->count();
        $likes = $likesQuery->limit($limit)->offset($offsetCount)->get();

        $fans = [];

        foreach ($likes as $like) {
            $targetUser = User::find($like->u1);
            
            if (!$targetUser) continue;
            
            $fans[] = [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'first_name' => explode(' ', $targetUser->name)[0],
                'age' => $targetUser->age,
                'city' => $targetUser->city,
                'photo' => profilePhoto($targetUser->id),                
                'premium' => $targetUser->premium,
                'verified' => $targetUser->verified,
                'status' => $targetUser->is_online ? 'y' : 'n',
                'story' => 0, // TODO: Check stories
                'last_m' => getTimeDifference($like->time),
                'credits' => $targetUser->credits,
                'unreadCount' => $this->getUnreadCount($user->id, $targetUser->id),
            ];
        }

        $hasMore = ($offsetCount + $limit) < $totalCount;

        return response()->json([
            'matches' => $fans,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * Get users I liked
     */
    public function myLike(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get pagination parameters
        $offset = (int) ($request->input('offset', 0));
        $limit = 10; // Items per page
        $offsetCount = $offset * $limit;

        // Get users this user has liked, ordered by time desc with pagination
        $likesQuery = UserLike::where('u1', $user->id)
            ->where('love', 1)
            ->orderBy('time', 'desc');

        $totalCount = $likesQuery->count();
        $likes = $likesQuery->limit($limit)->offset($offsetCount)->get();

        $myLikes = [];
        foreach ($likes as $like) {
            $targetUser = User::find($like->u2);            
            if (!$targetUser) continue;
            
            $myLikes[] = [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'first_name' => explode(' ', $targetUser->name)[0],
                'age' => $targetUser->age,
                'city' => $targetUser->city,
                'photo' => profilePhoto($targetUser->id),            
                'premium' => $targetUser->premium,
                'verified' => $targetUser->verified,
                'status' => $targetUser->is_online ? 'y' : 'n',
                'story' => 0, // TODO: Check stories
                'last_m' => getTimeDifference($like->time),
                'credits' => $targetUser->credits,
                'unreadCount' => $this->getUnreadCount($user->id, $targetUser->id),
            ];
        }

        $hasMore = ($offsetCount + $limit) < $totalCount;

        return response()->json([
            'matches' => $myLikes,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * Get profile visitors
     */
    public function getVisitors(Request $request)
    {
        $user = $request->user();

        // Get pagination parameters
        $offset = (int) ($request->input('offset', 0));
        $limit = 20; // Items per page
        $offsetCount = $offset * $limit;

        $visitsQuery = UserVisit::where('u1', $user->id)
            ->where('u2', '!=', $user->id)
            ->orderBy('timeago', 'desc');

        $totalCount = $visitsQuery->count();
        $visits = $visitsQuery->limit($limit)->offset($offsetCount)->get();

        $visitors = [];

        foreach ($visits as $visit) {
            $visitor = User::find($visit->u2);            
            if (!$visitor) continue;

            $visitors[] = [
                'id' => $visitor->id,
                'name' => $visitor->name,
                'first_name' => explode(' ', $visitor->name)[0],
                'age' => $visitor->age,
                'city' => $visitor->city,
                'photo' => profilePhoto($visitor->id),                
                'premium' => $visitor->premium,
                'status' => $visitor->is_online ? 'y' : 'n',
                'last_m' => getTimeDifference($visit->timeago) . ' ago',
                'last_m_time' => getTimeDifference($visit->timeago),
                'credits' => $visitor->credits,
                'unreadCount' => $this->getUnreadCount($user->id, $visitor->id),
            ];
        }

        $hasMore = ($offsetCount + $limit) < $totalCount;

        return response()->json([
            'matches' => $visitors,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * Add profile visit
     */
    public function addVisit(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $currentUser = $request->user();
        $targetUserId = $request->user_id;
        $time = time();

        // Get old visit time
        $oldTime = UserVisit::where('u1', $targetUserId)
            ->where('u2', $currentUser->id)
            ->value('timeago');

        // Create or update visit using DB::table() since table has composite key without id column
        $exists = DB::table('users_visits')
            ->where('u1', $targetUserId)
            ->where('u2', $currentUser->id)
            ->exists();

        if ($exists) {
            DB::table('users_visits')
                ->where('u1', $targetUserId)
                ->where('u2', $currentUser->id)
                ->update(['timeago' => $time]);
        } else {
            DB::table('users_visits')->insert([
                'u1' => $targetUserId,
                'u2' => $currentUser->id,
                'timeago' => $time,
            ]);
        }

        // Send notification if timeout passed
        $timeout = config('dating.plugin_fake_users_notification_timeout', 30) * 60;
        $shouldNotify = !$oldTime || ($oldTime + $timeout < $time);

        if ($shouldNotify) {
            $this->sendVisitNotification($currentUser, $targetUserId);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Block user
     */
    public function blockUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        $currentUser = $request->user();
        $targetUserId = $request->user_id;
        $reason = $request->reason ?? 'No reason provided';
        $time = time();

        DB::beginTransaction();
        
        try {
            // Block user
            UserBlock::create([
                'uid1' => $currentUser->id,
                'uid2' => $targetUserId,
            ]);

            // Delete chat messages
            DB::table('chat')
                ->where(function ($query) use ($currentUser, $targetUserId) {
                    $query->where('s_id', $currentUser->id)->where('r_id', $targetUserId);
                })
                ->orWhere(function ($query) use ($currentUser, $targetUserId) {
                    $query->where('s_id', $targetUserId)->where('r_id', $currentUser->id);
                })
                ->delete();

            // Remove likes
            UserLike::where('u1', $currentUser->id)
                ->where('u2', $targetUserId)
                ->update(['love' => 0]);

            // Report user
            DB::table('reports')->insert([
                'reported' => $targetUserId,
                'reported_by' => $currentUser->id,
                'reported_date' => $time,
                'reason' => $reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User blocked successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 1,
                'error_m' => 'Failed to block user',
            ], 500);
        }
    }

    /**
     * Format discovery user
     */
    private function formatDiscoveryUser(User $u, User $currentUser): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'first_name' => explode(' ', $u->name)[0],
            'age' => $u->age,
            'city' => $u->city,
            'country' => $u->country,
            'photo' => profilePhoto($u->id),
            'profile_photo' => $u->profile_photo_url,
            'premium' => $u->premium,
            'verified' => $u->verified,
            'status' => $u->is_online ? 'y' : 'n',
            'fan' => isFan($currentUser->id, $u->id),
            'match' => $u->isMatchWith($currentUser->id) ? 1 : 0,
            'story' => 0, // TODO: Check stories
            'distance' => round(calculateDistance($currentUser->lat, $currentUser->lng, $u->lat, $u->lng), 1),
        ];
    }

    /**
     * Format game user - matches legacy format exactly
     */
    private function formatGameUser(User $u, User $currentUser): array
    {
        // Format name based on onlyUsername setting (matching legacy logic)
        $first_name = explode(' ', trim($u->name));
        $first_name = $first_name[0];
        
        $onlyUsername = config('dating.only_username', 'No');
        if ($onlyUsername === 'Yes') {
            if (empty($u->username)) {
                $first_name = (string) $u->id;
                $name = (string) $u->id;
            } else {
                $first_name = $u->username;
                $name = $u->username;
            }
        } else {
            $name = $u->name;
        }       
        
        // Check if current user is a fan of this user
        $isFan = isFan($currentUser->id, $u->id) ? 1 : 0;
        
        return [
            'id' => $u->id,
            'name' => $name,
            'status' => userFilterStatus($u->id),
            'distance' => $u->distance ?? '', 
            'age' => $u->age,
            'city' => $u->city ?? '',
            'bio' => $u->bio ?? '',
            'isFan' => $isFan,
            'total' => 0, // Legacy code sets this to 0
            'photo' => profilePhoto($u->id, 0), // Thumb
            'discoverPhoto' => profilePhoto($u->id, 1), // Big photo
            'photos' => getUserPhotosAll($u->id, 'discover'),            
            'story' => '0',
            'stories' => [],
            'error' => 0,
        ];
    }

    /**
     * Check if users are a match
     */
    private function checkMatch(int $userId1, int $userId2): bool
    {
        return UserLike::where('u1', $userId1)
            ->where('u2', $userId2)
            ->where('love', 1)
            ->exists() &&
            UserLike::where('u1', $userId2)
            ->where('u2', $userId1)
            ->where('love', 1)
            ->exists();
    }

    /**
     * Get unread message count from a user
     */
    private function getUnreadCount(int $currentUserId, int $targetUserId): int
    {
        return Chat::where('r_id', $currentUserId)
            ->where('s_id', $targetUserId)
            ->where('seen', 0)
            ->count();
    }

    /**
     * Send like notification via Pusher
     */
    private function sendLikeNotification(User $from, int $toUserId, int $action): void
    {
        if ($action == 0) return; // Don't notify on unlike

        try {
            $pusher = $this->getPusher();
            if (!$pusher) return;

            $data = [
                'id' => $from->id,
                'message' => 'Liked your profile',
                'time' => date("H:i"),
                'type' => 4,
                'icon' => profilePhoto($from->id),
                'name' => $from->name,
                'photo' => 0,
                'action' => 'like',
                'unread' => checkUnreadMessages($toUserId),
            ];

            $pusher->trigger(
                config('services.pusher.key'),
                'like' . $toUserId,
                $data
            );

        } catch (\Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * Send match notification
     */
    private function sendMatchNotification(User $user1, int $user2Id): void
    {
        try {
            $pusher = $this->getPusher();
            if (!$pusher) return;

            // Send to both users
            $data1 = [
                'id' => $user2Id,
                'message' => 'It\'s a match!',
                'time' => date("H:i"),
                'type' => 5,
                'icon' => profilePhoto($user2Id),
                'name' => User::find($user2Id)->name ?? '',
                'action' => 'match',
            ];

            $data2 = [
                'id' => $user1->id,
                'message' => 'It\'s a match!',
                'time' => date("H:i"),
                'type' => 5,
                'icon' => profilePhoto($user1->id),
                'name' => $user1->name,
                'action' => 'match',
            ];

            $pusher->trigger(
                config('services.pusher.key'),
                'match' . $user1->id,
                $data1
            );

            $pusher->trigger(
                config('services.pusher.key'),
                'match' . $user2Id,
                $data2
            );

        } catch (\Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * Send visit notification
     */
    private function sendVisitNotification(User $from, int $toUserId): void
    {
        try {
            $pusher = $this->getPusher();
            if (!$pusher) return;

            $data = [
                'id' => $from->id,
                'message' => 'Visited your profile',
                'time' => date("H:i"),
                'type' => 4,
                'icon' => profilePhoto($from->id),
                'name' => $from->name,
                'photo' => 0,
                'action' => 'visit',
                'unread' => checkUnreadMessages($toUserId),
            ];

            $pusher->trigger(
                config('services.pusher.key'),
                'visit' . $toUserId,
                $data
            );

        } catch (\Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * Get Pusher instance
     */
    private function getPusher(): ?Pusher
    {
        $pusher_id = config('services.pusher.app_id');
        
        if (!is_numeric($pusher_id)) {
            return null;
        }

        try {
            $options = [
                'cluster' => config('services.pusher.options.cluster'),
                'useTLS' => true,
            ];

            // Disable SSL verification for local development to fix cURL error 60
            // This is safe for local development but should NOT be used in production
            $httpClient = null;
            if (app()->environment('local', 'development') || str_contains(config('app.url', ''), 'local')) {
                $httpClient = new Client([
                    'verify' => false, // Disable SSL verification for local dev
                ]);
            }

            return new Pusher(
                config('services.pusher.key'),
                config('services.pusher.secret'),
                $pusher_id,
                $options,
                $httpClient
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}



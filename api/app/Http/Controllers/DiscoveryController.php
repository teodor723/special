<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLike;
use App\Models\UserVisit;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Pusher\Pusher;

class DiscoveryController extends Controller
{
    /**
     * Get users for Meet page
     */
    public function getMeetUsers(Request $request)
    {
        $user = $request->user();
        $offset = $request->input('offset', 0);
        $onlineOnly = $request->input('online', 0);
        
        $user->updateLastAccess();

        $searchResult = (int) config('dating.plugin_meet_search_result', 20);
        $limit = $searchResult * ($offset + 1);
        
        // Get age range
        $ageRange = $user->age_range;
        $radius = $user->s_radius;
        $lookingFor = $user->s_gender;

        // Build query
        $query = User::query()
            ->where('id', '!=', $user->id)
            ->notFake()
            ->byAgeRange($ageRange['min'], $ageRange['max'])
            ->nearby($user->lat, $user->lng, $radius);

        // Filter by gender
        $allGenders = count(config('dating.genders', [1, 2])) + 1;
        if ($lookingFor != $allGenders) {
            $query->byGender($lookingFor);
        }

        // Filter online only
        if ($onlineOnly) {
            $query->online();
        }

        // Exclude blocked users
        $blockedIds = UserBlock::where('uid1', $user->id)
            ->pluck('uid2')
            ->toArray();
        
        if (!empty($blockedIds)) {
            $query->whereNotIn('id', $blockedIds);
        }

        $users = $query->limit($limit)->get();

        $result = $users->map(function ($u) use ($user) {
            return $this->formatDiscoveryUser($u, $user);
        });

        return response()->json([
            'result' => $result,
        ]);
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

        // Build query
        $query = User::query()
            ->where('id', '!=', $user->id)
            ->whereNotIn('id', $alreadyLiked)
            ->byAgeRange($ageRange['min'], $ageRange['max'])
            ->nearby($user->lat, $user->lng, $user->s_radius);

        // Filter by gender
        $allGenders = count(config('dating.genders', [1, 2])) + 1;
        if ($lookingFor != $allGenders) {
            $query->byGender($lookingFor);
        }

        // Get 30 users max
        $users = $query->limit(30)->get();

        if ($users->isEmpty()) {
            return response()->json([
                'game' => 'error',
            ]);
        }

        $result = $users->map(function ($u) use ($user) {
            return $this->formatGameUser($u, $user);
        });

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
            'action' => 'required|in:0,1,3', // 0=unlike, 1=like, 3=superlike
        ]);

        $currentUser = $request->user();
        $targetUserId = $request->user_id;
        $action = $request->action;

        $currentUser->updateLastAccess();

        // Check if superlike and has enough
        if ($action == 3) {
            if ($currentUser->sexy <= 0) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'No super likes available',
                ], 422);
            }
            
            $currentUser->decrement('sexy');
        }

        // Create or update like
        $time = $action == 3 ? time() + 288000 : time(); // Superlike gets priority
        
        UserLike::updateOrCreate(
            [
                'u1' => $currentUser->id,
                'u2' => $targetUserId,
            ],
            [
                'love' => $action,
                'time' => $time,
            ]
        );

        // Update popular count
        if ($action == 1 || $action == 3) {
            User::where('id', $targetUserId)->increment('popular');
        }

        // Check if notification should be sent
        $this->sendLikeNotification($currentUser, $targetUserId, $action);

        // Check if it's a match
        $isMatch = $this->checkMatch($currentUser->id, $targetUserId);

        if ($isMatch) {
            $this->sendMatchNotification($currentUser, $targetUserId);
        }

        return response()->json([
            'success' => true,
            'is_match' => $isMatch ? 1 : 0,
        ]);
    }

    /**
     * Get matches
     */
    public function getMatches(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        $likes = UserLike::where('u1', $user->id)
            ->where('love', 1)
            ->orderBy('time', 'desc')
            ->get();

        $matches = [];

        foreach ($likes as $like) {
            $targetUser = User::find($like->u2);
            
            if (!$targetUser) continue;

            $isFan = isFan($like->u2, $user->id);
            $isMatch = $isFan ? 1 : 0;

            $matches[] = [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'first_name' => explode(' ', $targetUser->name)[0],
                'age' => $targetUser->age,
                'city' => $targetUser->city,
                'photo' => profilePhoto($targetUser->id),
                'fan' => $isFan,
                'match' => $isMatch,
                'premium' => $targetUser->premium,
                'verified' => $targetUser->verified,
                'status' => $targetUser->is_online ? 'y' : 'n',
                'story' => 0, // TODO: Check stories
                'last_m' => getTimeDifference($like->time),
                'credits' => $targetUser->credits,
            ];
        }

        return response()->json([
            'matches' => $matches,
        ]);
    }

    /**
     * Get profile visitors
     */
    public function getVisitors(Request $request)
    {
        $user = $request->user();

        $visits = UserVisit::where('u1', $user->id)
            ->where('u2', '!=', $user->id)
            ->orderBy('timeago', 'desc')
            ->get();

        $visitors = [];

        foreach ($visits as $visit) {
            $visitor = User::find($visit->u2);
            
            if (!$visitor) continue;

            $isFan = isFan($visitor->id, $user->id);
            $isMatch = ($isFan && isFan($user->id, $visitor->id)) ? 1 : 0;

            $visitors[] = [
                'id' => $visitor->id,
                'name' => $visitor->name,
                'first_name' => explode(' ', $visitor->name)[0],
                'age' => $visitor->age,
                'city' => $visitor->city,
                'photo' => profilePhoto($visitor->id),
                'fan' => $isFan,
                'match' => $isMatch,
                'premium' => $visitor->premium,
                'status' => $visitor->is_online ? 'y' : 'n',
                'last_m' => getTimeDifference($visit->timeago) . ' ago',
                'last_m_time' => getTimeDifference($visit->timeago),
                'credits' => $visitor->credits,
            ];
        }

        return response()->json([
            'visitors' => $visitors,
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

        // Create or update visit
        UserVisit::updateOrCreate(
            [
                'u1' => $targetUserId,
                'u2' => $currentUser->id,
            ],
            [
                'timeago' => $time,
            ]
        );

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
     * Format game user
     */
    private function formatGameUser(User $u, User $currentUser): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'age' => $u->age,
            'city' => $u->city,
            'photo' => profilePhoto($u->id),
            'photos' => userAppPhotos($u->id),
            'bio' => $u->bio,
            'premium' => $u->premium,
            'verified' => $u->verified,
            'fan' => 0,
            'distance' => round(calculateDistance($currentUser->lat, $currentUser->lng, $u->lat, $u->lng), 1),
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
            return new Pusher(
                config('services.pusher.key'),
                config('services.pusher.secret'),
                $pusher_id,
                [
                    'cluster' => config('services.pusher.options.cluster'),
                    'useTLS' => true,
                ]
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}



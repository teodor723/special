<?php

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('secureEncode')) {
    /**
     * Secure encode input data (replacing old secureEncode)
     */
    function secureEncode($data)
    {
        if (is_array($data)) {
            return array_map('secureEncode', $data);
        }
        
        return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('profilePhoto')) {
    /**
     * Get user's profile photo URL
     * @param int $userId User ID
     * @param int $big 0 for thumb, 1 for big photo
     */
    function profilePhoto(int $userId, int $big = 0): string
    {
        $cacheKey = "user_photo_{$userId}_{$big}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $big) {
            $photo = UserPhoto::where('u_id', $userId)
                ->where('profile', 1)
                ->where('approved', 1)
                ->first();


            
            if (!$photo) {
                return asset('themes/default/img/no-user.png');
            }
            
            // Return big photo or thumb based on $big parameter
            return $big == 1 ? ($photo->photo ?? asset('themes/default/img/no-user.png')) : ($photo->thumb ?? $photo->photo ?? asset('themes/default/img/no-user.png'));
        });
    }
}

if (!function_exists('userAppPhotos')) {
    /**
     * Get user's approved photos for app
     */
    function userAppPhotos(int $userId, int $videoOnly = 0): array
    {
        $query = UserPhoto::where('u_id', $userId)
            ->where('approved', 1);
        
        if ($videoOnly) {
            $query->where('video', 1);
        }
        
        return $query->orderBy('profile', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'photo' => $photo->photo,
                    'thumb' => $photo->thumb ?? $photo->photo,
                    'profile' => $photo->profile ?? 0,
                    'video' => $photo->video ?? 0,
                    'private' => $photo->private ?? 0,
                    'blocked' => $photo->blocked ?? 0,
                ];
            })
            ->toArray();
    }
}

if (!function_exists('getUserSuperLikes')) {
    /**
     * Get user's available super likes
     */
    function getUserSuperLikes(int $userId): int
    {
        $user = User::find($userId);
        if (!$user) {
            return 0;
        }
        return (int) ($user->superlike ?? 0);
    }
}

if (!function_exists('checkUnreadMessages')) {
    /**
     * Check unread messages count for a user
     */
    function checkUnreadMessages(int $userId): int
    {
        return DB::table('chat')
            ->where('r_id', $userId)
            ->where('seen', 0)
            ->where('notification', '!=', 2)
            ->distinct('s_id')
            ->count('s_id');
    }
}

if (!function_exists('userStatus')) {
    /**
     * Check if user is online
     */
    function userStatus(int $userId): string
    {
        $user = User::find($userId);
        
        if (!$user) {
            return 'n';
        }
        
        $fiveMinutesAgo = now()->subMinutes(5)->timestamp;
        
        if ($user->last_access >= $fiveMinutesAgo || 
            ($user->fake && $user->online_day == date('w'))) {
            return 'y';
        }
        
        return 'n';
    }
}

if (!function_exists('isFan')) {
    /**
     * Check if user1 likes user2
     */
    function isFan(int $userId1, int $userId2): int
    {
        return DB::table('users_likes')
            ->where('u1', $userId1)
            ->where('u2', $userId2)
            ->where('love', 1)
            ->exists() ? 1 : 0;
    }
}

if (!function_exists('blockedUser')) {
    /**
     * Check if users have blocked each other
     */
    function blockedUser(int $userId1, int $userId2): int
    {
        return DB::table('users_blocks')
            ->where(function ($query) use ($userId1, $userId2) {
                $query->where('uid1', $userId1)->where('uid2', $userId2);
            })
            ->orWhere(function ($query) use ($userId1, $userId2) {
                $query->where('uid1', $userId2)->where('uid2', $userId1);
            })
            ->exists() ? 1 : 0;
    }
}

if (!function_exists('calculateDistance')) {
    /**
     * Calculate distance between two coordinates in KM
     */
    function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
}

if (!function_exists('getTimeDifference')) {
    /**
     * Get human-readable time difference
     */
    function getTimeDifference(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        }
        
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes');
        }
        
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
        }
        
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' ' . ($days == 1 ? 'day' : 'days');
        }
        
        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks');
        }
        
        $months = floor($diff / 2592000);
        return $months . ' ' . ($months == 1 ? 'month' : 'months');
    }
}

if (!function_exists('getSiteConfig')) {
    /**
     * Get site configuration value
     */
    function getSiteConfig(string $key, $default = null)
    {
        return config("dating.{$key}", $default);
    }
}

if (!function_exists('cleanMessage')) {
    /**
     * Clean and prepare message for display
     */
    function cleanMessage(string $message): string
    {
        // Remove HTML tags except images
        $message = strip_tags($message, '<img>');
        
        // Convert newlines to <br>
        $message = nl2br($message);
        
        return $message;
    }
}

if (!function_exists('getUserIpAddress')) {
    /**
     * Get user's IP address
     */
    function getUserIpAddress(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log user activity
     */
    function logActivity(string $type, array $data, string $title): void
    {
        if (config('dating.log_activity', false)) {
            DB::table('activity')->insert([
                'activity_type' => $type,
                'activity_content' => json_encode($data),
                'activity_title' => $title,
                'time' => time(),
            ]);
        }
    }
}

if (!function_exists('userNotifications')) {
    /**
     * Get user notification settings formatted
     */
    function userNotifications(int $userId): array
    {
        $notification = \App\Models\UserNotification::where('uid', $userId)->first();
        
        if (!$notification) {
            return [
                'fan' => ['email' => '1', 'push' => '1', 'inapp' => '1'],
                'match_me' => ['email' => '1', 'push' => '1', 'inapp' => '1'],
                'near_me' => ['email' => '1', 'push' => '1', 'inapp' => '1'],
                'message' => ['email' => '1', 'push' => '1', 'inapp' => '1'],
            ];
        }
        
        $result = [];
        
        $fan = explode(',', $notification->fan ?? '1,1,1');
        $result['fan'] = [
            'email' => $fan[0] ?? '1',
            'push' => $fan[1] ?? '1',
            'inapp' => $fan[2] ?? '1',
        ];
        
        $match = explode(',', $notification->match_me ?? '1,1,1');
        $result['match_me'] = [
            'email' => $match[0] ?? '1',
            'push' => $match[1] ?? '1',
            'inapp' => $match[2] ?? '1',
        ];
        
        $near = explode(',', $notification->near_me ?? '1,1,1');
        $result['near_me'] = [
            'email' => $near[0] ?? '1',
            'push' => $near[1] ?? '1',
            'inapp' => $near[2] ?? '1',
        ];
        
        $message = explode(',', $notification->message ?? '1,1,1');
        $result['message'] = [
            'email' => $message[0] ?? '1',
            'push' => $message[1] ?? '1',
            'inapp' => $message[2] ?? '1',
        ];
        
        return $result;
    }
}

if (!function_exists('getUserTotalPhotos')) {
    function getUserTotalPhotos(int $userId): int
    {
        return \App\Models\UserPhoto::where('u_id', $userId)
            ->where('approved', 1)
            ->count();
    }
}

if (!function_exists('getUserTotalPhotosPublic')) {
    function getUserTotalPhotosPublic(int $userId): int
    {
        return \App\Models\UserPhoto::where('u_id', $userId)
            ->where('approved', 1)
            ->where('private', 0)
            ->count();
    }
}

if (!function_exists('getUserTotalPhotosPrivate')) {
    function getUserTotalPhotosPrivate(int $userId): int
    {
        return \App\Models\UserPhoto::where('u_id', $userId)
            ->where('approved', 1)
            ->where('private', 1)
            ->count();
    }
}

if (!function_exists('getUserTotalLikers')) {
    function getUserTotalLikers(int $userId): int
    {
        return DB::table('users_likes')
            ->where('u2', $userId)
            ->where('love', 1)
            ->count();
    }
}

if (!function_exists('getUserTotalNoLikers')) {
    function getUserTotalNoLikers(int $userId): int
    {
        return DB::table('users_likes')
            ->where('u2', $userId)
            ->where('love', 0)
            ->count();
    }
}

if (!function_exists('getUserLikePercent')) {
    function getUserLikePercent(int $likers, int $total): int
    {
        if ($total == 0) return 0;
        return (int) round(($likers / $total) * 100);
    }
}

if (!function_exists('getUserTotalLikes')) {
    function getUserTotalLikes(int $userId): int
    {
        return DB::table('users_likes')
            ->where('u1', $userId)
            ->where('love', 1)
            ->count();
    }
}

if (!function_exists('getUserPhotosAllProfile')) {
    function getUserPhotosAllProfile(int $userId): array
    {
        return \App\Models\UserPhoto::where('u_id', $userId)
            ->where('approved', 1)
            ->orderBy('profile', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($photo) {
                return [
                    'image' => $photo->photo,
                    'photoId' => (string) $photo->id,
                    'private' => (string) ($photo->private ?? 0),
                ];
            })
            ->toArray();
    }
}

if (!function_exists('getUserPhotosAll')) {
    /**
     * Get user photos for discover/game page
     * @param int $userId User ID
     * @param string $page Page type ('discover' or empty)
     */
    function getUserPhotosAll(int $userId, string $page = ''): array
    {
        $limit = 15;
        if ($page === 'discover') {
            $limit = (int) config('dating.plugin_discover_photos_galleria', 15);
        }
        
        return \App\Models\UserPhoto::where('u_id', $userId)
            ->where('approved', 1)
            ->where('blocked', 0)
            ->where('video', 0)
            ->where('story', 0)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($photo) {
                return [
                    'image' => $photo->photo,
                ];
            })
            ->toArray();
    }
}

if (!function_exists('userExtended')) {
    function userExtended(int $userId): ?array
    {
        $extended = DB::table('users_extended')
            ->where('uid', $userId)
            ->first();
        
        return $extended ? (array) $extended : null;
    }
}

if (!function_exists('userInterest')) {
    function userInterest(int $userId): array
    {
        $interests = DB::table('users_interest')
            ->where('u_id', $userId)
            ->get();
        
        $result = [];
        foreach ($interests as $interest) {
            $interestData = DB::table('interest')
                ->where('id', $interest->i_id)
                ->first();
            
            if ($interestData) {
                $result[$interest->i_id] = [
                    'id' => $interest->i_id,
                    'name' => $interestData->name ?? 'noData',
                    'icon' => $interestData->icon ?? 'noData',
                ];
            }
        }
        
        return $result;
    }
}

if (!function_exists('userMatchesCount')) {
    function userMatchesCount(int $userId): int
    {
        // Get users who liked this user
        $likers = DB::table('users_likes')
            ->where('u2', $userId)
            ->where('love', 1)
            ->pluck('u1')
            ->toArray();
        
        if (empty($likers)) {
            return 0;
        }
        
        // Count how many of those this user also liked
        return DB::table('users_likes')
            ->where('u1', $userId)
            ->where('love', 1)
            ->whereIn('u2', $likers)
            ->count();
    }
}

if (!function_exists('profilePhotoBig')) {
    function profilePhotoBig(int $userId): string
    {
        $photo = \App\Models\UserPhoto::where('u_id', $userId)
            ->where('profile', 1)
            ->where('approved', 1)
            ->value('photo');
        
        return $photo ?? asset('themes/default/img/no-user.png');
    }
}

if (!function_exists('randomPhoto')) {
    function randomPhoto(int $userId): string
    {
        $photo = \App\Models\UserPhoto::where('u_id', $userId)
            ->where('approved', 1)
            ->inRandomOrder()
            ->value('photo');
        
        return $photo ?? asset('themes/default/img/no-user.png');
    }
}

if (!function_exists('userStatusIcon')) {
    function userStatusIcon(int $userId): string
    {
        $status = userStatus($userId);
        if ($status === 'y') {
            return '<i class="mdi-image-brightness-1" style="color:#17d425"></i>';
        }
        return '<i class="mdi-image-brightness-1" style="color:#ccc"></i>';
    }
}

if (!function_exists('userFilterStatus')) {
    function userFilterStatus(int $userId): int
    {
        // Return 1 if user is online, 0 if offline
        return userStatus($userId) === 'y' ? 1 : 0;
    }
}

if (!function_exists('checkUserPremium')) {
    function checkUserPremium(int $userId): int
    {
        $premium = DB::table('users_premium')
            ->where('uid', $userId)
            ->value('premium');
        
        if (!$premium) {
            return 0;
        }
        
        return $premium > time() ? 1 : 0;
    }
}

if (!function_exists('getLangName')) {
    function getLangName(int $langId): string
    {
        $lang = DB::table('languages')
            ->where('id', $langId)
            ->value('name');
        
        return $lang ?? 'English';
    }
}

if (!function_exists('getLangPrefix')) {
    function getLangPrefix(int $langId): string
    {
        $prefix = DB::table('languages')
            ->where('id', $langId)
            ->value('prefix');
        
        return $prefix ?? 'en';
    }
}

if (!function_exists('getRandomFakeOnline')) {
    function getRandomFakeOnline(string $column, int $looking): array
    {
        // Get random fake users who are online and match the looking preference
        $users = DB::table('users')
            ->where('fake', 1)
            ->where('looking', $looking)
            ->where('online_day', date('w'))
            ->inRandomOrder()
            ->limit(20)
            ->pluck($column)
            ->toArray();
        
        return array_map(function ($id) {
            return ['id' => (string) $id];
        }, $users);
    }
}

if (!function_exists('checkWithdrawExist')) {
    function checkWithdrawExist(int $userId): string
    {
        $exists = DB::table('users_withdraw')
            ->where('uid', $userId)
            ->where('status', 0)
            ->exists();
        
        return $exists ? '1' : '0';
    }
}

if (!function_exists('adminCheckUserPremium')) {
    function adminCheckUserPremium(int $userId): int
    {
        return checkUserPremium($userId);
    }
}

if (!function_exists('getRegisterReward')) {
    function getRegisterReward(int $userId): string
    {
        $reward = DB::table('users_rewards')
            ->where('uid', $userId)
            ->where('reward', 'newAccountFreeCredit')
            ->value('reward');
        
        return $reward ?: 'noData';
    }
}

if (!function_exists('clean')) {
    function clean(string $text): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $text);
    }
}


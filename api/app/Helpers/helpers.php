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
     */
    function profilePhoto(int $userId): string
    {
        $cacheKey = "user_photo_{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            $photo = UserPhoto::where('u_id', $userId)
                ->where('profile', 1)
                ->where('approved', 1)
                ->value('photo');
            
            return $photo ?? asset('themes/default/img/no-user.png');
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
                    'thumb' => $photo->thumb,
                    'profile' => $photo->profile,
                    'video' => $photo->video,
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
        return User::where('id', $userId)->value('sexy') ?? 0;
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


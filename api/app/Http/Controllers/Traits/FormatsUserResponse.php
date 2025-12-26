<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait FormatsUserResponse
{
    /**
     * Format user response - matches original getUserInfo structure
     * This method is shared between AuthController and ProfileController
     * to ensure consistent user data format across all endpoints
     */
    protected function formatUserResponse(User $user): array
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
}


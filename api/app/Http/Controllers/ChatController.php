<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pusher\Pusher;
use GuzzleHttp\Client;

class ChatController extends Controller
{
    /**
     * Get all conversations
     */
    public function getConversations(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Get distinct conversation partners
        $conversations = DB::table('chat')
            ->select('s_id', 'r_id', DB::raw('MAX(id) as last_message_id'))
            ->where(function ($query) use ($user) {
                $query->where('r_id', $user->id)
                    ->orWhere('s_id', $user->id);
            })
            ->where('seen', '!=', 2)
            ->where('notification', '!=', 2)
            ->groupBy('s_id', 'r_id')
            ->orderBy('last_message_id', 'desc')
            ->get();

        $matches = [];
        $processedUsers = [];

        foreach ($conversations as $conv) {
            $partnerId = $conv->s_id == $user->id ? $conv->r_id : $conv->s_id;
            
            // Skip if already processed
            if (in_array($partnerId, $processedUsers)) {
                continue;
            }
            
            $processedUsers[] = $partnerId;
            
            $partner = User::find($partnerId);
            if (!$partner) continue;

            // Get last message
            $lastMessage = Chat::conversation($user->id, $partnerId)
                ->notDeleted()
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastMessage) continue;

            // Count unread messages
            $unreadCount = Chat::where('r_id', $user->id)
                ->where('s_id', $partnerId)
                ->where('seen', 0)
                ->count();

            // Format last message
            $lastMessageText = $this->formatLastMessage($lastMessage);

            $matches[] = [
                'id' => $partner->id,
                'name' => $this->sanitizeUtf8($partner->name ?? ''),
                'first_name' => $this->sanitizeUtf8(explode(' ', $partner->name ?? '')[0] ?? ''),
                'age' => $partner->age,
                'city' => $this->sanitizeUtf8($partner->city ?? ''),
                'photo' => profilePhoto($partner->id),
                'premium' => $partner->premium,
                'status' => $partner->is_online ? 'y' : 'n',
                'last_m' => $this->sanitizeUtf8($lastMessageText),
                'last_m_time' => $this->sanitizeUtf8(getTimeDifference($lastMessage->time)),
                'unread' => $unreadCount,
                'credits' => $partner->credits,
            ];
        }

        return response()->json([
            'matches' => $matches,
            'unread' => array_sum(array_column($matches, 'unread')),
        ]);
    }

    /**
     * Get conversation with specific user
     */
    public function getConversation(int $userId, Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        // Check if blocked
        if (blockedUser($user->id, $userId)) {
            return response()->json([
                'blocked' => 1,
                'chat' => [],
            ]);
        }

        // Check chat limits (basic vs premium)
        $todayConversations = $this->getUserTodayConversations($user->id);
        $totalConversations = $this->getUserTotalConversations($user->id, $userId);
        
        $chatLimit = $user->premium ? 
            config('dating.chat_premium_limit', 'unlimited') : 
            config('dating.chat_basic_limit', 10);

        $needsPremium = 0;
        if ($chatLimit != 'unlimited' && $totalConversations == 0 && $todayConversations >= (int)$chatLimit) {
            $needsPremium = 1;
        }

        // Mark messages as seen
        Chat::where('r_id', $user->id)
            ->where('s_id', $userId)
            ->update(['seen' => 1]);

        // Get messages
        $messages = Chat::conversation($user->id, $userId)
            ->notDeleted()
            ->orderBy('id', 'asc')
            ->get();

        $chat = $messages->map(function ($msg) use ($user) {
            return [
                'id' => $msg->id,
                'message' => $this->formatMessage($msg),
                'body' => $msg->message,
                'time' => $msg->time,
                'timestamp' => date('M d Y', $msg->time),
                'hour' => date('H:i', $msg->time),
                'type' => $this->getMessageType($msg),
                'sender' => $msg->s_id == $user->id ? 'me' : 'them',
                'seen' => $msg->seen,
            ];
        });

        return response()->json([
            'blocked' => 0,
            'premium' => $needsPremium,
            'chat' => $chat,
        ]);
    }

    /**
     * Send message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'message' => 'required|string',
            'type' => 'nullable|in:text,image,gif,gift,story,videocall',
            'extra' => 'nullable|string', // For gift price, story ID, etc
        ]);

        $user = $request->user();
        $receiverId = $request->receiver_id;
        $message = $request->message;
        $type = $request->type ?? 'text';
        $extra = $request->extra;

        // Check if blocked
        if (blockedUser($user->id, $receiverId)) {
            return response()->json([
                'error' => 1,
                'error_m' => 'User is blocked',
            ], 403);
        }

        $time = time();
        $receiver = User::find($receiverId);

        // Handle gift type (deduct credits)
        if ($type == 'gift' && $extra) {
            $price = (int) $extra;
            if (!$user->deductCredits($price, 'Gift sent')) {
                return response()->json([
                    'error' => 1,
                    'error_m' => 'Insufficient credits',
                ], 422);
            }
        }

        // Create chat message
        $chatData = [
            's_id' => $user->id,
            'r_id' => $receiverId,
            'message' => $message,
            'time' => $time,
            'seen' => 0,
            'notification' => 0,
            'fake' => $receiver->fake,
            'online_day' => $receiver->online_day,
        ];

        // Set type-specific flags
        switch ($type) {
            case 'image':
                $chatData['photo'] = 1;
                break;
            case 'gif':
                $chatData['gif'] = 1;
                break;
            case 'gift':
                $chatData['gift'] = 1;
                $chatData['credits'] = (int) $extra;
                break;
            case 'story':
                $chatData['story'] = (int) $extra;
                break;
        }

        $chat = Chat::create($chatData);

        // Send real-time notification
        $this->sendRealTimeNotification($user, $receiver, $message, $type);

        // Send email notification if user offline
        if (!$receiver->is_online && !$receiver->fake) {
            // TODO: Send email notification
        }

        // Transfer credits to receiver if enabled
        $this->transferCreditsToReceiver($user, $receiver);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $chat->id,
                'message' => $this->formatMessage($chat),
                'time' => $chat->time,
                'hour' => date('H:i', $chat->time),
                'type' => $type,
            ],
        ]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(int $userId, Request $request)
    {
        $user = $request->user();

        Chat::where('r_id', $user->id)
            ->where('s_id', $userId)
            ->where('seen', 0)
            ->update(['seen' => 1, 'notification' => 1]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Delete conversation
     */
    public function deleteConversation(int $userId, Request $request)
    {
        $user = $request->user();

        // Mark as deleted (seen = 2) instead of actually deleting
        Chat::where('r_id', $user->id)
            ->where('s_id', $userId)
            ->update(['seen' => 2]);

        Chat::where('s_id', $user->id)
            ->where('r_id', $userId)
            ->update(['notification' => 2]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(Request $request)
    {
        $user = $request->user();

        $count = Chat::where('r_id', $user->id)
            ->where('seen', 0)
            ->where('notification', '!=', 2)
            ->distinct('s_id')
            ->count('s_id');

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Format message for display
     */
    private function formatMessage(Chat $msg): string
    {
        $message = $msg->message;

        if ($msg->photo) {
            return '<img src="' . $message . '" class="chat-image" />';
        }

        if ($msg->gif) {
            return '<img src="' . $message . '" class="chat-gif" />';
        }

        if ($msg->gift) {
            return '<img src="' . $message . '" class="chat-gift" />';
        }

        return cleanMessage($message);
    }

    /**
     * Format last message preview
     */
    private function formatLastMessage(Chat $msg): string
    {
        if ($msg->photo) return 'Photo';
        if ($msg->gif) return 'GIF';
        if ($msg->gift) return 'Gift';
        if ($msg->story) return 'Story';

        $text = strip_tags($msg->message ?? '');
        $text = $this->sanitizeUtf8($text);
        
        // Use mb_strlen for proper UTF-8 character counting
        return mb_strlen($text) > 50 ? mb_substr($text, 0, 50) . '...' : $text;
    }

    /**
     * Get message type
     */
    private function getMessageType(Chat $msg): string
    {
        if ($msg->photo == 1) return 'image';
        if ($msg->photo == 2) return 'video';
        if ($msg->gif) return 'gif';
        if ($msg->gift) return 'gift';
        if ($msg->story) return 'story';
        
        return 'text';
    }

    /**
     * Send real-time notification via Pusher
     */
    private function sendRealTimeNotification(User $sender, User $receiver, string $message, string $type): void
    {
        try {
            $pusher = $this->getPusher();
            if (!$pusher) return;

            $content = $message;
            
            // Format content based on type
            if ($type == 'image') {
                $content = '<div class="message__pic_"><img src="' . $message . '" /></div>';
            } elseif ($type == 'gif') {
                $content = '<div class="message__pic_"><img src="' . $message . '" /></div>';
            } elseif ($type == 'gift') {
                $content = '<div class="message__pic_"><img src="' . $message . '" /></div>';
            }

            // Send to chat channel
            $chatEvent = 'chat' . $receiver->id . $sender->id;
            $chatData = [
                'type' => $type,
                'message' => $message,
                'id' => $sender->id,
                'action' => 'message',
                'chatHeaderRight' => '<div class="js-message-block" id="you">
                    <div class="message">
                        <div class="brick brick--xsm brick--hover">
                            <div class="brick-img profile-photo" data-src="' . profilePhoto($sender->id) . '"></div>
                        </div>
                        <div class="message__txt">
                            <span class="lgrey message__time">' . date("H:i") . '</span>
                            <div class="message__name lgrey">' . $sender->name . '</div>
                            <p class="montserrat chat-text">' . $content . '</p>
                        </div>
                    </div>
                </div>',
            ];

            $pusher->trigger(config('services.pusher.key'), $chatEvent, $chatData);

            // Send notification
            $notiEvent = 'notification' . $receiver->id;
            $notiData = [
                'id' => $sender->id,
                'message' => $this->getNotificationMessage($type),
                'time' => date("H:i"),
                'type' => $type,
                'icon' => profilePhoto($sender->id),
                'name' => $sender->name,
                'photo' => 0,
                'action' => 'message',
                'unread' => checkUnreadMessages($receiver->id),
            ];

            $pusher->trigger(config('services.pusher.key'), $notiEvent, $notiData);

        } catch (\Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * Get notification message based on type
     */
    private function getNotificationMessage(string $type): string
    {
        switch ($type) {
            case 'image':
                return 'Sent you a photo';
            case 'gif':
                return 'Sent you a GIF';
            case 'gift':
                return 'Sent you a gift';
            case 'story':
                return 'Shared a story with you';
            default:
                return 'Sent you a message';
        }
    }

    /**
     * Transfer credits to receiver if enabled
     */
    private function transferCreditsToReceiver(User $sender, User $receiver): void
    {
        if (!config('dating.chat_transfer_credits', false)) {
            return;
        }

        $creditsPerMessage = (int) config('dating.chat_credits_per_message', 1);
        $creditsGender = (int) config('dating.chat_credits_gender', 0);
        $allGenders = count(config('dating.genders', [1, 2])) + 1;

        if ($creditsGender == $sender->gender || $creditsGender == $allGenders) {
            $receiver->addCredits($creditsPerMessage, 'Credits for message received');
        }
    }

    /**
     * Get user's today conversations count
     */
    private function getUserTodayConversations(int $userId): int
    {
        $date = date('m/d/Y');
        
        return DB::table('users_chat')
            ->where('uid', $userId)
            ->where('date', $date)
            ->value('count') ?? 0;
    }

    /**
     * Get total conversations with specific user
     */
    private function getUserTotalConversations(int $userId1, int $userId2): int
    {
        return Chat::conversation($userId1, $userId2)->count();
    }

    /**
     * Send real-time message notification (refactored from rt.php)
     * Handles Pusher notifications for chat messages
     */
    public function sendRealTimeMessage(Request $request)
    {
        $request->validate([
            'query' => 'required|string', // Format: senderId[rt]receiverId[rt]senderPhoto[rt]senderName[rt]content[rt]type
        ]);

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication required'], 401);
        }
        
        $query = $request->input('query');
        $data = explode('[rt]', $query);

        if (count($data) < 6) {
            return response()->json(['error' => 'Invalid query format'], 400);
        }

        $senderId = (int) $data[0];
        $receiverId = (int) $data[1];
        $senderPhoto = $data[2];
        $senderName = $data[3];
        $message = $data[4];
        $type = $data[5];
        $storyType = $data[6] ?? '';
        $storyUrl = $data[7] ?? '';

        // Verify sender is the authenticated user
        if ($senderId !== (int) $user->id) {
            return response()->json([
                'error' => 'Unauthorized', 
                'message' => 'Sender ID does not match authenticated user'
            ], 403);
        }

        $receiver = User::find($receiverId);
        if (!$receiver) {
            return response()->json(['error' => 'Receiver not found'], 404);
        }

        $lang = $this->getUserLang($receiverId);
        $time = time();
        $notiMessage = $this->getLang(686, $lang);

        // Handle special message types
        if ($type == 'credits') {
            $creditsAmount = $data[6] ?? '';
            $message = '<b>' . $this->getLang(583, $lang) . ' ' . $creditsAmount . ' ' . $this->getLang(128, $lang) . '!</b>';
            $notiMessage = $message;
        }

        if ($type == 'videocall') {
            $notiMessage = $message;
        }

        // Format content based on type
        $content = $message;
        if ($type == 'image') {
            $content = '<div class="message__pic_" style="cursor:pointer;"><img src="' . $message . '" /></div>';
            $notiMessage = $this->getLang(687, $lang);
        } elseif ($type == 'gif') {
            $content = '<div class="message__pic_" style="cursor:pointer;border:none"><img src="' . $message . '" /></div>';
            $notiMessage = $this->getLang(688, $lang);
        } elseif ($type == 'gift') {
            $content = '<div class="message__pic_" style="cursor:pointer;border:none"><img src="' . $message . '" /></div>';
            $notiMessage = $this->getLang(689, $lang);
        } elseif ($type == 'story') {
            if ($storyType == 'video') {
                $content = '<div class="message__pic_" style="cursor:pointer;">
                    <video src="' . $storyUrl . '" type="video/mp4" muted preload style="position:absolute;top:0;left:0;width:100%;height:100%"></video>
                </div>
                <span style="opacity:.6;font-size:11px;margin-bottom:10px">' . $this->getLang(663, $lang) . '</span><br>' . $message;
            } else {
                $content = '<div class="message__pic_" style="cursor:pointer;"><img src="' . $storyUrl . '" /></div>
                <span style="opacity:.6;font-size:11px;margin-bottom:10px">' . $this->getLang(663, $lang) . '</span><br>' . $message;
            }
        }

        $pusher = $this->getPusher();
        if (!$pusher) {
            return response()->json(['error' => 'Pusher not configured'], 500);
        }

        // Send chat event
        $chatEvent = 'chat' . $receiverId . $senderId;
        $chatData = [
            'type' => $type,
            'notification_chat' => false,
            'message' => $message,
            'id' => $senderId,
            'action' => 'message',
            'chatHeaderRight' => '<div class="js-message-block" id="you">
                <div class="message">
                    <div class="brick brick--xsm brick--hover">
                        <div class="brick-img profile-photo" data-src="' . $senderPhoto . '"></div>
                    </div>
                    <div class="message__txt">
                        <span class="lgrey message__time" style="margin-right: 15px;">' . date("H:i", $time) . '</span>
                        <div class="message__name lgrey">' . $senderName . '</div>
                        <p class="montserrat chat-text">' . $content . '</p>
                    </div>
                </div>
            </div>',
        ];

        $pusher->trigger(config('services.pusher.key'), $chatEvent, $chatData);

        // Check for unread messages to determine notification_chat
        $unreadResults = DB::table('chat')
            ->where('r_id', $receiverId)
            ->where('seen', 0)
            ->where('notification', 0)
            ->orderBy('id', 'desc')
            ->get();

        $notiData = [
            'notification_chat' => false,
            'id' => $senderId,
            'message' => $notiMessage,
            'time' => date("H:i", $time),
            'type' => $type,
            'icon' => $senderPhoto,
            'name' => $senderName,
            'photo' => 0,
            'action' => 'message',
            'unread' => $this->checkUnreadMessages($receiverId),
        ];

        if ($unreadResults->count() > 0) {
            $notiData['notification_chat'] = $this->getUserFriends($receiverId);
            $notiData['unread'] = $this->checkUnreadMessages($receiverId);
        }

        // Send notification event
        $notiEvent = 'notification' . $receiverId;
        $pusher->trigger(config('services.pusher.key'), $notiEvent, $notiData);

        return response()->json(['success' => true]);
    }

    /**
     * Send typing indicator (refactored from rt.php)
     */
    public function sendTypingIndicator(Request $request)
    {
        $request->validate([
            'sender_id' => 'required',
            'receiver_id' => 'required|integer',
            'is_typing' => 'required|integer|in:0,1',
        ]);

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication required'], 401);
        }

        $senderId = (int) $request->input('sender_id');
        $receiverId = (int) $request->input('receiver_id');
        $isTyping = (int) $request->input('is_typing');

        // Verify sender is the authenticated user
        if ($senderId !== (int) $user->id) {
            return response()->json([
                'error' => 'Unauthorized', 
                'message' => 'Sender ID does not match authenticated user'
            ], 403);
        }

        $pusher = $this->getPusher();
        if (!$pusher) {
            return response()->json(['error' => 'Pusher not configured'], 500);
        }

        $event = 'typing' . $receiverId . $senderId;
        $data = ['t' => $isTyping];

        $pusher->trigger(config('services.pusher.key'), $event, $data);

        return response()->json(['success' => true]);
    }

    /**
     * Track today's chat usage (refactored from api.php 'today' action)
     */
    public function trackTodayChat(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication required'], 401);
        }
        
        $time = time();
        $date = date('m/d/Y', $time);

        DB::table('users_chat')->updateOrInsert(
            ['uid' => $user->id, 'date' => $date],
            [
                'count' => DB::raw('count + 1'),
                'last_chat' => $time,
            ]
        );

        return response()->json(['success' => true]);
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

    /**
     * Helper: Get user language ID
     */
    private function getUserLang(int $userId): int
    {
        $user = User::find($userId);
        return $user ? (int) $user->lang : 1;
    }

    /**
     * Helper: Get language string
     */
    private function getLang(int $langId, int $lang): string
    {
        $langData = DB::table('site_lang')
            ->where('id', $langId)
            ->where('lang_id', $lang)
            ->value('text');
        
        return $langData ?: '';
    }

    /**
     * Helper: Check unread messages count
     */
    private function checkUnreadMessages(int $userId): int
    {
        return Chat::where('r_id', $userId)
            ->where('seen', 0)
            ->count();
    }

    /**
     * Helper: Get user friends (for notification_chat)
     */
    private function getUserFriends(int $userId): array
    {
        $friends = [];
        $today = date('w');
        
        $results = DB::table('chat')
            ->select('s_id', 'id')
            ->where('r_id', $userId)
            ->where('seen', '<=', 1)
            ->orderBy('id', 'desc')
            ->distinct()
            ->get();

        foreach ($results as $result) {
            if (!in_array($result->s_id, $friends)) {
                $friends[] = $result->s_id;
            }
        }

        return $friends;
    }

    /**
     * Sanitize UTF-8 string to remove invalid characters
     */
    private function sanitizeUtf8(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        // Use iconv to remove invalid UTF-8 characters
        if (function_exists('iconv')) {
            $string = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        }
        
        // Fallback: use mb_convert_encoding
        if ($string !== false) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // Remove any remaining invalid UTF-8 sequences using regex
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
        
        // Final check: ensure valid UTF-8
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        return $string ?: '';
    }
}



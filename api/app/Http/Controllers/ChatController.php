<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pusher\Pusher;

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
                'name' => $partner->name,
                'first_name' => explode(' ', $partner->name)[0],
                'age' => $partner->age,
                'city' => $partner->city,
                'photo' => profilePhoto($partner->id),
                'premium' => $partner->premium,
                'status' => $partner->is_online ? 'y' : 'n',
                'last_m' => $lastMessageText,
                'last_m_time' => getTimeDifference($lastMessage->time),
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

        $text = strip_tags($msg->message);
        return strlen($text) > 50 ? substr($text, 0, 50) . '...' : $text;
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



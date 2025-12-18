<?php

namespace App\Http\Controllers;

use App\Models\UserStory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StoryController extends Controller
{
    /**
     * Get stories feed
     */
    public function getStories(Request $request)
    {
        $user = $request->user();
        $offset = $request->input('offset', 0);
        
        $lat = $request->input('lat', $user->lat);
        $lng = $request->input('lng', $user->lng);
        $lookingFor = $user->s_gender;

        // Get story expiry time
        $storyDays = config('dating.plugin_story_days', 1);
        $expiryTime = now()->subDays($storyDays)->timestamp;

        // Get users with active stories
        $userIds = UserStory::where('storyTime', '>', $expiryTime)
            ->where('deleted', 0)
            ->where('visible', 1)
            ->groupBy('uid')
            ->pluck('uid');

        if ($userIds->isEmpty()) {
            return response()->json([
                'result' => 'empty',
                'stories' => [],
            ]);
        }

        // Get users info
        $query = User::whereIn('id', $userIds)
            ->where('id', '!=', $user->id);

        // Filter by gender preference
        $allGenders = count(config('dating.genders', [1, 2])) + 1;
        if ($lookingFor != $allGenders) {
            $query->byGender($lookingFor);
        }

        // Order by distance
        $users = $query->selectRaw("*, 
            ( 6371 * acos( 
                cos( radians(?) ) * 
                cos( radians( lat ) ) * 
                cos( radians( lng ) - radians(?) ) + 
                sin( radians(?) ) * 
                sin( radians( lat ) )
            ) ) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<=', $user->s_radius)
            ->orderBy('distance')
            ->offset($offset * 10)
            ->limit(10)
            ->get();

        $stories = [];

        foreach ($users as $u) {
            $userStories = UserStory::where('uid', $u->id)
                ->where('storyTime', '>', $expiryTime)
                ->where('deleted', 0)
                ->where('visible', 1)
                ->orderBy('storyTime', 'asc')
                ->get();

            if ($userStories->isEmpty()) continue;

            $stories[] = [
                'uid' => $u->id,
                'name' => $u->name,
                'first_name' => explode(' ', $u->name)[0],
                'photo' => profilePhoto($u->id),
                'premium' => $u->premium,
                'verified' => $u->verified,
                'items' => $userStories->map(function ($story) {
                    return [
                        'id' => $story->id,
                        'type' => $story->storyType,
                        'src' => $story->story,
                        'hls_url' => $story->hls_url,
                        'time' => $story->storyTime,
                        'views' => $story->storyViews,
                        'seen' => false, // TODO: Track viewed stories
                    ];
                })->toArray(),
            ];
        }

        return response()->json([
            'result' => 'OK',
            'stories' => $stories,
        ]);
    }

    /**
     * Get specific user's stories
     */
    public function getUserStories(int $userId)
    {
        $storyDays = config('dating.plugin_story_days', 1);
        $expiryTime = now()->subDays($storyDays)->timestamp;

        $user = User::findOrFail($userId);
        
        $stories = UserStory::where('uid', $userId)
            ->where('storyTime', '>', $expiryTime)
            ->where('deleted', 0)
            ->where('visible', 1)
            ->orderBy('storyTime', 'asc')
            ->get();

        $firstName = explode(' ', $user->name)[0];

        return response()->json([
            'stories' => [
                [
                    'uid' => $user->id,
                    'name' => $user->name,
                    'first_name' => $firstName,
                    'photo' => profilePhoto($user->id),
                    'items' => $stories->map(function ($story) {
                        return [
                            'id' => $story->id,
                            'type' => $story->storyType,
                            'src' => $story->story,
                            'hls_url' => $story->hls_url,
                            'time' => $story->storyTime,
                            'views' => $story->storyViews,
                        ];
                    })->toArray(),
                ],
            ],
        ]);
    }

    /**
     * Upload story
     */
    public function upload(Request $request)
    {
        $request->validate([
            'path' => 'required|url',
            'thumb' => 'required|url',
            'type' => 'required|in:image,video',
        ]);

        $user = $request->user();
        $path = $request->path;
        $thumb = $request->thumb;
        $type = $request->type;

        // Check if review is enabled
        $reviewEnabled = config('dating.plugin_story_review', false);
        $visible = $reviewEnabled ? 0 : 1;

        // TODO: AWS Rekognition integration for content moderation
        $rekognitionJson = '';
        $hls_url = '';

        // If S3 URL, check for Rekognition data
        if (str_contains($path, 's3.') || str_contains($path, 'amazonaws.com')) {
            $fileName = basename($path);
            
            // Poll for Rekognition results (wait up to 30 seconds)
            $startTime = time();
            $maxWaitTime = 30;
            
            while ((time() - $startTime) < $maxWaitTime) {
                $result = DB::table('reels_aws_upload')
                    ->where('file_name', $fileName)
                    ->first();
                
                if ($result && !empty($result->rekognition)) {
                    $rekognitionJson = $result->rekognition;
                    $hls_url = $result->hls_url ?? '';
                    
                    // Parse and determine visibility
                    $data = json_decode($rekognitionJson, true);
                    if ($data && is_array($data)) {
                        $visible = $this->moderateContent($data);
                    }
                    break;
                }
                
                sleep(1);
            }
        }

        // Create story
        $story = UserStory::create([
            'uid' => $user->id,
            'storyType' => $type,
            'story' => $path,
            'storyTime' => time(),
            'storyViews' => 0,
            'deleted' => 0,
            'visible' => $visible,
            'rekognition' => $rekognitionJson,
            'hls_url' => $hls_url,
        ]);

        // Clear cache
        Cache::forget("user_stories_{$user->id}");

        return response()->json([
            'success' => true,
            'story' => [
                'id' => $story->id,
                'type' => $story->storyType,
                'src' => $story->story,
                'status' => $visible == 1 ? 'approved' : ($visible == 2 ? 'rejected' : 'pending'),
            ],
        ]);
    }

    /**
     * Delete story
     */
    public function delete(int $id, Request $request)
    {
        $user = $request->user();
        
        $story = UserStory::where('id', $id)
            ->where('uid', $user->id)
            ->firstOrFail();

        $story->update(['deleted' => 1]);

        Cache::forget("user_stories_{$user->id}");

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Check if user has active stories
     */
    public function checkHasStory(int $userId)
    {
        $storyDays = config('dating.plugin_story_days', 1);
        $expiryTime = now()->subDays($storyDays)->timestamp;

        $count = UserStory::where('uid', $userId)
            ->where('storyTime', '>', $expiryTime)
            ->where('deleted', 0)
            ->where('visible', 1)
            ->count();

        return response()->json([
            'story' => $count,
        ]);
    }

    /**
     * Moderate content based on Rekognition results
     */
    private function moderateContent(array $rekognitionData): int
    {
        $minConfidence = config('services.aws.rekognition_min_confidence', 75);
        
        // Check for inappropriate content
        foreach ($rekognitionData as $label) {
            if (!isset($label['Confidence'])) continue;
            
            $confidence = $label['Confidence'];
            $name = $label['Name'] ?? '';
            
            // Flag inappropriate labels
            $inappropriateLabels = ['Explicit Nudity', 'Violence', 'Suggestive', 'Drugs'];
            
            if (in_array($name, $inappropriateLabels) && $confidence >= $minConfidence) {
                return 2; // Rejected
            }
        }
        
        return 1; // Approved
    }
}



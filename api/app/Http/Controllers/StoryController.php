<?php

namespace App\Http\Controllers;

use App\Models\UserStory;
use App\Models\User;
use App\Models\UserPhoto;
use App\Traits\ModeratesContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StoryController extends Controller
{
    use ModeratesContent;
    /**
     * Get stories feed
     * Refactored to match original discoverStoriesMobile behavior
     * Uses seed parameter for consistent random ordering (no session needed)
     */
    public function getStories(Request $request)
    {
        $user = $request->user();
        $offset = (int) $request->input('offset', 0);
        
        $lat = (float) ($request->input('lat', $user->lat) ?? 0);
        $lng = (float) ($request->input('lng', $user->lng) ?? 0);
        $looking = (int) $user->s_gender;

        // Get story expiry time - match original: 365*3 days (3 years)
        // Original code: $storyFromDays = 365*3;
        $storyDays = config('dating.plugin_story_days', 365 * 3);
        $expiryTime = now()->subDays($storyDays)->timestamp;
        $time = time();
        
        // Limit: 24 for first page, 23 for subsequent pages
        $limit = ($offset === 0) ? 23 : 12;

        // Get seed from request or generate new one if offset is 0
        // Client should send the seed back for pagination to maintain consistent ordering
        $seed = $request->input('seed');
        if ($offset === 0 || !$seed) {
            $seed = random_int(1, PHP_INT_MAX);
        } else {
            $seed = (int) $seed;
        }

        // Main query: Get latest story per user with all required fields
        // Using Laravel query builder for better maintainability
        $stories = DB::table('users_story as s1')
            ->select([
                's1.id',
                's1.uid',
                's1.story',
                's1.storyType',
                'users.name',
                'users.city',
                'users.age',
                DB::raw("( 6371 * acos(
                    cos( radians({$lat}) ) * cos( radians( users.lat ) )
                    * cos( radians( users.lng ) - radians({$lng}) ) 
                    + sin( radians({$lat}) ) * sin( radians( users.lat ) )
                )) AS distance")
            ])
            ->joinSub(
                DB::table('users_story')
                    ->select('uid', DB::raw('MAX(storyTime) as maxTime'))
                    ->where('visible', 1)
                    ->where('deleted', 0)
                    ->groupBy('uid'),
                's2',
                function ($join) {
                    $join->on('s1.uid', '=', 's2.uid')
                         ->on('s1.storyTime', '=', 's2.maxTime');
                }
            )
            ->leftJoin('users', 'users.id', '=', 's1.uid')
            ->where('s1.visible', 1)
            ->where('s1.deleted', 0)
            ->where('s1.storyTime', '>', $expiryTime)
            ->where('users.gender', $looking)
            ->where('users.id', '!=', $user->id)
            ->orderByRaw("RAND({$seed})")
            ->offset($offset)
            ->limit($limit)
            ->get();

        $json = [];
        $i = 0;

        if (empty($stories)) {
            return response()->json([
                'result' => 'empty',
            ]);
        }

        foreach ($stories as $q) {
            $id = $q->uid;
            $i++;

            // Extract data from query result
            $story = $q->story;
            $storyType = $q->storyType;
            $name = $q->name;
            $city = $q->city;
            $age = $q->age;

            $status = userFilterStatus($id);

            // Extract first name (matching original logic)
            $first_name = explode(' ', trim($name));
            $first_name = explode('_', trim($first_name[0]));

            $fadeDelay = 50 * $i;

            // Build story data matching original format
            $data = [
                'id' => $q->id,
                'uid' => $id,
                'profile_photo' => profilePhoto($id),
                'profile_photo_big' => profilePhoto($id, 1),
                'city' => $city,
                'age' => $age,
                'fadeDelay' => $fadeDelay,
                'video' => ($storyType === 'video') ? 1 : 0,
                'name' => $first_name[0],
                'status' => $status,
                'story' => $story,
            ];

            $json['stories'][] = $data;
        }

        $json['totalDiscoverStories'] = $i;
        $json['seed'] = $seed;

        return response()->json($json);
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
                            'hls_url' => $story->story_src_hls,
                            'time' => $story->storyTime,
                            'views' => 0, // storyViews column doesn't exist in database
                        ];
                    })->toArray(),
                ],
            ],
        ]);
    }

    /**
     * Upload story
     * Matches the logic from requests/belloo.php uploadStory case
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
        $time = time();

        // Check if story review is enabled
        $reviewEnabled = config('dating.plugin_story_review', false);
        $approved = $reviewEnabled ? 0 : 1;

        // Default visibility
        $visible = 1; // approved
        $rekognitionJson = '';
        $hls_url = '';

        // Check AWS Rekognition data if S3 URL
        if (str_contains($path, 's3.') || str_contains($path, 'amazonaws.com')) {
            $fileName = basename($path);
            
            // Wait up to 30 seconds for rekognition data
            $maxWaitTime = 30;
            $startTime = time();
            
            while ((time() - $startTime) < $maxWaitTime) {
                $result = DB::table('reels_aws_upload')
                    ->where('file_name', $fileName)
                    ->first();
                
                if ($result && !empty($result->rekognition)) {
                    $rekognitionJson = $result->rekognition;
                    $hls_url = $result->hls_url ?? '';
                    break; // Data found
                }
                
                sleep(1); // Wait 1 second before retry
            }
            
            // Parse rekognition scores and determine visibility
            if (!empty($rekognitionJson)) {
                $data = json_decode($rekognitionJson, true);
                
                if ($data && is_array($data)) {
                    $visible = $this->moderateContent($data);
                }
            } else {
                $visible = 0; // pending - no rekognition data
            }
        } else {
            $visible = 0; // pending - not S3 URL
        }

        // Create story with lat/lng
        $story = UserStory::create([
            'uid' => $user->id,
            'storyType' => $type,
            'story' => $path,
            'storyTime' => $time,
            'lat' => $user->lat ?? 0,
            'lng' => $user->lng ?? 0,
            'deleted' => 0,
            'visible' => $visible,
            'rekognition' => $rekognitionJson,
            'story_src_hls' => $hls_url,
        ]);

        // Insert into photos table (matching original behavior)
        UserPhoto::create([
            'u_id' => $user->id,
            'time' => $time,
            'photo' => $path,
            'thumb' => $thumb,
            'video' => 0, // Stories are always images
            'story' => $story->id,
            'approved' => $approved,
            'fake' => $user->fake ?? 0,
        ]);

        // Clear cache
        Cache::forget("user_stories_{$user->id}");

        // Return status matching original response format
        $status = 'approved';
        $message = 'Story uploaded successfully!';
        
        if ($visible == 2) {
            $status = 'rejected';
            $message = 'Your story was rejected due to content policy violations.';
        } else if ($visible == 0) {
            $status = 'pending';
            $message = 'Your story is pending review and will be visible after moderation.';
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
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

}



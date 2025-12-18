<?php

namespace App\Http\Controllers;

use App\Models\Reel;
use App\Models\ReelLike;
use App\Models\ReelView;
use App\Models\ReelPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReelController extends Controller
{
    /**
     * Get reels feed
     */
    public function getReels(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 0);
        $customFilter = $request->input('filter', '');
        $trending = $request->input('trending', 'No');

        // Determine looking for gender
        $userGender = $user->gender;
        $lookingFor = $userGender == 1 ? 2 : 1;

        // Build query
        $query = Reel::with('user')
            ->where('visible', 1)
            ->where('gender', $lookingFor);

        // Apply custom filters
        if ($customFilter == 'liked') {
            $likedReelIds = ReelLike::where('uid', $user->id)->pluck('reel');
            $query->whereIn('id', $likedReelIds);
        } elseif ($customFilter == 'purchased') {
            $purchasedReelIds = ReelPurchase::where('uid', $user->id)->pluck('reel');
            $query->whereIn('id', $purchasedReelIds);
        } elseif ($customFilter == 'me') {
            $query->where('uid', $user->id)->where('gender', $userGender);
        }

        // Apply trending filter
        if ($trending == 'Yes') {
            $query->trending();
        }

        // Exclude already played reels (from session/cache)
        $playedReels = Cache::get("played_reels_{$user->id}", []);
        if (!empty($playedReels) && $customFilter != 'me') {
            $query->whereNotIn('id', $playedReels);
        }

        // Order by newest
        $query->orderBy('id', 'desc');

        // Paginate
        $reels = $query->offset($limit)->limit(10)->get();

        $result = $reels->map(function ($reel) use ($user) {
            return $this->formatReel($reel, $user);
        });

        return response()->json([
            'reels' => $result,
        ]);
    }

    /**
     * Upload reel
     */
    public function upload(Request $request)
    {
        $request->validate([
            'path' => 'required|url',
            'caption' => 'nullable|string|max:500',
            'price' => 'required|integer|min:0',
        ]);

        $user = $request->user();
        $path = $request->path;
        $caption = $request->caption ?? '';
        $price = $request->price;

        // Check if review is enabled
        $reviewEnabled = config('dating.video_review_enabled', false);
        $visible = $reviewEnabled ? 0 : 1;

        // AWS Rekognition integration
        $rekognitionJson = '';
        $hls_url = '';

        // If S3 URL, poll for Rekognition results
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

        // Create reel
        $reel = Reel::create([
            'uid' => $user->id,
            'gender' => $user->gender,
            'reel_price' => $price,
            'reel_src' => $path,
            'reel_src_hls' => $hls_url,
            'reel_meta' => $caption,
            'time' => time(),
            'visible' => $visible,
            'rekognition' => $rekognitionJson,
        ]);

        return response()->json([
            'uploaded' => 'OK',
            'status' => $visible == 1 ? 'approved' : ($visible == 2 ? 'rejected' : 'pending'),
            'reel' => $this->formatReel($reel, $user),
        ]);
    }

    /**
     * Update reel
     */
    public function update(int $id, Request $request)
    {
        $request->validate([
            'caption' => 'nullable|string|max:500',
            'price' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();
        
        $reel = Reel::where('id', $id)
            ->where('uid', $user->id)
            ->firstOrFail();

        $reel->update([
            'reel_meta' => $request->caption ?? $reel->reel_meta,
            'reel_price' => $request->price ?? $reel->reel_price,
        ]);

        return response()->json([
            'success' => true,
            'reel' => $this->formatReel($reel->fresh(), $user),
        ]);
    }

    /**
     * Delete reel
     */
    public function delete(int $id, Request $request)
    {
        $user = $request->user();
        
        $reel = Reel::where('id', $id)
            ->where('uid', $user->id)
            ->firstOrFail();

        // Delete related data
        $reel->likes()->delete();
        $reel->views()->delete();
        $reel->purchases()->delete();
        
        // Delete reel
        $reel->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Like/Unlike reel
     */
    public function like(int $id, Request $request)
    {
        $user = $request->user();
        
        $reel = Reel::findOrFail($id);

        // Toggle like
        $existingLike = ReelLike::where('uid', $user->id)
            ->where('reel', $id)
            ->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            $liked = false;
        } else {
            // Like
            ReelLike::create([
                'uid' => $user->id,
                'reel' => $id,
            ]);
            $liked = true;
        }

        $likesCount = ReelLike::where('reel', $id)->count();

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Add view
     */
    public function addView(int $id, Request $request)
    {
        $user = $request->user();
        
        $reel = Reel::findOrFail($id);

        // Check if already viewed
        $alreadyViewed = ReelView::where('uid', $user->id)
            ->where('reel', $id)
            ->exists();

        if (!$alreadyViewed) {
            ReelView::create([
                'uid' => $user->id,
                'reel' => $id,
            ]);

            // Add to played reels cache
            $playedReels = Cache::get("played_reels_{$user->id}", []);
            $playedReels[] = $id;
            Cache::put("played_reels_{$user->id}", array_unique($playedReels), 3600);
        }

        $viewsCount = ReelView::where('reel', $id)->count();

        return response()->json([
            'success' => true,
            'views_count' => $viewsCount,
        ]);
    }

    /**
     * Purchase premium reel
     */
    public function purchase(int $id, Request $request)
    {
        $user = $request->user();
        
        $reel = Reel::findOrFail($id);

        // Check if already purchased
        $alreadyPurchased = ReelPurchase::where('uid', $user->id)
            ->where('reel', $id)
            ->exists();

        if ($alreadyPurchased) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Already purchased',
            ], 422);
        }

        // Check if free
        if ($reel->reel_price == 0) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Reel is free',
            ], 422);
        }

        // Deduct credits
        if (!$user->deductCredits($reel->reel_price, "Purchased reel #{$id}")) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Insufficient credits',
            ], 422);
        }

        // Create purchase record
        ReelPurchase::create([
            'uid' => $user->id,
            'reel' => $id,
            'amount' => $reel->reel_price,
        ]);

        // Transfer credits to reel owner
        $reelOwner = $reel->user;
        if ($reelOwner) {
            $reelOwner->addCredits($reel->reel_price, "Reel #{$id} purchased");
        }

        return response()->json([
            'success' => true,
            'purchased' => true,
            'credits' => $user->fresh()->credits,
        ]);
    }

    /**
     * Format reel for response
     */
    private function formatReel(Reel $reel, $currentUser): array
    {
        $user = $reel->user;

        return [
            'id' => $reel->id,
            'uid' => $reel->uid,
            'reel_src' => $reel->reel_src,
            'reel_src_hls' => $reel->reel_src_hls,
            'reel_meta' => $reel->reel_meta,
            'reel_price' => $reel->reel_price,
            'time' => $reel->time,
            'views' => $reel->views()->count(),
            'likes' => $reel->likes()->count(),
            'liked' => $reel->isLikedBy($currentUser->id),
            'purchased' => $reel->isPurchasedBy($currentUser->id),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => explode(' ', $user->name)[0],
                'age' => $user->age,
                'photo' => profilePhoto($user->id),
                'premium' => $user->premium,
                'verified' => $user->verified,
            ],
        ];
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



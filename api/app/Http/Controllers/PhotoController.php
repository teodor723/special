<?php

namespace App\Http\Controllers;

use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    /**
     * Get user photos
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $photos = UserPhoto::where('u_id', $user->id)
            ->orderBy('profile', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'photo' => $photo->photo,
                    'thumb' => $photo->thumb,
                    'profile' => $photo->profile,
                    'video' => $photo->video,
                    'approved' => $photo->approved,
                    'status' => $photo->status,
                ];
            });

        return response()->json([
            'photos' => $photos,
        ]);
    }

    /**
     * Upload photo
     */
    public function upload(Request $request)
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*.photo' => 'required|url',
            'photos.*.thumb' => 'required|url',
            'photos.*.video' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $uploadedPhotos = [];

        // Check if this is first photo
        $hasProfilePhoto = UserPhoto::where('u_id', $user->id)
            ->where('profile', 1)
            ->exists();

        // Check photo review settings
        $photoReview = config('dating.photo_review_enabled', false);
        $approved = $photoReview ? 0 : 1;

        foreach ($request->photos as $index => $photoData) {
            $isVideo = $photoData['video'] ?? false;
            $isFirstPhoto = ($index === 0 && !$hasProfilePhoto);

            // TODO: Integrate Sightengine for image moderation if enabled
            $moderationStatus = 'Approved';
            
            if (config('dating.sightengine_enabled', false) && !$isVideo) {
                // $moderationResult = $this->moderateImage($photoData['photo']);
                // $approved = $moderationResult['approved'];
                // $moderationStatus = $moderationResult['status'];
            }

            $photo = UserPhoto::create([
                'u_id' => $user->id,
                'photo' => $photoData['photo'],
                'thumb' => $photoData['thumb'],
                'profile' => $isFirstPhoto ? 1 : 0,
                'video' => $isVideo ? 1 : 0,
                'approved' => $approved,
                'time' => time(),
                'fake' => $user->fake,
                'status' => $moderationStatus,
            ]);

            $uploadedPhotos[] = [
                'id' => $photo->id,
                'photo' => $photo->photo,
                'thumb' => $photo->thumb,
                'profile' => $photo->profile,
                'video' => $photo->video,
                'approved' => $photo->approved,
                'status' => $photo->status,
            ];

            // Update user profile photo if first photo
            if ($isFirstPhoto) {
                $user->update(['profile_photo' => $photoData['photo']]);
            }
        }

        // Clear cache
        Cache::forget("user_photo_{$user->id}");
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'photos' => $uploadedPhotos,
        ], 201);
    }

    /**
     * Set photo as main profile photo
     */
    public function setMain(int $id, Request $request)
    {
        $user = $request->user();
        
        $photo = UserPhoto::where('id', $id)
            ->where('u_id', $user->id)
            ->firstOrFail();

        // Remove profile flag from all other photos
        UserPhoto::where('u_id', $user->id)
            ->update(['profile' => 0]);

        // Set this photo as profile
        $photo->update(['profile' => 1]);

        // Update user profile photo
        $user->update(['profile_photo' => $photo->photo]);

        // Clear cache
        Cache::forget("user_photo_{$user->id}");
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'user' => [
                'profile_photo' => $user->profile_photo_url,
                'photos' => userAppPhotos($user->id),
            ],
        ]);
    }

    /**
     * Delete photo
     */
    public function delete(int $id, Request $request)
    {
        $user = $request->user();
        
        $photo = UserPhoto::where('id', $id)
            ->where('u_id', $user->id)
            ->firstOrFail();

        $wasProfilePhoto = $photo->profile;

        // Delete photo
        $photo->delete();

        // If deleted photo was profile photo, set another as profile
        if ($wasProfilePhoto) {
            $newProfilePhoto = UserPhoto::where('u_id', $user->id)
                ->where('approved', 1)
                ->orderBy('id', 'desc')
                ->first();

            if ($newProfilePhoto) {
                $newProfilePhoto->update(['profile' => 1]);
                $user->update(['profile_photo' => $newProfilePhoto->photo]);
            } else {
                $user->update(['profile_photo' => null]);
            }
        }

        // Clear cache
        Cache::forget("user_photo_{$user->id}");
        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'success' => true,
            'user' => [
                'profile_photo' => $user->fresh()->profile_photo_url,
                'photos' => userAppPhotos($user->id),
            ],
        ]);
    }

    /**
     * Moderate image using Sightengine (placeholder)
     */
    private function moderateImage(string $imageUrl): array
    {
        // TODO: Implement Sightengine API integration
        
        if (!config('dating.sightengine_enabled', false)) {
            return [
                'approved' => 1,
                'status' => 'Approved',
            ];
        }

        try {
            $apiUser = config('dating.sightengine_api_user');
            $apiSecret = config('dating.sightengine_api_secret');

            // Call Sightengine API
            // $response = Http::get('https://api.sightengine.com/1.0/check.json', [
            //     'url' => $imageUrl,
            //     'models' => 'nudity-2.0,wad,offensive',
            //     'api_user' => $apiUser,
            //     'api_secret' => $apiSecret,
            // ]);

            // Parse response and determine approval
            // For now, auto-approve
            return [
                'approved' => 1,
                'status' => 'Approved',
            ];

        } catch (\Exception $e) {
            // On error, auto-approve to not block uploads
            return [
                'approved' => 1,
                'status' => 'Approved',
            ];
        }
    }
}



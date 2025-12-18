<?php

namespace App\Http\Controllers;

use App\Models\Spotlight;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SpotlightController extends Controller
{
    /**
     * Get spotlight users
     */
    public function getSpotlight(Request $request)
    {
        $user = $request->user();
        $user->updateLastAccess();

        $limit = config('dating.plugin_spotlight_limit', 50);
        $area = config('dating.plugin_spotlight_area', 'Worldwide');
        $worldwide = config('dating.plugin_spotlight_worldwide', 'Yes');

        $query = Spotlight::query()->active();

        // Apply area filter
        if ($area != 'Worldwide' && $worldwide != 'Yes') {
            $query->nearby($user->lat, $user->lng, $user->s_radius);
        } else {
            $query->nearby($user->lat, $user->lng, 999999); // Worldwide
        }

        $spotlights = $query->limit($limit)->get();

        $result = [];

        foreach ($spotlights as $spotlight) {
            $spotlightUser = User::find($spotlight->u_id);
            
            if (!$spotlightUser) continue;

            $result[] = [
                'id' => $spotlightUser->id,
                'name' => $spotlightUser->name,
                'first_name' => explode(' ', $spotlightUser->name)[0],
                'age' => $spotlightUser->age,
                'city' => $spotlightUser->city,
                'photo' => profilePhoto($spotlightUser->id),
                'premium' => $spotlightUser->premium,
                'verified' => $spotlightUser->verified,
                'status' => $spotlightUser->is_online ? 'y' : 'n',
                'fan' => isFan($user->id, $spotlightUser->id),
                'match' => $spotlightUser->isMatchWith($user->id) ? 1 : 0,
                'distance' => round(calculateDistance(
                    $user->lat,
                    $user->lng,
                    $spotlightUser->lat,
                    $spotlightUser->lng
                ), 1),
            ];
        }

        return response()->json([
            'spotlight' => $result,
        ]);
    }

    /**
     * Add user to spotlight
     */
    public function addToSpotlight(Request $request)
    {
        $user = $request->user();
        $price = config('dating.price_spotlight', 50);

        // Check credits
        if (!$user->hasEnoughCredits($price)) {
            return response()->json([
                'error' => 1,
                'error_m' => 'Insufficient credits',
            ], 422);
        }

        // Deduct credits
        $user->deductCredits($price, 'Spotlight feature');

        // Add to spotlight
        Spotlight::create([
            'u_id' => $user->id,
            'time' => time(),
            'lat' => $user->lat,
            'lng' => $user->lng,
            'photo' => profilePhoto($user->id),
            'lang' => $user->lang,
            'country' => $user->country ?? '-',
            'city' => $user->city ?? '-',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to spotlight successfully',
            'credits' => $user->fresh()->credits,
        ]);
    }
}



<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Spotlight extends Model
{
    protected $table = 'spotlight';
    protected $primaryKey = 'u_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'u_id',
        'time',
        'lat',
        'lng',
        'photo',
        'lang',
        'country',
        'city',
    ];

    protected $casts = [
        'time' => 'integer',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'u_id');
    }

    public function scopeActive($query)
    {
        $expiryTime = now()->subDay()->timestamp;
        return $query->where('time', '>', $expiryTime);
    }

    public function scopeNearby($query, $lat, $lng, $radius = 50)
    {
        return $query->selectRaw("*, 
            ( 6371 * acos( 
                cos( radians(?) ) * 
                cos( radians( lat ) ) * 
                cos( radians( lng ) - radians(?) ) + 
                sin( radians(?) ) * 
                sin( radians( lat ) )
            ) ) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<=', $radius)
            ->orderBy('distance');
    }
}


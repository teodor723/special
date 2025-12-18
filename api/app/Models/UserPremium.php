<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPremium extends Model
{
    protected $table = 'users_premium';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'premium',
        'days',
        'months',
        'credits',
        'time',
    ];

    protected $casts = [
        'premium' => 'integer',
        'days' => 'integer',
        'months' => 'integer',
        'credits' => 'integer',
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function isActive(): bool
    {
        if ($this->months > 0) {
            $expiryDate = date('Y-m-d', strtotime($this->time . ' + ' . $this->months . ' months'));
            return strtotime($expiryDate) > time();
        }
        
        if ($this->days > 0) {
            $expiryDate = $this->time + ($this->days * 86400);
            return $expiryDate > time();
        }
        
        return false;
    }
}


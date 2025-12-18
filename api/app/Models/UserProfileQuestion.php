<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileQuestion extends Model
{
    protected $table = 'users_profile_questions';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'qid',
        'answer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }
}


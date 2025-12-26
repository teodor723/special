<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVideoCall extends Model
{
    protected $table = 'users_videocall';
    public $timestamps = false;

    protected $fillable = [
        'u_id',
        'peer_id',
    ];
}


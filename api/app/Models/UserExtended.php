<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserExtended extends Model
{
    protected $table = 'users_extended';
    public $timestamps = false;

    protected $fillable = [
        'uid',
    ];
}


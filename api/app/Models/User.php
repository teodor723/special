<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'name',
        'username',
        'age',
        'birthday',
        'gender',
        'looking',
        'city',
        'country',
        'lat',
        'lng',
        'bio',
        'bio_url',
        'profile_photo',
        'credits',
        'premium',
        'verified',
        'lang',
        's_age',
        's_gender',
        's_radius',
        'facebook_id',
        'google_id',
        'apple_id',
        'firebase_uid',
        'app_id',
        'last_access',
        'sexy',
        'popular',
        'fake',
        'online_day',
        'meet',
        'admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birthday' => 'date',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'credits' => 'integer',
        'premium' => 'boolean',
        'verified' => 'boolean',
        'fake' => 'boolean',
        'admin' => 'boolean',
    ];

    public $timestamps = false; // Existing DB doesn't use created_at/updated_at

    // Relationships
    public function photos(): HasMany
    {
        return $this->hasMany(UserPhoto::class, 'u_id');
    }

    public function profilePhoto(): HasOne
    {
        return $this->hasOne(UserPhoto::class, 'u_id')->where('profile', 1);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(UserLike::class, 'u1');
    }

    public function likedBy(): HasMany
    {
        return $this->hasMany(UserLike::class, 'u2');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(UserVisit::class, 'u1');
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(UserVisit::class, 'u2');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Chat::class, 's_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Chat::class, 'r_id');
    }

    public function stories(): HasMany
    {
        return $this->hasMany(UserStory::class, 'uid');
    }

    public function reels(): HasMany
    {
        return $this->hasMany(Reel::class, 'uid');
    }

    public function notifications(): HasOne
    {
        return $this->hasOne(UserNotification::class, 'uid');
    }

    public function premiumSubscription(): HasOne
    {
        return $this->hasOne(UserPremium::class, 'uid');
    }

    public function profileQuestions(): HasMany
    {
        return $this->hasMany(UserProfileQuestion::class, 'uid');
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('last_access', '>=', now()->subMinutes(5)->timestamp);
    }

    public function scopeNotFake($query)
    {
        return $query->where('fake', 0);
    }

    public function scopeVerified($query)
    {
        return $query->where('verified', 1);
    }

    public function scopePremium($query)
    {
        return $query->where('premium', 1);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    public function scopeByAgeRange($query, $minAge, $maxAge)
    {
        return $query->whereBetween('age', [$minAge, $maxAge]);
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

    // Accessors & Mutators
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo && !str_contains($this->profile_photo, 'no_user')) {
            return $this->profile_photo;
        }
        
        $profilePhoto = $this->profilePhoto;
        if ($profilePhoto) {
            return $profilePhoto->photo;
        }
        
        return asset('themes/default/img/no-user.png');
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_access >= now()->subMinutes(5)->timestamp;
    }

    public function getAgeRangeAttribute(): array
    {
        $parts = explode(',', $this->s_age);
        return [
            'min' => (int) ($parts[0] ?? 18),
            'max' => (int) ($parts[1] ?? 35),
        ];
    }

    public function getSuperLikesAttribute(): int
    {
        return $this->sexy ?? 0;
    }

    // Helper Methods
    public function hasEnoughCredits(int $amount): bool
    {
        return $this->credits >= $amount;
    }

    public function deductCredits(int $amount, string $reason = 'Credits spent'): bool
    {
        if (!$this->hasEnoughCredits($amount)) {
            return false;
        }

        $this->decrement('credits', $amount);
        
        // Log credit transaction
        UserCredit::create([
            'uid' => $this->id,
            'credits' => $amount,
            'reason' => $reason,
            'time' => time(),
        ]);

        return true;
    }

    public function addCredits(int $amount, string $reason = 'Credits earned'): void
    {
        $this->increment('credits', $amount);
        
        UserCredit::create([
            'uid' => $this->id,
            'credits' => $amount,
            'reason' => $reason,
            'time' => time(),
        ]);
    }

    public function updateLastAccess(): void
    {
        $this->update(['last_access' => time()]);
    }

    public function isFanOf(int $userId): bool
    {
        return $this->likes()
            ->where('u2', $userId)
            ->where('love', 1)
            ->exists();
    }

    public function isMatchWith(int $userId): bool
    {
        return $this->isFanOf($userId) && 
               UserLike::where('u1', $userId)
                   ->where('u2', $this->id)
                   ->where('love', 1)
                   ->exists();
    }

    public function isBlockedBy(int $userId): bool
    {
        return UserBlock::where('uid1', $userId)
            ->where('uid2', $this->id)
            ->exists();
    }

    public function hasBlocked(int $userId): bool
    {
        return UserBlock::where('uid1', $this->id)
            ->where('uid2', $userId)
            ->exists();
    }
}


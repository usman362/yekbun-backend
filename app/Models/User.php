<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements MustVerifyEmail, JWTSubject
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'image',
        'status',
        'level',
        'username',
        'fname',
        'last_name',
        'gender',
        'origin',
        'is_verfied',
        'dob',
        'address',
        'province',
        'city',
        'province_city',
        'country',
        'location',
        'role_id',
        'roles',
        'user_id',
        'user_type',
        'device_name',
        'device_model',
        'device_serial',
        'device_type',
        'device_imei',
        'device_id',
        'fcm_token',
        'is_admin_user',
        'is_superadmin',
        'isPrivacyPolicyAccepted',
        'maritalStatus',
        'phone',
        'is_else',
        'is_language',
        'is_music',
        'family_image',
        'friends_image',
        'friends_request',
        'get_greetings',
        'public_image',
        'search_option',
        'info_banner',
        'new_donation',
        'new_events',
        'new_history',
        'new_music',
        'new_news',
        'new_videos',
        'new_votes',
        'live_stream',
        'play_video',
        'video_call',
        'interview',
        'upload_video',
        'play_music',
        'app_status',
        'congrats_popup',
        'expired_at',
        'subscription_type',
        'force_logout',
        'action_type',
        'action_duration',
        'old_level',
        'old_user_type',
        'deactivated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'action_duration' => 'datetime',
        'deactivated_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->user_id = self::generateCustomId();
        });
    }

    public static function generateCustomId()
    {
        $length = 4;

        while (true) {
            $min = pow(10, $length - 1);
            $max = pow(10, $length) - 1;

            $customId = (string) random_int($min, $max);

            if (!self::where('custom_id', $customId)->exists()) {
                return $customId;
            }

            if (self::whereBetween('custom_id', [$min, $max])->count() >= ($max - $min + 1)) {
                $length++;
            }
        }
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // ─── Relationships ───

    public function feeds()
    {
        return $this->hasMany(Feed::class, 'user_id');
    }

    public function friends()
    {
        return $this->hasMany(UserFriends::class, 'friend_id')->where('user_type', 'friends')->limit(5);
    }

    public function family()
    {
        return $this->hasMany(UserFriends::class, 'friend_id')->where('user_type', 'family')->limit(5);
    }

    public function relations()
    {
        return $this->hasMany(UserFriends::class, 'friend_id');
    }

    public function relationsAsFriend()
    {
        return $this->hasMany(UserFriends::class, 'friend_id');
    }

    public function relationsAsUser()
    {
        return $this->hasMany(UserFriends::class, 'user_id');
    }

    public function block()
    {
        return $this->hasMany(UserFriends::class, 'friend_id')->where('user_type', 'block')->limit(5);
    }

    public function user_requests()
    {
        return $this->hasMany(UserRequest::class, 'request_id')->where('status', 1)->limit(5);
    }

    public function images()
    {
        return $this->hasMany(UserImage::class, 'user_id');
    }

    public function videos()
    {
        return $this->hasMany(UserVideo::class, 'user_id');
    }
}

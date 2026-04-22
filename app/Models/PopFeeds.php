<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class PopFeeds extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'pop_feeds';

    protected $fillable = [
        'user_id', 'title', 'date_start', 'date_ends', 'image', 'audio', 'video',
        'share_option', 'status', 'is_comments', 'is_share', 'is_emoji', 'type', 'limited',
        'is_paypal', 'is_gpay', 'is_pay_office', 'is_pay_other',
        'icon1', 'icon2', 'icon3', 'txt1', 'txt2', 'txt3',
        'allowed_provinces', 'survey_data', 'custom_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($feed) {
            $last = self::orderBy('id', 'desc')->first();
            $lastId = $last ? intval(substr($last->custom_id, 3)) : 99;
            $feed->custom_id = 'PF-' . ($lastId + 1);
        });
    }

    public function comments() { return $this->hasMany(FeedComments::class, 'feed_id'); }
    public function reports() { return $this->hasMany(Report::class, 'reported_post_id', 'id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function likes() { return $this->hasMany(FeedLikes::class, 'feed_id'); }
    public function views() { return $this->hasMany(FeedViews::class, 'feed_id'); }
}

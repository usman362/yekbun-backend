<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "user_id",
        "post_id",
        "parent_id",
        "content",
        "status",
        "type",
        "feed_id",
        "news_id",
        "history_id",
        "vote_id",
        "music_id",
        "emoji_id",
        "audio_path",
        "duration",
        "post_gallery_id",
        "comment_id",
        "is_rply"
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subComments()
    {
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reported_comment_id', 'id');
    }

    public function gallery()
    {
        return $this->belongsTo(PostGallery::class, 'post_gallery_id');
    }

    public function replies(){
        return $this->hasMany(Comment::class , 'comment_id' , 'id');
    }
    public function parent(){
        return $this->belongsTo(Comment::class , 'comment_id');
    }
}

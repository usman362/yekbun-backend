<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CommentsLike extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'comments_likes';

    protected $fillable = ['comment_id', 'user_id', 'emoji'];

    public function feed() { return $this->belongsTo(Feed::class); }
}

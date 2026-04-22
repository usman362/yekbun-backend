<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class CommentsLike extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'comments_likes';

    protected $fillable = ['comment_id', 'user_id', 'emoji'];

    public function feed() { return $this->belongsTo(Feed::class); }
}

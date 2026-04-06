<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ReportComments extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'report_comments';

    protected $fillable = ['user_id', 'report_type', 'comment_id', 'status'];

    public function user() { return $this->belongsTo(User::class); }
    public function comment() { return $this->belongsTo(FeedComments::class, 'comment_id'); }
}

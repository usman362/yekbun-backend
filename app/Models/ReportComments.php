<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ReportComments extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'report_comments';

    protected $fillable = ['user_id', 'report_type', 'comment_id', 'status'];

    public function user() { return $this->belongsTo(User::class); }
    public function comment() { return $this->belongsTo(FeedComments::class, 'comment_id'); }
}

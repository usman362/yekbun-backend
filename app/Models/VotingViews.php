<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class VotingViews extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'voting_views';

    protected $fillable = ['user_id', 'voting_id'];

    public function user() { return $this->belongsTo(User::class); }
    public function voting() { return $this->belongsTo(Voting::class); }
}

<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VotingReaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'voting_reactions';

    protected $fillable = ['user_id', 'voting_id', 'type'];

    public function user() { return $this->belongsTo(User::class); }
    public function voting() { return $this->belongsTo(Voting::class); }
}

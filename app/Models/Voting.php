<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Support\Facades\Auth;

class Voting extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'votings';

    protected $fillable = ['name', 'category_id', 'description', 'image', 'options'];

    protected $casts = [
        'options' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($voting) {
            $lastRecord = self::orderBy('created_at', 'desc')->first();
            $lastId = $lastRecord ? intval(substr($lastRecord->custom_id, 2)) : 99;
            $voting->custom_id = 'VT-' . ($lastId + 1);
        });
    }

    public function voting_category() { return $this->belongsTo(VotingCategory::class, 'category_id'); }
    public function gallery() { return $this->hasMany(PostGallery::class, 'vote_id', '_id'); }
    public function reactions() { return $this->hasMany(VotingReaction::class)->where('user_id', Auth::id()); }
}

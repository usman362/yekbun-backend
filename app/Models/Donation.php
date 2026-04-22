<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Donation extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'donations';

    protected $fillable = [
        'title', 'description', 'organization_id',
        'tags', 'start_date', 'end_date', 'status',
    ];

    protected $casts = [
        'tags' => 'array',
        'start_date' => 'datetime:Y-m-d',
        'end_date' => 'datetime:Y-m-d',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
}

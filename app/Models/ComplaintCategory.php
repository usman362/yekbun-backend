<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/** Admin-managed complaint categories (Roads, Water, Healthcare, …). */
class ComplaintCategory extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'complaint_categories';
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}

<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AppVersion extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'app_versions';

    protected $fillable = ['version_number'];
}

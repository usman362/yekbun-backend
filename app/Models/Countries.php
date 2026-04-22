<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Countries extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'countries';

    protected $fillable = [
        'name', 'code', 'flag_path', 'image_path',
        'icon_code', 'capital_id', 'language_id', 'status', 'conid',
    ];

    public function cities()
    {
        return $this->hasMany(Cities::class, 'country_id', 'conid');
    }
}

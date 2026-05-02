<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class ReportUsers extends Eloquent // ✅ Use MongoDB Eloquent Model
{
    use HasFactory;

    protected $table = 'report_users'; // Optional, 'table' is fine too

    protected $fillable = [
        'report_by',
        'report_type',
        'user_id',
        'status'
    ];
    protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function reportBy()
    {
        return $this->belongsTo(User::class, 'report_by', '_id');
    }

}

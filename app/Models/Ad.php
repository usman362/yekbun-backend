<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ad extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'service_type',
        'payment_type',
        'image_url',
        'destination_url',
        'start_date',
        'end_date',
        'budget',
        'status',
        'country',
        'city',
        'address',
        'code',
        'phone_no'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

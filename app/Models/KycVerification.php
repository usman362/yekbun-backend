<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class KycVerification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'kyc_verifications';

    protected $fillable = [
        'user_id',
        'document_type',
        'front_image',
        'back_image',
        'selfie_image',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}

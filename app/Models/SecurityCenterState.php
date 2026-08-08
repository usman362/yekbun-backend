<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Per-admin Security Center state (2FA / passkeys / sessions / recovery).
 */
class SecurityCenterState extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'security_center_state';

    protected $guarded = [];

    protected $casts = [
        'passkey' => 'boolean',
        'passkey_registered' => 'boolean',
        'authenticator' => 'boolean',
        'email_verify' => 'boolean',
        'sms_verify' => 'boolean',
        'codes_generated' => 'boolean',
        'recovery_codes' => 'array',
        'devices' => 'array',
        'sessions' => 'array',
        'history' => 'array',
        'alerts' => 'array',
    ];

    protected $hidden = [
        'totp_secret',
        'backup_key',
        'recovery_codes',
    ];
}

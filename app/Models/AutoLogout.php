<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Single-row config for the Auto-Logout (inactivity) policy. Read by the appdash
 * InactivityGuard and by the mobile app (to auto-logout inactive sessions).
 */
class AutoLogout extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'auto_logout';

    protected $guarded = [];

    protected $casts = [
        'enabled'         => 'boolean',
        'minutes'         => 'integer',
        'warn_before'     => 'boolean',
        'warn_seconds'    => 'integer',
        'logout_on_close' => 'boolean',
        'exclude_admins'  => 'boolean',
    ];
}

<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Settings for the Zercash / Wallet sub-system.
 *
 * Lives in the `zercash_settings` collection (legacy name kept for parity with the old
 * admin_yekbun project). Rows are keyed by a `key` field (e.g. 'general') with an
 * `is_active` flag so admin can toggle multiple configs without deleting them. Used by
 * WalletApiController::dashboard, KycApiController, and CheckoutApiController to look up
 * `transaction_fee_percent` (cashback %) and `default_currency`.
 */
class ZercashSetting extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'zercash_settings';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'transaction_fee_percent' => 'float',
    ];
}

<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Wallet extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'pin',
        'balance',
        'status',
        'status_reason',
        'welcome_bonus_claimed',
        'expire_at',
        'wallet_number',
    ];

    protected $casts = [
        'balance' => 'float',
        'welcome_bonus_claimed' => 'boolean',
        'expire_at' => 'datetime',
    ];

    /**
     * Every new wallet gets a unique 12-digit account number (shown on the mobile
     * home as "KU-RA XXXX XXXX XXXX"). Assigned automatically so all creation paths
     * (KYC, login, admin, welcome-bonus) get one without duplicating the logic.
     */
    protected static function booted()
    {
        static::creating(function (self $wallet) {
            if (empty($wallet->wallet_number)) {
                $wallet->wallet_number = static::generateUniqueNumber();
            }
        });
    }

    /**
     * Generate a 12-digit numeric wallet number that isn't already in use.
     * First digit is non-zero so it's always exactly 12 digits.
     */
    public static function generateUniqueNumber(): string
    {
        do {
            $number = (string) random_int(1, 9);
            for ($i = 0; $i < 11; $i++) {
                $number .= random_int(0, 9);
            }
        } while (static::where('wallet_number', $number)->exists());

        return $number;
    }

    /**
     * Return this wallet's number, lazily generating + persisting one for legacy
     * wallets created before this field existed (backfill on first read).
     */
    public function ensureWalletNumber(): string
    {
        if (empty($this->wallet_number)) {
            $this->wallet_number = static::generateUniqueNumber();
            $this->save();
        }

        return $this->wallet_number;
    }

    /**
     * The 12-digit number grouped as "XXXX XXXX XXXX" for card-style display.
     */
    public function formattedWalletNumber(): string
    {
        return trim(implode(' ', str_split($this->ensureWalletNumber(), 4)));
    }
}

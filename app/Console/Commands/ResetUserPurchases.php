<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPlaylistGroup;
use App\Models\Wallet;
use Illuminate\Console\Command;

/**
 * Reset a single user's Zercash purchase state so they can re-test from scratch.
 *
 * Deletes their orders / transactions / invoices / cart, re-locks the music playlists
 * a purchase had unlocked (Bronze/Silver/Gold → paid), drops their plan back to the free
 * tier, and sets their Zer wallet balance. Scoped strictly to the given email and refuses
 * to touch admin accounts.
 *
 *   php artisan zercash:reset-user zeedev16@gmail.com --coins=1000
 *   php artisan zercash:reset-user someone@x.com --keep-coins   (reset only, leave balance)
 */
class ResetUserPurchases extends Command
{
    protected $signature = 'zercash:reset-user
        {email : The email of the user to reset}
        {--coins=1000 : Zer balance to set on the wallet}
        {--keep-coins : Do not change the wallet balance}';

    protected $description = "Reset a user's Zercash purchases and set their Zer balance (client testing).";

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        // Never reset an admin / superadmin account.
        if (($user->is_superadmin ?? false) || ($user->is_admin_user ?? false)) {
            $this->error("Refusing to reset an admin/superadmin account: {$email}");
            return self::FAILURE;
        }

        $uid = (string) $user->_id;
        $this->info("Resetting purchases for: {$user->name} <{$email}> ({$uid})");

        $orders = Order::where('user_id', $uid)->delete();
        $txs    = Transaction::where('user_id', $uid)->delete();
        $invs   = Invoice::where('user_id', $uid)->delete();
        $carts  = Cart::where('user_id', $uid)->delete();

        // Re-lock the paid music playlists a purchase had unlocked (leave "Free Playlist").
        $relocked = UserPlaylistGroup::where('user_id', $uid)
            ->whereIn('title', ['Bronze Playlist', 'Silver Playlist', 'Gold Playlist'])
            ->where('type', 'free')
            ->update(['type' => 'paid']);

        // Drop the subscription tier back to the free default.
        $user->user_type  = 'cultivated';
        $user->expired_at = null;
        $user->save();

        // Set the wallet balance (the "give me 1000 coins" part).
        if (!$this->option('keep-coins')) {
            $coins  = (float) $this->option('coins');
            $wallet = Wallet::where('user_id', $uid)->first();
            if (!$wallet) {
                $wallet = new Wallet();
                $wallet->user_id      = $uid;
                $wallet->status       = 'active';
                $wallet->activated_at = now();
            }
            $wallet->balance = $coins;
            $wallet->save();
        }

        $this->table(['What', 'Result'], [
            ['Orders deleted', $orders],
            ['Transactions deleted', $txs],
            ['Invoices deleted', $invs],
            ['Carts cleared', $carts],
            ['Playlists re-locked', $relocked],
            ['Tier reset to', 'cultivated (free)'],
            ['Wallet balance', $this->option('keep-coins') ? 'unchanged' : (float) $this->option('coins') . ' Zer'],
        ]);

        $this->info('Done ✅');
        return self::SUCCESS;
    }
}

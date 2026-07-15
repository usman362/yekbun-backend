<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\LanguageDetail;
use Illuminate\Console\Command;

/**
 * Seed Wallet / ZerCash translation keywords under E-Wallet for every language.
 *
 * Admin Languages UI only edits existing keywords — it cannot create new catalog keys.
 * E-Wallet previously only had the placeholder "Sample Word E-Wallet". This command
 * adds the real wallet UI strings (idempotent per language + keyword + section).
 *
 *   php artisan language:seed-wallet-keywords
 */
class SeedWalletLanguageKeywords extends Command
{
    protected $signature = 'language:seed-wallet-keywords';

    protected $description = 'Add Wallet / ZerCash translation keywords under E-Wallet for every language.';

    /**
     * [keyword, main_section, section_name]
     *
     * main_section = sidebar group ("E-Wallet")
     * section_name = module tab inside that group
     */
    private function keywords(): array
    {
        $main = 'E-Wallet';

        $rows = [
            // Overview
            ['My Wallet', $main, 'Overview'],
            ['Manage your Zer and ZerCash balances.', $main, 'Overview'],
            ['E-Wallet', $main, 'Overview'],
            ['ZerCash', $main, 'Overview'],
            ['Zer', $main, 'Overview'],
            ['Wallet', $main, 'Overview'],
            ['Cêbika Zer', $main, 'Overview'],
            ['Total Zer Balance', $main, 'Overview'],
            ['Total Balance', $main, 'Overview'],
            ['Available Balance', $main, 'Overview'],
            ['Reserve Balance', $main, 'Overview'],
            ['Cashback Balance', $main, 'Overview'],
            ['Loading wallet…', $main, 'Overview'],
            ["Couldn't load your wallet", $main, 'Overview'],
            ['Check your connection or try again. If this keeps happening, sign out and back in.', $main, 'Overview'],
            ['Retry', $main, 'Overview'],
            ['Set up wallet', $main, 'Overview'],
            ['Wallet Dashboard', $main, 'Overview'],

            // Activation / status
            ['Wallet not active yet', $main, 'Status'],
            ['Wallet under review', $main, 'Status'],
            ["We're reviewing your wallet request. You'll get a notification as soon as it's activated.", $main, 'Status'],
            ['Your wallet request was rejected. Please contact support to resubmit.', $main, 'Status'],
            ['Set up your wallet to start charging Zer and earning cashback.', $main, 'Status'],
            ['No wallet found. Please create one.', $main, 'Status'],
            ['Your request is pending review.', $main, 'Status'],
            ['We will review your request. We will get back soon.', $main, 'Status'],
            ['All wallet features are now available.', $main, 'Status'],
            ['Wallet is on Hold. See the reason here.', $main, 'Status'],
            ['Your wallet request was rejected.', $main, 'Status'],
            ['Wallet is Closed. The account will be removed after 90 Days.', $main, 'Status'],
            ['Unknown status.', $main, 'Status'],
            ['Activate Wallet', $main, 'Status'],
            ['Create Wallet', $main, 'Status'],
            ['Wallet Activated', $main, 'Status'],
            ['Wallet Pending', $main, 'Status'],
            ['Wallet On Hold', $main, 'Status'],
            ['Wallet Closed', $main, 'Status'],
            ['Wallet Rejected', $main, 'Status'],

            // Charge / top-up
            ['Charge', $main, 'Charge'],
            ['Charge Zer', $main, 'Charge'],
            ['Select an amount to charge via credit card', $main, 'Charge'],
            ['Continue to Payment', $main, 'Charge'],
            ['Custom', $main, 'Charge'],
            ['Top-up', $main, 'Charge'],
            ['Buy Zer', $main, 'Charge'],
            ['Payment Method', $main, 'Charge'],
            ['Credit Card', $main, 'Charge'],
            ['PayPal', $main, 'Charge'],
            ['Bank Transfer', $main, 'Charge'],
            ['Amount', $main, 'Charge'],
            ['Minimum charge amount is 1 Zer.', $main, 'Charge'],
            ['Payment successful', $main, 'Charge'],
            ['Payment failed', $main, 'Charge'],
            ['Processing payment…', $main, 'Charge'],

            // Transfer / withdraw
            ['Transfer', $main, 'Transfer'],
            ['Withdraw', $main, 'Transfer'],
            ['Send Zer', $main, 'Transfer'],
            ['Receive Zer', $main, 'Transfer'],
            ['Recipient', $main, 'Transfer'],
            ['Wallet Number', $main, 'Transfer'],
            ['Enter wallet number', $main, 'Transfer'],
            ['Confirm Transfer', $main, 'Transfer'],
            ['Transfer successful', $main, 'Transfer'],
            ['Transfer failed', $main, 'Transfer'],
            ['Insufficient balance', $main, 'Transfer'],
            ['Withdrawal request submitted', $main, 'Transfer'],
            ['Payout', $main, 'Transfer'],

            // Cashback / ZerCash rewards
            ['Cashback', $main, 'Cashback'],
            ['ZerCash Rewards', $main, 'Cashback'],
            ['Earn cashback', $main, 'Cashback'],
            ['Cashback earned', $main, 'Cashback'],
            ['Merchant cashback', $main, 'Cashback'],
            ['Partner shops', $main, 'Cashback'],
            ['Up to 15% cashback', $main, 'Cashback'],
            ['What is ZerCash?', $main, 'Cashback'],
            ['ZerCash works like Zer and can be spent on the platform.', $main, 'Cashback'],

            // Transactions
            ['Transaction History', $main, 'Transactions'],
            ['Your recent activity across the platform', $main, 'Transactions'],
            ['All', $main, 'Transactions'],
            ['Charges', $main, 'Transactions'],
            ['Date', $main, 'Transactions'],
            ['Description', $main, 'Transactions'],
            ['Type', $main, 'Transactions'],
            ['Status', $main, 'Transactions'],
            ['No transactions yet.', $main, 'Transactions'],
            ['Completed', $main, 'Transactions'],
            ['Pending', $main, 'Transactions'],
            ['Failed', $main, 'Transactions'],
            ['Earned', $main, 'Transactions'],
            ['Deposit', $main, 'Transactions'],
            ['Expense', $main, 'Transactions'],

            // PIN / security
            ['Wallet PIN', $main, 'Security'],
            ['Set PIN', $main, 'Security'],
            ['Change PIN', $main, 'Security'],
            ['Enter PIN', $main, 'Security'],
            ['Confirm PIN', $main, 'Security'],
            ['PIN set successfully', $main, 'Security'],
            ['Incorrect PIN', $main, 'Security'],
            ['Forgot PIN', $main, 'Security'],

            // KYC
            ['KYC', $main, 'KYC'],
            ['Identity Verification', $main, 'KYC'],
            ['Submit KYC', $main, 'KYC'],
            ['KYC under review', $main, 'KYC'],
            ['KYC approved', $main, 'KYC'],
            ['KYC rejected', $main, 'KYC'],
            ['Upload documents', $main, 'KYC'],
            ['Verification required to activate wallet', $main, 'KYC'],
        ];

        // Keep the original sample key in E-Wallet / E-Wallet so existing rows stay grouped.
        $rows[] = ['Sample Word E-Wallet', $main, 'E-Wallet'];

        return $rows;
    }

    public function handle(): int
    {
        $languages = Language::all();
        if ($languages->isEmpty()) {
            $this->warn('No languages found.');
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $keywords = $this->keywords();

        $this->info('Seeding ' . count($keywords) . ' wallet keywords × ' . $languages->count() . ' languages…');

        foreach ($languages as $language) {
            $langId = (string) $language->_id;
            $label  = $language->title ?? $language->name ?? $language->code ?? $langId;

            foreach ($keywords as [$keyword, $main, $section]) {
                $exists = LanguageDetail::where('language_id', $langId)
                    ->where('keyword', $keyword)
                    ->where('main_section', $main)
                    ->where('section_name', $section)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                LanguageDetail::create([
                    'language_id'  => $langId,
                    'keyword'      => $keyword,
                    'translated'   => '',
                    'main_section' => $main,
                    'section_name' => $section,
                ]);
                $created++;
            }

            $this->line("  · {$label}");
        }

        $this->info("Done. Created: {$created} · already present: {$skipped}");

        return self::SUCCESS;
    }
}

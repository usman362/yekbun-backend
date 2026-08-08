<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\LanguageDetail;
use Illuminate\Console\Command;

/**
 * Seed / update registration month keywords so Kurdish (and other) names
 * are editable in the dashboard Languages UI and available to the mobile app.
 *
 * Kurdish names per Welat (Aug 2026) — first letter capitalized:
 * Çile, Sibat, Adar, Nîsan, Gulan, Hezîran, Tîrmeh, Tebax, Îlon, Cotmeh, Mijdar, Kanûn
 *
 *   php artisan language:seed-registration-months
 *   php artisan language:seed-registration-months --force   # overwrite existing translated values
 */
class SeedRegistrationMonthKeywords extends Command
{
    protected $signature = 'language:seed-registration-months {--force : Overwrite existing translated values}';

    protected $description = 'Seed registration month name keywords (Home Page SignUp) for all languages.';

    private const MAIN = 'Home Page';
    private const SECTION = 'Home Page SignUp';

    /** English keyword => translations by language code */
    private function months(): array
    {
        return [
            'January' => [
                'EN' => 'January', 'KU' => 'Çile', 'DE' => 'Januar', 'AR' => 'كانون الثاني',
                'FR' => 'Janvier', 'TR' => 'Ocak', 'RU' => 'Январь',
            ],
            'February' => [
                'EN' => 'February', 'KU' => 'Sibat', 'DE' => 'Februar', 'AR' => 'شباط',
                'FR' => 'Février', 'TR' => 'Şubat', 'RU' => 'Февраль',
            ],
            'March' => [
                'EN' => 'March', 'KU' => 'Adar', 'DE' => 'März', 'AR' => 'آذار',
                'FR' => 'Mars', 'TR' => 'Mart', 'RU' => 'Март',
            ],
            'April' => [
                'EN' => 'April', 'KU' => 'Nîsan', 'DE' => 'April', 'AR' => 'نيسان',
                'FR' => 'Avril', 'TR' => 'Nisan', 'RU' => 'Апрель',
            ],
            'May' => [
                'EN' => 'May', 'KU' => 'Gulan', 'DE' => 'Mai', 'AR' => 'أيار',
                'FR' => 'Mai', 'TR' => 'Mayıs', 'RU' => 'Май',
            ],
            'June' => [
                'EN' => 'June', 'KU' => 'Hezîran', 'DE' => 'Juni', 'AR' => 'حزيران',
                'FR' => 'Juin', 'TR' => 'Haziran', 'RU' => 'Июнь',
            ],
            'July' => [
                'EN' => 'July', 'KU' => 'Tîrmeh', 'DE' => 'Juli', 'AR' => 'تموز',
                'FR' => 'Juillet', 'TR' => 'Temmuz', 'RU' => 'Июль',
            ],
            'August' => [
                'EN' => 'August', 'KU' => 'Tebax', 'DE' => 'August', 'AR' => 'آب',
                'FR' => 'Août', 'TR' => 'Ağustos', 'RU' => 'Август',
            ],
            'September' => [
                'EN' => 'September', 'KU' => 'Îlon', 'DE' => 'September', 'AR' => 'أيلول',
                'FR' => 'Septembre', 'TR' => 'Eylül', 'RU' => 'Сентябрь',
            ],
            'October' => [
                'EN' => 'October', 'KU' => 'Cotmeh', 'DE' => 'Oktober', 'AR' => 'تشرين الأول',
                'FR' => 'Octobre', 'TR' => 'Ekim', 'RU' => 'Октябрь',
            ],
            'November' => [
                'EN' => 'November', 'KU' => 'Mijdar', 'DE' => 'November', 'AR' => 'تشرين الثاني',
                'FR' => 'Novembre', 'TR' => 'Kasım', 'RU' => 'Ноябрь',
            ],
            'December' => [
                'EN' => 'December', 'KU' => 'Kanûn', 'DE' => 'Dezember', 'AR' => 'كانون الأول',
                'FR' => 'Décembre', 'TR' => 'Aralık', 'RU' => 'Декабрь',
            ],
        ];
    }

    public function handle(): int
    {
        $languages = Language::all();
        if ($languages->isEmpty()) {
            $this->warn('No languages found.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $months = $this->months();

        // Also index by Kurdish month string (mobile MONTHS const) → English / locale
        $byKuKeyword = [];
        foreach ($months as $en => $byCode) {
            $ku = $byCode['KU'];
            $byKuKeyword[$ku] = $byCode;
            $byKuKeyword[$ku]['EN'] = $en;
        }

        $allKeywords = $months + $byKuKeyword;

        $this->info('Seeding ' . count($allKeywords) . ' month keywords × ' . $languages->count() . ' languages…');

        foreach ($languages as $language) {
            $langId = (string) ($language->_id ?? $language->id);
            $code = strtoupper((string) ($language->code ?? ''));
            $label = $language->title ?? $language->name ?? $code ?: $langId;

            foreach ($allKeywords as $keyword => $byCode) {
                // For KU language: Kurdish keyword maps to itself; English keyword → Kurdish name
                if ($code === 'KU') {
                    $translated = $byCode['KU'] ?? $keyword;
                } else {
                    $translated = $byCode[$code] ?? $byCode['EN'] ?? $keyword;
                }

                $row = LanguageDetail::where('language_id', $langId)
                    ->where('keyword', $keyword)
                    ->where('main_section', self::MAIN)
                    ->where('section_name', self::SECTION)
                    ->first();

                if (!$row) {
                    // Also match legacy rows without section filters
                    $row = LanguageDetail::where('language_id', $langId)
                        ->where('keyword', $keyword)
                        ->first();
                }

                if ($row) {
                    $shouldUpdate = $force
                        || $row->translated === ''
                        || $row->translated === null
                        || ($code === 'KU' && $row->translated !== $translated);

                    if ($shouldUpdate) {
                        $row->translated = $translated;
                        $row->main_section = self::MAIN;
                        $row->section_name = self::SECTION;
                        $row->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                LanguageDetail::create([
                    'language_id' => $langId,
                    'keyword' => $keyword,
                    'translated' => $translated,
                    'main_section' => self::MAIN,
                    'section_name' => self::SECTION,
                ]);
                $created++;
            }

            $this->line("  · {$label} ({$code})");
        }

        $this->info("Done. Created: {$created} · updated: {$updated} · skipped: {$skipped}");

        return self::SUCCESS;
    }
}

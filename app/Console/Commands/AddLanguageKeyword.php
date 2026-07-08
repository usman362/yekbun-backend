<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\LanguageDetail;
use Illuminate\Console\Command;

/**
 * Add a translation keyword to a section for EVERY language.
 *
 * Keywords live in the `language_details` collection (one row per language),
 * grouped by main_section (sidebar) + section_name (module tabs). There is no
 * "add keyword" button in the admin UI — it only edits existing translations —
 * so a new keyword has to be inserted for each language via this command. It is
 * idempotent: languages that already have the keyword in that section are skipped.
 *
 * Default adds "Your Birthday" to the signup screen (Home Page → Home Page SignUp):
 *
 *   php artisan language:add-keyword
 *   php artisan language:add-keyword "Your Birthday" --main="Home Page" --section="Home Page SignUp"
 */
class AddLanguageKeyword extends Command
{
    protected $signature = 'language:add-keyword
        {keyword=Your Birthday : The keyword text to add}
        {--main=Home Page : main_section (sidebar group)}
        {--section=Home Page SignUp : section_name (module tab)}';

    protected $description = 'Add a translation keyword to a section for every language (idempotent).';

    public function handle(): int
    {
        $keyword = trim((string) $this->argument('keyword'));
        $main    = (string) $this->option('main');
        $section = (string) $this->option('section');

        if ($keyword === '') {
            $this->error('Keyword cannot be empty.');
            return self::FAILURE;
        }

        $languages = Language::all();
        if ($languages->isEmpty()) {
            $this->warn('No languages found.');
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($languages as $language) {
            $langId = (string) $language->_id;

            $exists = LanguageDetail::where('language_id', $langId)
                ->where('keyword', $keyword)
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

        $this->info("Keyword \"{$keyword}\" → [{$main} / {$section}]");
        $this->info("Languages: {$languages->count()} · created: {$created} · already present: {$skipped}");

        return self::SUCCESS;
    }
}

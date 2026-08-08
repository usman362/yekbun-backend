<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Rename province Bakûr → Bakur, enforce registration province order, and
 * replace Kurdistan cities from database/data/kurdistan_cities.json (Excel export).
 *
 * Province order (Welat): Bakur → Başûr → Rojava → Rojhilat
 *
 *   php artisan kurdistan:sync-cities
 *   php artisan kurdistan:sync-cities --force
 */
class SyncKurdistanCities extends Command
{
    protected $signature = 'kurdistan:sync-cities
                            {--force : Required to delete existing Kurdistan cities and re-import}
                            {--file= : Optional path to kurdistan_cities.json}';

    protected $description = 'Sync Kurdistan provinces (Bakur spelling + order) and cities from Excel JSON seed.';

    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->error('Refusing to replace cities without --force (destructive).');
            $this->line('Run: php artisan kurdistan:sync-cities --force');
            return self::FAILURE;
        }

        $path = $this->option('file') ?: database_path('data/kurdistan_cities.json');
        if (!is_readable($path)) {
            $this->error("Seed file not readable: {$path}");
            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($path), true);
        if (!is_array($payload) || $payload === []) {
            $this->error('Invalid or empty kurdistan_cities.json');
            return self::FAILURE;
        }

        $country = Country::where('name', 'Kurdistan')
            ->orWhere('name', 'like', '%Kurdistan%')
            ->first();

        if (!$country) {
            $this->error('Kurdistan country not found in countries collection.');
            return self::FAILURE;
        }

        $countryId = (string) ($country->_id ?? $country->id);
        $this->info("Kurdistan country_id={$countryId}");

        // Rename Bakûr → Bakur on region + user.province
        $renamed = Region::where('name', 'Bakûr')->where('country_id', $countryId)->update(['name' => 'Bakur']);
        $usersUpdated = User::where('province', 'Bakûr')->update(['province' => 'Bakur']);
        $this->line("  Regions renamed Bakûr→Bakur: {$renamed}");
        $this->line("  Users province Bakûr→Bakur: {$usersUpdated}");

        $regionIds = [];
        foreach ($payload as $block) {
            $name = $block['province'] ?? null;
            $sort = (int) ($block['sort_order'] ?? 0);
            if (!$name) {
                continue;
            }

            // Accept legacy spelling while upserting
            $region = Region::where('country_id', $countryId)
                ->where(function ($q) use ($name) {
                    $q->where('name', $name);
                    if ($name === 'Bakur') {
                        $q->orWhere('name', 'Bakûr');
                    }
                })
                ->first();

            if (!$region) {
                $shortcodes = [
                    'Bakur' => 'KU-BK',
                    'Başûr' => 'KU-BŞ',
                    'Rojava' => 'KU-RA',
                    'Rojhilat' => 'KU-RH',
                ];
                $region = Region::create([
                    'name' => $name,
                    'country_id' => $countryId,
                    'shortcode' => $shortcodes[$name] ?? null,
                    'sort_order' => $sort,
                    'status' => 'active',
                ]);
                $this->line("  Created region: {$name}");
            } else {
                $region->name = $name;
                $region->sort_order = $sort;
                if (empty($region->shortcode)) {
                    $shortcodes = [
                        'Bakur' => 'KU-BK',
                        'Başûr' => 'KU-BŞ',
                        'Rojava' => 'KU-RA',
                        'Rojhilat' => 'KU-RH',
                    ];
                    $region->shortcode = $shortcodes[$name] ?? $region->shortcode;
                }
                $region->save();
            }

            $regionIds[$name] = (string) ($region->_id ?? $region->id);
            $this->line("  Region {$name} id={$regionIds[$name]} sort={$sort} cities_in_file=" . count($block['cities'] ?? []));
        }

        // Wipe existing Kurdistan registration cities
        $deleted = City::where('country_id', $countryId)->delete();
        $this->warn("Deleted existing cities_orig for Kurdistan: {$deleted}");

        $inserted = 0;
        foreach ($payload as $block) {
            $name = $block['province'] ?? null;
            $regionId = $regionIds[$name] ?? null;
            if (!$regionId) {
                continue;
            }
            foreach ($block['cities'] ?? [] as $city) {
                $cityName = trim((string) ($city['name'] ?? ''));
                if ($cityName === '') {
                    continue;
                }
                City::create([
                    'name' => $cityName,
                    'zipcode' => isset($city['zipcode']) ? (string) $city['zipcode'] : null,
                    'country_id' => $countryId,
                    'region_id' => $regionId,
                    'status' => 'active',
                ]);
                $inserted++;
            }
        }

        $this->info("Inserted {$inserted} cities.");

        foreach ($regionIds as $name => $id) {
            $count = City::where('region_id', $id)->count();
            $this->line("  {$name}: {$count} cities");
        }

        // Ensure Region model exposes sort_order for future admin edits
        $this->line('Provinces order: ' . implode(' → ', array_keys($regionIds)));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\LoconetState;
use Illuminate\Console\Command;

/**
 * Seed LoCoNet dashboard snapshot from database/data/loconet_seed.json.
 *
 *   php artisan loconet:seed-defaults
 *   php artisan loconet:seed-defaults --force
 */
class SeedLoconetDefaults extends Command
{
    protected $signature = 'loconet:seed-defaults {--force : Replace existing snapshot}';

    protected $description = 'Seed LoCoNet dashboard snapshot (chats, calls, streams, minutes, settings).';

    public function handle(): int
    {
        $path = database_path('data/loconet_seed.json');
        if (!is_readable($path)) {
            $this->error("Seed file missing: {$path}");
            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($path), true);
        if (!is_array($payload) || $payload === []) {
            $this->error('Invalid loconet_seed.json');
            return self::FAILURE;
        }

        $projectId = (string) ($payload['project_id'] ?? 'yekbun-prod-01');
        $existing = LoconetState::where('project_id', $projectId)->first();

        if ($existing && !$this->option('force')) {
            $this->info("Snapshot already exists for {$projectId}. Use --force to replace.");
            return self::SUCCESS;
        }

        if ($existing) {
            $existing->fill($payload);
            $existing->project_id = $projectId;
            $existing->save();
            $this->info("Updated LoCoNet snapshot ({$projectId}).");
            return self::SUCCESS;
        }

        $payload['project_id'] = $projectId;
        LoconetState::create($payload);
        $this->info("Created LoCoNet snapshot ({$projectId}).");

        return self::SUCCESS;
    }
}

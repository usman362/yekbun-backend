<?php

namespace App\Console\Commands;

use App\Models\AgentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seed Agent Event Center queue from database/data/event_center_seed.json.
 *
 *   php artisan event-center:seed-defaults
 *   php artisan event-center:seed-defaults --force
 */
class SeedEventCenterDefaults extends Command
{
    protected $signature = 'event-center:seed-defaults {--force : Replace all existing agent events}';

    protected $description = 'Seed Agent Event Center queue (EVT-* samples for dashboard).';

    public function handle(): int
    {
        $path = database_path('data/event_center_seed.json');
        if (!is_readable($path)) {
            $this->error("Seed file missing: {$path}");
            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($path), true);
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];
        if ($events === []) {
            $this->error('Invalid or empty event_center_seed.json');
            return self::FAILURE;
        }

        if ($this->option('force')) {
            AgentEvent::query()->delete();
            $this->warn('Cleared agent_events (--force).');
        } elseif (AgentEvent::count() > 0) {
            $this->info('agent_events already has ' . AgentEvent::count() . ' rows. Use --force to replace.');
            return self::SUCCESS;
        }

        $now = now();
        $inserted = 0;
        foreach ($events as $raw) {
            if (!is_array($raw) || empty($raw['id'])) {
                continue;
            }
            $offset = (int) ($raw['offset_minutes'] ?? 0);
            $created = $now->copy()->subMinutes($offset);
            $row = [
                'event_key' => (string) $raw['id'],
                'type' => (string) ($raw['type'] ?? 'review_required'),
                'worker' => (string) ($raw['worker'] ?? 'moderator'),
                'platform' => (string) ($raw['platform'] ?? 'web'),
                'preview' => (string) ($raw['preview'] ?? ''),
                'language' => (string) ($raw['language'] ?? 'en'),
                'priority' => (string) ($raw['priority'] ?? 'medium'),
                'status' => (string) ($raw['status'] ?? 'new'),
                'thread_id' => $raw['thread_id'] ?? null,
                'post_id' => $raw['post_id'] ?? null,
                'original_post' => $raw['original_post'] ?? null,
                'agent_comment' => $raw['agent_comment'] ?? null,
                'user_reply' => $raw['user_reply'] ?? null,
                'thread_messages' => $raw['thread_messages'] ?? [],
                'sentiment' => $raw['sentiment'] ?? null,
                'risk_level' => $raw['risk_level'] ?? null,
                'payload' => $raw['payload'] ?? [],
                'claimed_by' => $raw['claimed_by'] ?? null,
                'claimed_at' => !empty($raw['claimed_by']) ? $created->copy()->addMinutes(1) : null,
                'result' => $raw['result'] ?? null,
                'task' => $raw['task'] ?? null,
                'is_duplicate' => (bool) ($raw['is_duplicate'] ?? false),
                'activity_log' => $raw['activity_log'] ?? [
                    ['action' => 'Event created', 'timestamp' => $created->toIso8601String()],
                ],
                'created_at_event' => $created,
            ];
            AgentEvent::create($row);
            $inserted++;
        }

        $this->info("Inserted {$inserted} agent events.");
        return self::SUCCESS;
    }
}

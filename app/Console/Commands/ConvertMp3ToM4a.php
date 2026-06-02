<?php

namespace App\Console\Commands;

use App\Helpers\Helpers;
use App\Models\Comment;
use App\Models\FeedComments;
use App\Models\PopFeeds;
use App\Models\Ringtone;
use App\Models\Song;
use App\Services\BunnyCDNService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * One-shot migration to convert all legacy `.mp3` URLs in the database to `.m4a`.
 *
 * Why: Android can't trim raw MP3 frames natively, so the mobile app needs `.m4a`
 * (AAC in an MP4 container) for its in-app trimming flow. The upload pipeline
 * (Helpers::fileCDNUpload) was already producing m4a content, but it was stored
 * with a `.mp3` extension — so the mobile app rejected it on filename. This command
 * cleans up the existing rows.
 *
 * For every row across {Song, Comment, FeedComments, PopFeeds, Ringtone}:
 *   1. Download the file from BunnyCDN
 *   2. Re-encode to AAC/m4a via ffmpeg (idempotent — already-m4a content is also
 *      re-encoded cheaply with the AAC codec, which is fine)
 *   3. Upload the new `.m4a` file alongside the old one
 *   4. Point the DB row at the new URL
 *   5. Delete the old `.mp3` from BunnyCDN
 *
 * Safe to interrupt: each row is processed independently and committed before the
 * next one. Re-run anytime — already-converted rows (URL ending in .m4a) are skipped.
 *
 * Usage:
 *   php artisan audio:convert-mp3-to-m4a                  # process everything
 *   php artisan audio:convert-mp3-to-m4a --dry-run        # log only, no changes
 *   php artisan audio:convert-mp3-to-m4a --model=Song     # one model at a time
 *   php artisan audio:convert-mp3-to-m4a --limit=50       # batch size cap
 *   php artisan audio:convert-mp3-to-m4a --keep-old       # don't delete .mp3 from CDN
 */
class ConvertMp3ToM4a extends Command
{
    protected $signature = 'audio:convert-mp3-to-m4a
                            {--dry-run : Show what would be converted without changing anything}
                            {--model= : Restrict to one model (Song|Comment|FeedComments|PopFeeds|Ringtone)}
                            {--limit= : Max rows to process across all models (default: unlimited)}
                            {--keep-old : Keep the original .mp3 on the CDN after conversion}';

    protected $description = 'Convert legacy .mp3 audio URLs in the database to .m4a (AAC).';

    /**
     * Each entry: [Model class, audio-URL column name].
     * Add new (model, column) pairs here if more collections start storing audio.
     */
    private array $targets = [
        [Song::class,         'audio'],
        [Comment::class,      'audio_path'],
        [FeedComments::class, 'audio'],
        [PopFeeds::class,     'audio'],
        [Ringtone::class,     'filePath'],
    ];

    private BunnyCDNService $bunny;

    public function handle(): int
    {
        $this->bunny = new BunnyCDNService();

        $dryRun     = (bool) $this->option('dry-run');
        $onlyModel  = $this->option('model');
        $limit      = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $keepOld    = (bool) $this->option('keep-old');

        $this->info($dryRun ? '🧪 DRY RUN — no files will be changed.' : '🚀 Live run — converting files.');

        // Quick guard: ffmpeg present?
        $ffmpeg = trim((string) @shell_exec('which ffmpeg'));
        if ($ffmpeg === '' && !$dryRun) {
            $this->error('❌ ffmpeg not installed on this server. Install it before running live.');
            return self::FAILURE;
        }

        $totals = ['scanned' => 0, 'skipped' => 0, 'converted' => 0, 'failed' => 0];
        $processed = 0;

        foreach ($this->targets as [$modelClass, $column]) {
            $short = class_basename($modelClass);
            if ($onlyModel && strcasecmp($onlyModel, $short) !== 0) {
                continue;
            }

            $this->newLine();
            $this->line("─── {$short}.{$column} ───");

            // Match rows whose audio URL ends in .mp3 (case-insensitive). MongoDB regex.
            $query = $modelClass::where($column, 'regexp', '/\.mp3$/i');

            $count = $query->count();
            $this->line("Found {$count} row(s) with .mp3 URLs.");
            if ($count === 0) continue;

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $query->orderBy('_id')->chunk(100, function ($rows) use ($column, $dryRun, $keepOld, &$totals, &$processed, $limit, $bar) {
                foreach ($rows as $row) {
                    if ($limit !== null && $processed >= $limit) return false;
                    $processed++;
                    $totals['scanned']++;

                    $url = (string) $row->{$column};
                    if ($url === '' || !Str::endsWith(strtolower($url), '.mp3')) {
                        $totals['skipped']++;
                        $bar->advance();
                        continue;
                    }

                    try {
                        $newUrl = $this->convertOne($url, $dryRun);

                        if ($dryRun) {
                            $totals['converted']++;
                        } elseif ($newUrl !== null) {
                            $row->{$column} = $newUrl;
                            $row->save();
                            if (!$keepOld) {
                                $this->deleteOldMp3($url);
                            }
                            $totals['converted']++;
                        } else {
                            $totals['failed']++;
                        }
                    } catch (\Throwable $e) {
                        $totals['failed']++;
                        // Don't blow up the whole run on one bad row — log + continue.
                        $this->newLine();
                        $this->warn("  ✗ {$row->_id}: " . $e->getMessage());
                    }

                    $bar->advance();
                }
                return $limit === null || $processed < $limit;
            });

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->info('────────────────────────────');
        $this->info("Scanned:   {$totals['scanned']}");
        $this->info("Converted: {$totals['converted']}");
        $this->warn("Skipped:   {$totals['skipped']}");
        $this->error("Failed:    {$totals['failed']}");
        $this->info('────────────────────────────');
        $this->info($dryRun ? 'Dry run complete. Re-run without --dry-run to apply.' : 'Done.');

        return self::SUCCESS;
    }

    /**
     * Download the .mp3 from CDN, re-encode to .m4a, upload alongside the old file.
     * Returns the new CDN URL on success, null on failure. In dry-run mode just
     * logs the plan and returns the (synthetic) target URL.
     */
    private function convertOne(string $mp3Url, bool $dryRun): ?string
    {
        $m4aUrl = preg_replace('/\.mp3$/i', '.m4a', $mp3Url);

        if ($dryRun) {
            $this->newLine();
            $this->line("  • {$mp3Url}");
            $this->line("    → {$m4aUrl}");
            return $m4aUrl;
        }

        // Resolve CDN URL → storage path (the part after BUNNY_CDN_URL/).
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');
        if ($cdnBase === '' || !Str::startsWith($mp3Url, $cdnBase . '/')) {
            throw new \RuntimeException('URL is not under the configured BUNNY_CDN_URL');
        }
        $storagePath = Str::after($mp3Url, $cdnBase . '/');
        $folder      = trim(dirname($storagePath), '/');
        $newFilename = basename(preg_replace('/\.mp3$/i', '.m4a', $storagePath));

        // 1) Download the source file
        $tmpDir = storage_path('app/audio_convert_tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $tmpIn  = $tmpDir . '/' . uniqid('in_', true) . '.mp3';
        $tmpOut = $tmpDir . '/' . uniqid('out_', true) . '.m4a';

        $resp = Http::timeout(60)->get($mp3Url);
        if (!$resp->successful()) {
            $this->cleanup([$tmpIn, $tmpOut]);
            throw new \RuntimeException("download HTTP {$resp->status()}");
        }
        file_put_contents($tmpIn, $resp->body());

        // 2) Re-encode to AAC/m4a (idempotent — works even if content is already AAC).
        $ok = Helpers::convertToM4A($tmpIn, $tmpOut);
        if (!$ok || !file_exists($tmpOut) || filesize($tmpOut) === 0) {
            $this->cleanup([$tmpIn, $tmpOut]);
            throw new \RuntimeException('ffmpeg conversion produced no output');
        }

        // 3) Upload to the same folder with the new filename.
        $newCdnUrl = $this->bunny->upload(
            $folder,
            $newFilename,
            file_get_contents($tmpOut),
            'audio/mp4'
        );

        $this->cleanup([$tmpIn, $tmpOut]);
        return $newCdnUrl;
    }

    /** Delete the legacy .mp3 file from the CDN. Soft-fails on errors (logged, not thrown). */
    private function deleteOldMp3(string $mp3Url): void
    {
        try {
            $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');
            if (!Str::startsWith($mp3Url, $cdnBase . '/')) return;
            $storagePath = env('BUNNY_STORAGE_ZONE') . '/' . Str::after($mp3Url, $cdnBase . '/');
            $this->bunny->delete($storagePath);
        } catch (\Throwable $e) {
            // Don't fail the migration if cleanup hiccups — orphan files can be GC'd later.
            $this->warn('  (could not delete old .mp3: ' . $e->getMessage() . ')');
        }
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            if ($p && file_exists($p)) @unlink($p);
        }
    }
}

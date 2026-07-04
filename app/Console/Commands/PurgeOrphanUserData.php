<?php

namespace App\Console\Commands;

use App\Models\Clips;
use App\Models\Feed;
use App\Models\FeedComments;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\UserFriends;
use App\Models\UserImage;
use App\Models\UserVideo;
use App\Models\Wallet;
use App\Services\BunnyCDNService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scan every user-referencing collection and delete anything that belongs to a user who no
 * longer exists — the DB rows AND their uploaded media on the CDN. Useful after users were
 * removed (or the users collection was lost) and their content is now orphaned.
 *
 *   php artisan users:purge-orphans --dry-run     # report only, deletes NOTHING
 *   php artisan users:purge-orphans               # asks to confirm, then purges
 *   php artisan users:purge-orphans --yes         # purge without the prompt
 */
class PurgeOrphanUserData extends Command
{
    protected $signature = 'users:purge-orphans {--dry-run : Report what would be deleted, change nothing} {--yes : Skip the confirmation prompt}';

    protected $description = 'Delete data (DB rows + CDN media) belonging to users that no longer exist.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Set of existing user ids for O(1) lookups. If the collection is empty, EVERYTHING
        // that references a user is orphaned.
        $existing = User::pluck('_id')->map(fn($id) => (string) $id)->flip();
        $this->info(($dry ? '[DRY RUN] ' : '') . "Existing users: {$existing->count()}");

        $isOrphan = fn($uid) => $uid !== null && $uid !== '' && !$existing->has((string) $uid);

        if (!$dry && !$this->option('yes')) {
            $this->warn('This permanently deletes orphaned rows AND their CDN media. Run with --dry-run first.');
            if (!$this->confirm('Proceed with deletion?')) {
                $this->line('Aborted.');
                return self::SUCCESS;
            }
        }

        $bunny   = new BunnyCDNService();
        $cdnBase = (string) env('BUNNY_CDN_URL');
        $mediaDeleted = 0;
        $mediaFailed  = 0;

        $del = function ($path) use ($bunny, $cdnBase, $dry, &$mediaDeleted, &$mediaFailed) {
            if (empty($path) || !is_string($path)) return;
            $rel = ltrim($cdnBase !== '' ? Str::after($path, $cdnBase) : $path, '/');
            if ($rel === '' || Str::startsWith($rel, ['http://', 'https://'])) return;
            if ($dry) { $mediaDeleted++; return; }
            try { $bunny->delete($rel); $mediaDeleted++; } catch (\Throwable $e) { $mediaFailed++; }
        };

        $rowCounts = [];

        // ── Collections that carry CDN media: walk each doc, drop its files, then its row. ──
        $mediaModels = [
            [Feed::class, function ($f) {
                $p = [];
                foreach ((array) ($f->images ?? []) as $i) $p[] = is_array($i) ? ($i['path'] ?? null) : null;
                foreach ((array) ($f->videos ?? []) as $v) $p[] = is_array($v) ? ($v['path'] ?? null) : null;
                return $p;
            }],
            [Clips::class,           fn($c) => [$c->clip, $c->thumbnail]],
            [UserImage::class,       fn($x) => [$x->image]],
            [UserVideo::class,       fn($x) => [$x->video]],
            [FeedComments::class,    fn($x) => [$x->audio, $x->image]],
            [KycVerification::class, fn($x) => [$x->front_image, $x->back_image, $x->selfie_image]],
        ];

        foreach ($mediaModels as [$model, $extract]) {
            $orphanIds = [];
            $model::chunk(200, function ($docs) use ($isOrphan, $extract, $del, &$orphanIds) {
                foreach ($docs as $doc) {
                    if (!$isOrphan($doc->user_id ?? null)) continue;
                    foreach ((array) $extract($doc) as $path) $del($path);
                    $orphanIds[] = (string) $doc->_id;
                }
            });
            $rowCounts[class_basename($model)] = count($orphanIds);
            if (!$dry && $orphanIds) {
                foreach (array_chunk($orphanIds, 500) as $batch) {
                    $model::whereIn('_id', $batch)->delete();
                }
            }
        }

        // ── Plain collections: bulk-delete every row whose user_id is orphaned. ──
        $plainModels = [
            Wallet::class,
            \App\Models\Comment::class, \App\Models\FeedLikes::class, \App\Models\FeedViews::class,
            \App\Models\FeedShare::class, \App\Models\ClipsLikes::class, \App\Models\ClipsViews::class,
            \App\Models\CommentsLike::class, \App\Models\Transaction::class, \App\Models\UserPlaylist::class,
            \App\Models\UserPlaylistGroup::class, \App\Models\ArtistFavorite::class, \App\Models\MusicPlay::class,
            \App\Models\VideoPlay::class, \App\Models\SongViews::class, \App\Models\VideoClipViews::class,
            \App\Models\NotificationCenter::class, \App\Models\UserCode::class, \App\Models\UserImei::class,
            \App\Models\Report::class, \App\Models\ReportComments::class, \App\Models\ReportFeeds::class,
            \App\Models\ReportUsers::class, \App\Models\Media::class,
        ];

        foreach ($plainModels as $model) {
            try {
                $orphans = $model::pluck('user_id')->filter()->unique()->filter($isOrphan)->values()->all();
                $cnt = 0;
                if ($orphans) {
                    $cnt = $model::whereIn('user_id', $orphans)->count();
                    if (!$dry) {
                        foreach (array_chunk($orphans, 500) as $batch) {
                            $model::whereIn('user_id', $batch)->delete();
                        }
                    }
                }
                $rowCounts[class_basename($model)] = $cnt;
            } catch (\Throwable $e) {
                $rowCounts[class_basename($model)] = 'skip (' . $e->getMessage() . ')';
            }
        }

        // ── Friendships: orphaned on EITHER side (user_id or friend_id). ──
        $friendOrphans = 0;
        UserFriends::chunk(500, function ($rows) use ($isOrphan, $dry, &$friendOrphans) {
            $ids = [];
            foreach ($rows as $r) {
                if ($isOrphan($r->user_id ?? null) || $isOrphan($r->friend_id ?? null)) $ids[] = (string) $r->_id;
            }
            $friendOrphans += count($ids);
            if (!$dry && $ids) UserFriends::whereIn('_id', $ids)->delete();
        });
        $rowCounts['UserFriends'] = $friendOrphans;

        // ── Report ──
        $this->newLine();
        $this->table(['Collection', $dry ? 'Would delete' : 'Deleted'], collect($rowCounts)
            ->map(fn($v, $k) => [$k, is_int($v) ? number_format($v) : $v])
            ->values()->all());

        $totalRows = collect($rowCounts)->filter(fn($v) => is_int($v))->sum();
        $this->newLine();
        $this->info(($dry ? 'Would delete' : 'Deleted') . " {$totalRows} orphan rows and {$mediaDeleted} CDN files.");
        if ($mediaFailed) $this->warn("{$mediaFailed} CDN files could not be deleted (see BunnyCDN).");
        if ($dry) $this->comment('Nothing was changed. Re-run without --dry-run to apply.');

        return self::SUCCESS;
    }
}

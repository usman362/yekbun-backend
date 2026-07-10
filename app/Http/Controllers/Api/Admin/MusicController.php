<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistFavorite;
use App\Models\MusicCategory;
use App\Models\Region;
use App\Models\Song;
use App\Models\SongViews;
use App\Models\VideoClip;
use App\Services\BunnyCDNService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MusicController extends Controller
{
    public function artists(Request $request)
    {
        $search = $request->get('search', '');
        $sort   = $request->get('sort', 'name');

        $query = Artist::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $artists = $query->orderBy($sort === 'name' ? 'name' : 'created_at', $sort === 'name' ? 'asc' : 'desc')->get();

        $artistIds = $artists->pluck('_id')->toArray();

        $songCounts = Song::whereIn('artist_id', $artistIds)
            ->get()
            ->groupBy('artist_id')
            ->map(fn($g) => $g->count());

        $clipCounts = VideoClip::whereIn('artist_id', $artistIds)
            ->get()
            ->groupBy('artist_id')
            ->map(fn($g) => $g->count());

        $favCounts = ArtistFavorite::whereIn('artist_id', $artistIds)
            ->get()
            ->groupBy('artist_id')
            ->map(fn($g) => $g->count());

        $listenCounts = SongViews::whereIn('artist_id', $artistIds)
            ->get()
            ->groupBy('artist_id')
            ->map(fn($g) => $g->count());

        $provinceIds = $artists->pluck('province_id')->unique()->filter()->toArray();
        $provinces = Region::whereIn('_id', $provinceIds)->get()->keyBy(fn($r) => (string) $r->_id);

        $result = $artists->map(function ($a) use ($songCounts, $clipCounts, $favCounts, $listenCounts, $provinces) {
            $songs = $songCounts->get($a->_id, 0);
            $clips = $clipCounts->get($a->_id, 0);
            $likes = $favCounts->get($a->_id, 0);
            $listens = (int) ($listenCounts->get((string) $a->_id, $listenCounts->get($a->_id, 0)));
            $provinceName = $a->province_id ? ($provinces->get((string) $a->province_id)->name ?? '') : '';

            return [
                'id'          => $a->_id,
                'name'        => $a->name ?? '',
                'gender'      => $a->gender ?? '',
                'province_id' => (string) ($a->province_id ?? ''),
                'province'    => $provinceName,
                'region'      => $provinceName ?: ($a->city ?? ''),
                'songs'       => $songs,
                'clips'       => $clips,
                'status'      => $a->status == 1 ? 'published' : 'draft',
                'likes'       => (int) ($a->total_views ?? $likes),
                'listens'     => $listens,
                'avatar'      => Helpers::mediaUrl($a->image) ?? '',
                'followers'   => $likes,
                // Popularity = real engagement only (listens + favorites), not catalog size.
                'popularity'  => min(100, (int) floor($listens / 10) + $likes * 5),
            ];
        })->values();

        if ($sort === 'songs') {
            $result = $result->sortByDesc('songs')->values();
        } elseif ($sort === 'likes') {
            $result = $result->sortByDesc('likes')->values();
        }

        return ResponseHelper::sendResponse($result, 'Artists fetched.');
    }

    public function songs()
    {
        $songs = Song::where('status', 1)
            ->orderBy('created_at', 'desc')
            ->get();

        $artistIds = $songs->pluck('artist_id')->unique()->filter()->toArray();
        $artists = Artist::whereIn('_id', $artistIds)->get()->keyBy('_id');

        $songIds = $songs->pluck('_id')->toArray();
        $viewCounts = SongViews::whereIn('song_id', $songIds)
            ->get()
            ->groupBy('song_id')
            ->map(fn($g) => $g->count());

        $result = $songs->map(function ($s) use ($artists, $viewCounts) {
            $artist = $artists->get($s->artist_id);
            return [
                'id'       => $s->_id,
                'title'    => $s->name ?? '',
                'artist'   => $artist?->name ?? 'Unknown',
                'cover'    => Helpers::mediaUrl($artist?->image) ?? '',
                'plays'    => (int) $viewCounts->get($s->_id, 0),
                'duration' => $s->length ?? '0:00',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Songs fetched.');
    }

    public function videoClips()
    {
        $clips = VideoClip::orderBy('created_at', 'desc')->get();

        $artistIds = $clips->pluck('artist_id')->unique()->filter()->toArray();
        $artists = Artist::whereIn('_id', $artistIds)->get()->keyBy('_id');

        $result = $clips->map(function ($c) use ($artists) {
            $artist = $artists->get($c->artist_id);
            return [
                'id'        => $c->_id,
                'artist_id' => (string) ($c->artist_id ?? ''),
                'title'     => $c->name ?? ($artist ? ($artist->name . ' - Clip') : 'Video Clip'),
                'avatar'    => Helpers::mediaUrl($artist?->image) ?? '',
                'timeAgo'   => \Carbon\Carbon::parse($c->created_at)->diffForHumans(),
                'thumbnail' => Helpers::mediaUrl($c->thumbnail) ?? '',
                'video'     => Helpers::mediaUrl($c->video) ?? '',
                'duration'  => $c->length ?? '0:00',
                'views'     => (int) ($c->short_size ?? 0),
                'comments'  => 0,
                'likes'     => 0,
                'status'    => $c->status == 1 ? 'Published' : 'Draft',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Video clips fetched.');
    }

    /** GET /admin/music/categories — music types for the Create Song dropdown. */
    public function musicCategories()
    {
        $cats = MusicCategory::orderBy('name')->get();
        $result = $cats->map(fn($c) => [
            'id'   => (string) $c->getKey(),
            'name' => $c->name ?? '',
        ])->values();

        return ResponseHelper::sendResponse($result, 'Music categories fetched.');
    }

    /** POST /admin/music/songs — create a song (artist + audio + optional cover). */
    public function storeSong(Request $request)
    {
        $request->validate([
            'artist_id' => 'required|string',
            'audio'     => 'required|file',
        ]);

        $song = new Song();
        $song->artist_id = $request->input('artist_id');
        if ($request->filled('category_id')) $song->category_id = $request->input('category_id');
        $song->status = $request->input('status', '1');

        $audioFile = $request->file('audio');
        // No title field in the form → name comes from the request, else the audio filename.
        $song->name = $request->input('name')
            ?: pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME);
        $song->file_size = round($audioFile->getSize() / 1024 / 1024, 2);
        // Read duration BEFORE fileCDNUpload (it moves the file off the temp path).
        $length = $this->extractDuration($audioFile->getRealPath());
        if ($length) $song->length = $length;
        $songFolder = $this->artistMediaFolder($song->artist_id, 'audios/songs');
        // fileCDNUpload transcodes mp3 → m4a and returns the CDN-relative path.
        $song->audio = Helpers::fileCDNUpload($audioFile, $songFolder);

        if ($request->hasFile('cover')) {
            $song->image = Helpers::fileCDNUpload($request->file('cover'), $this->artistMediaFolder($song->artist_id, 'images/songs'));
        }

        $song->save();

        // Config-driven push (new_music toggle + title/description). Published only.
        if ((string) $song->status === '1') {
            NotificationHelper::sendConfiguredBroadcast('new_music', ['[name]' => (string) $song->name], 'music');
        }

        return ResponseHelper::sendResponse([
            'id'     => (string) $song->getKey(),
            'title'  => $song->name,
            'audio'  => Helpers::mediaUrl($song->audio) ?? '',
            'cover'  => Helpers::mediaUrl($song->image) ?? '',
            'length' => $song->length ?? '0:00',
        ], 'Song created.');
    }

    public function storeArtist(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $artist = new Artist();
        $artist->name = $request->input('name');
        $artist->gender = $request->input('gender');
        if ($request->filled('province_id')) $artist->province_id = $request->input('province_id');
        $artist->status = $request->input('status', '1');
        if ($request->hasFile('image')) {
            $artist->image = Helpers::fileCDNUpload($request->file('image'), 'images/artist');
        }
        $artist->save();

        // Config-driven push (new_artist toggle + title/description in Portal Notifications).
        // Only when the artist is published, not saved as a draft.
        if ((string) $artist->status === '1') {
            NotificationHelper::sendConfiguredBroadcast('new_artist', ['[name]' => (string) $artist->name], 'artist');
        }

        return ResponseHelper::sendResponse([
            'id'     => (string) $artist->getKey(),
            'name'   => $artist->name,
            'avatar' => Helpers::mediaUrl($artist->image) ?? '',
        ], 'Artist created.');
    }

    public function updateArtist(Request $request, $id)
    {
        $artist = Artist::find($id);
        if (!$artist) {
            return ResponseHelper::sendResponse([], 'Artist not found.', false, 404);
        }

        // Draft/inactive → published on edit should fire the same Portal Notification as create.
        $wasPublished = (string) $artist->status === '1';

        if ($request->filled('name'))        $artist->name        = $request->input('name');
        if ($request->filled('gender'))      $artist->gender      = $request->input('gender');
        if ($request->filled('province_id')) $artist->province_id = $request->input('province_id');
        if ($request->filled('status'))      $artist->status      = $request->input('status');

        if ($request->hasFile('image')) {
            $artist->image = Helpers::fileCDNUpload($request->file('image'), 'images/artist');
        }
        $artist->save();

        if (!$wasPublished && (string) $artist->status === '1') {
            // Publishing the artist also publishes all of their songs + video clips
            // (content is usually uploaded as draft under a draft artist).
            Song::where('artist_id', $id)->update(['status' => '1']);
            VideoClip::where('artist_id', $id)->update(['status' => '1']);

            NotificationHelper::sendConfiguredBroadcast('new_artist', ['[name]' => (string) $artist->name], 'artist');
        }

        return ResponseHelper::sendResponse([
            'id'     => (string) $artist->getKey(),
            'name'   => $artist->name,
            'avatar' => Helpers::mediaUrl($artist->image) ?? '',
        ], 'Artist updated.');
    }

    public function deleteArtist($id)
    {
        $artist = Artist::find($id);
        if (!$artist) {
            return ResponseHelper::sendResponse([], 'Artist not found.', false, 404);
        }

        // Same rule as the legacy dashboard: only empty artists (no songs, no clips) can be removed.
        $songCount = Song::where('artist_id', $id)->count();
        $clipCount = VideoClip::where('artist_id', $id)->count();
        if ($songCount > 0 || $clipCount > 0) {
            return ResponseHelper::sendResponse(
                null,
                'Cannot remove artist while songs or video clips still exist.',
                false,
                422
            );
        }

        if (!empty($artist->image)) {
            try {
                $bunny = new BunnyCDNService();
                $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');
                $rel = $cdnBase !== '' && Str::startsWith((string) $artist->image, $cdnBase . '/')
                    ? Str::after((string) $artist->image, $cdnBase . '/')
                    : ltrim((string) $artist->image, '/');
                if ($rel !== '' && !Str::startsWith($rel, ['http://', 'https://'])) {
                    $bunny->delete($rel);
                }
            } catch (\Throwable $e) {
                // Don't block DB delete if CDN cleanup fails.
            }
        }

        $artist->delete();
        return ResponseHelper::sendResponse([], 'Artist deleted.');
    }

    public function storeClip(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'artist_id' => 'required|string',
        ]);

        $clip = new VideoClip();
        $clip->name      = $request->input('name');
        $clip->artist_id = $request->input('artist_id');
        $clip->status    = $request->input('status', '1');
        if ($request->filled('custom_id')) $clip->custom_id = $request->input('custom_id');
        if ($request->filled('length'))    $clip->length    = $request->input('length');

        if ($request->hasFile('thumbnail')) {
            $clip->thumbnail = Helpers::fileCDNUpload(
                $request->file('thumbnail'),
                $this->artistMediaFolder($clip->artist_id, 'images/thumbnails/clips')
            );
        }
        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $sizeMb = round($videoFile->getSize() / 1024 / 1024, 2);
            $length = $this->extractDuration($videoFile->getRealPath());
            $clip->video = Helpers::fileCDNUpload($videoFile, $this->artistMediaFolder($clip->artist_id, 'videos/clips'));
            $clip->file_size = $sizeMb;
            if ($length) $clip->length = $length;
        }
        $clip->save();

        // Config-driven push (new_video_clips toggle + title/description). Published only.
        if ((string) $clip->status === '1') {
            NotificationHelper::sendConfiguredBroadcast('new_video_clips', ['[name]' => (string) $clip->name], 'video_clips');
        }

        return ResponseHelper::sendResponse([
            'id'        => (string) $clip->getKey(),
            'name'      => $clip->name,
            'thumbnail' => Helpers::mediaUrl($clip->thumbnail) ?? '',
            'video'     => Helpers::mediaUrl($clip->video) ?? '',
            'length'    => $clip->length ?? '0:00',
        ], 'Video clip created.');
    }

    /** CDN subfolder per artist, e.g. audios/songs/abbas_ahmed */
    private function artistMediaFolder(string $artistId, string $baseFolder): string
    {
        $artist = Artist::find($artistId);
        $slug = Str::slug((string) ($artist->name ?? ''), '_');
        if ($slug === '') {
            $slug = 'artist_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $artistId);
        }
        return rtrim($baseFolder, '/') . '/' . $slug;
    }

    private function extractDuration(?string $path): ?string
    {
        if (!$path || !file_exists($path)) return null;
        $ffprobe = trim((string) shell_exec('which ffprobe 2>/dev/null'));
        if (!$ffprobe) return null;
        $out = @shell_exec(escapeshellcmd($ffprobe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>/dev/null');
        $secs = (float) trim((string) $out);
        if ($secs <= 0) return null;
        $m = floor($secs / 60);
        $s = (int) round($secs - ($m * 60));
        if ($s === 60) { $m++; $s = 0; }
        return sprintf('%02d:%02d', $m, $s);
    }

    public function updateClip(Request $request, $id)
    {
        $clip = VideoClip::find($id);
        if (!$clip) {
            return ResponseHelper::sendResponse([], 'Video clip not found.', false, 404);
        }

        // Draft/inactive → published on edit should fire the same Portal Notification as create.
        $wasPublished = (string) $clip->status === '1';

        if ($request->filled('name'))      $clip->name      = $request->input('name');
        if ($request->filled('artist_id')) $clip->artist_id = $request->input('artist_id');
        if ($request->filled('status'))    $clip->status    = $request->input('status');
        if ($request->filled('custom_id')) $clip->custom_id = $request->input('custom_id');
        if ($request->filled('length'))    $clip->length    = $request->input('length');

        if ($request->hasFile('thumbnail')) {
            $clip->thumbnail = Helpers::fileCDNUpload(
                $request->file('thumbnail'),
                $this->artistMediaFolder($clip->artist_id, 'images/thumbnails/clips')
            );
        }
        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $sizeMb = round($videoFile->getSize() / 1024 / 1024, 2);
            $length = $this->extractDuration($videoFile->getRealPath());
            $clip->video = Helpers::fileCDNUpload($videoFile, $this->artistMediaFolder($clip->artist_id, 'videos/clips'));
            $clip->file_size = $sizeMb;
            if ($length) $clip->length = $length;
        }
        $clip->save();

        if (!$wasPublished && (string) $clip->status === '1') {
            NotificationHelper::sendConfiguredBroadcast('new_video_clips', ['[name]' => (string) $clip->name], 'video_clips');
        }

        return ResponseHelper::sendResponse([
            'id'        => (string) $clip->getKey(),
            'name'      => $clip->name,
            'thumbnail' => Helpers::mediaUrl($clip->thumbnail) ?? '',
            'video'     => Helpers::mediaUrl($clip->video) ?? '',
        ], 'Video clip updated.');
    }

    public function updateSong(Request $request, $id)
    {
        $song = Song::find($id);
        if (!$song) {
            return ResponseHelper::sendResponse([], 'Song not found.', false, 404);
        }

        $request->validate([
            'status' => 'sometimes|in:0,1',
        ]);

        // Draft → published on edit fires the same Portal Notification as create.
        $wasPublished = (string) $song->status === '1';

        if ($request->filled('status')) {
            $song->status = $request->input('status');
        }
        $song->save();

        if (!$wasPublished && (string) $song->status === '1') {
            NotificationHelper::sendConfiguredBroadcast('new_music', ['[name]' => (string) $song->name], 'music');
        }

        return ResponseHelper::sendResponse([
            'id'     => (string) $song->getKey(),
            'status' => (string) $song->status === '1' ? 'Published' : 'Draft',
        ], 'Song updated.');
    }

    public function deleteSong($id)
    {
        $song = Song::find($id);
        if (!$song) {
            return ResponseHelper::sendResponse([], 'Song not found.', false, 404);
        }

        foreach (['audio', 'image'] as $field) {
            if (!empty($song->{$field})) {
                $this->deleteFromCdn((string) $song->{$field});
            }
        }

        SongViews::where('song_id', $song->_id)->delete();
        $song->delete();

        return ResponseHelper::sendResponse([], 'Song deleted.');
    }

    public function deleteClip($id)
    {
        $clip = VideoClip::find($id);
        if (!$clip) {
            return ResponseHelper::sendResponse([], 'Video clip not found.', false, 404);
        }

        foreach (['video', 'clip', 'thumbnail'] as $field) {
            if (!empty($clip->{$field})) {
                $this->deleteFromCdn((string) $clip->{$field});
            }
        }

        $clip->delete();
        return ResponseHelper::sendResponse([], 'Video clip deleted.');
    }

    /** Remove a stored media path from BunnyCDN (relative path or full CDN URL). */
    private function deleteFromCdn(string $path): void
    {
        $relative = $this->cdnRelativePath($path);
        if ($relative === '') {
            return;
        }
        (new BunnyCDNService())->delete($relative);
    }

    private function cdnRelativePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');
        if ($cdnBase !== '' && Str::startsWith($path, $cdnBase . '/')) {
            return ltrim(Str::after($path, $cdnBase . '/'), '/');
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return '';
        }
        return ltrim($path, '/');
    }

    public function artistSongs($id)
    {
        $songs = Song::where('artist_id', $id)->orderBy('created_at', 'desc')->get();
        $songIds = $songs->pluck('_id')->toArray();
        $viewCounts = SongViews::whereIn('song_id', $songIds)
            ->get()
            ->groupBy('song_id')
            ->map(fn($g) => $g->count());

        $result = $songs->values()->map(function ($s, $i) use ($viewCounts) {
            return [
                'id'       => $s->custom_id ?? sprintf('S-%03d', $i + 1),
                'songId'   => (string) $s->_id,
                'title'    => $s->name ?? '',
                'duration' => $s->length ?? '0:00',
                'size'     => $s->file_size ? $s->file_size . 'MB' : '0MB',
                'listens'  => (int) $viewCounts->get($s->_id, 0),
                'trend'    => 'up',
                'track'    => Helpers::mediaUrl($s->audio) ?? '',
                'status'   => $s->status == 1 ? 'Published' : 'Draft',
            ];
        });

        return ResponseHelper::sendResponse($result, 'Artist songs fetched.');
    }

    public function artistClips($id)
    {
        $clips = VideoClip::where('artist_id', $id)->orderBy('created_at', 'desc')->get();

        $result = $clips->values()->map(function ($c, $i) {
            return [
                'id'        => sprintf('VC-%03d', $i + 1),
                'clipId'    => (string) $c->_id,
                'title'     => $c->name ?? ('Clip ' . ($i + 1)),
                'thumbnail' => Helpers::mediaUrl($c->thumbnail) ?? '',
                'video'     => Helpers::mediaUrl($c->clip ?? $c->video) ?? '',
                'views'     => (int) ($c->short_size ?? 0),
                'likes'     => 0,
                'duration'  => $c->length ?? '0:00',
                'status'    => $c->status == 1 ? 'Published' : 'Draft',
            ];
        });

        return ResponseHelper::sendResponse($result, 'Artist clips fetched.');
    }

    public function stats()
    {
        return ResponseHelper::sendResponse([
            'total_artists'     => Artist::count(),
            'total_songs'       => Song::count(),
            'total_video_clips' => VideoClip::count(),
            'total_likes'       => ArtistFavorite::count(),
        ], 'Music stats fetched.');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistFavorite;
use App\Models\Region;
use App\Models\Song;
use App\Models\SongViews;
use App\Models\VideoClip;
use Illuminate\Http\Request;

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

        $provinceIds = $artists->pluck('province_id')->unique()->filter()->toArray();
        $provinces = Region::whereIn('_id', $provinceIds)->get()->keyBy(fn($r) => (string) $r->_id);

        $result = $artists->map(function ($a) use ($songCounts, $clipCounts, $favCounts, $provinces) {
            $songs = $songCounts->get($a->_id, 0);
            $clips = $clipCounts->get($a->_id, 0);
            $likes = $favCounts->get($a->_id, 0);
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
                'avatar'      => Helpers::mediaUrl($a->image) ?? '',
                'followers'   => $likes,
                'popularity'  => min(100, $songs * 5 + $clips * 3 + $likes * 2),
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

        if ($request->filled('name'))        $artist->name        = $request->input('name');
        if ($request->filled('gender'))      $artist->gender      = $request->input('gender');
        if ($request->filled('province_id')) $artist->province_id = $request->input('province_id');
        if ($request->filled('status'))      $artist->status      = $request->input('status');

        if ($request->hasFile('image')) {
            $artist->image = Helpers::fileCDNUpload($request->file('image'), 'images/artist');
        }
        $artist->save();

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
            $clip->thumbnail = Helpers::fileCDNUpload($request->file('thumbnail'), 'images/thumbnails/clips');
        }
        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $sizeMb = round($videoFile->getSize() / 1024 / 1024, 2);
            $length = $this->extractDuration($videoFile->getRealPath());
            $clip->video = Helpers::fileCDNUpload($videoFile, 'videos/clips');
            $clip->file_size = $sizeMb;
            if ($length) $clip->length = $length;
        }
        $clip->save();

        return ResponseHelper::sendResponse([
            'id'        => (string) $clip->getKey(),
            'name'      => $clip->name,
            'thumbnail' => Helpers::mediaUrl($clip->thumbnail) ?? '',
            'video'     => Helpers::mediaUrl($clip->video) ?? '',
            'length'    => $clip->length ?? '0:00',
        ], 'Video clip created.');
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

        if ($request->filled('name'))      $clip->name      = $request->input('name');
        if ($request->filled('artist_id')) $clip->artist_id = $request->input('artist_id');
        if ($request->filled('status'))    $clip->status    = $request->input('status');
        if ($request->filled('custom_id')) $clip->custom_id = $request->input('custom_id');
        if ($request->filled('length'))    $clip->length    = $request->input('length');

        if ($request->hasFile('thumbnail')) {
            $clip->thumbnail = Helpers::fileCDNUpload($request->file('thumbnail'), 'images/thumbnails/clips');
        }
        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $sizeMb = round($videoFile->getSize() / 1024 / 1024, 2);
            $length = $this->extractDuration($videoFile->getRealPath());
            $clip->video = Helpers::fileCDNUpload($videoFile, 'videos/clips');
            $clip->file_size = $sizeMb;
            if ($length) $clip->length = $length;
        }
        $clip->save();

        return ResponseHelper::sendResponse([
            'id'        => (string) $clip->getKey(),
            'name'      => $clip->name,
            'thumbnail' => Helpers::mediaUrl($clip->thumbnail) ?? '',
            'video'     => Helpers::mediaUrl($clip->video) ?? '',
        ], 'Video clip updated.');
    }

    public function deleteClip($id)
    {
        $clip = VideoClip::find($id);
        if (!$clip) {
            return ResponseHelper::sendResponse([], 'Video clip not found.', false, 404);
        }
        $clip->delete();
        return ResponseHelper::sendResponse([], 'Video clip deleted.');
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

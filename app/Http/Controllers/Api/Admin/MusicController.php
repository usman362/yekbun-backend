<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistFavorite;
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

        $result = $artists->map(function ($a) use ($songCounts, $clipCounts, $favCounts) {
            $songs = $songCounts->get($a->_id, 0);
            $clips = $clipCounts->get($a->_id, 0);
            $likes = $favCounts->get($a->_id, 0);

            return [
                'id'         => $a->_id,
                'name'       => $a->name ?? '',
                'region'     => $a->city ?? '',
                'songs'      => $songs,
                'clips'      => $clips,
                'status'     => $a->status == 1 ? 'published' : 'draft',
                'likes'      => (int) ($a->total_views ?? $likes),
                'avatar'     => Helpers::mediaUrl($a->image) ?? '',
                'followers'  => $likes,
                'popularity' => min(100, $songs * 5 + $clips * 3 + $likes * 2),
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
            ->orderByRaw(['created_at' => -1])
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
                'artist'   => $artist->name ?? 'Unknown',
                'cover'    => Helpers::mediaUrl($artist->image) ?? '',
                'plays'    => (int) $viewCounts->get($s->_id, 0),
                'duration' => $s->length ?? '0:00',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Songs fetched.');
    }

    public function videoClips()
    {
        $clips = VideoClip::orderByRaw(['created_at' => -1])->get();

        $artistIds = $clips->pluck('artist_id')->unique()->filter()->toArray();
        $artists = Artist::whereIn('_id', $artistIds)->get()->keyBy('_id');

        $result = $clips->map(function ($c) use ($artists) {
            $artist = $artists->get($c->artist_id);
            return [
                'id'        => $c->_id,
                'title'     => $artist ? ($artist->name . ' - Clip') : 'Video Clip',
                'avatar'    => Helpers::mediaUrl($artist->image) ?? '',
                'timeAgo'   => \Carbon\Carbon::parse($c->created_at)->diffForHumans(),
                'thumbnail' => Helpers::mediaUrl($c->thumbnail) ?? '',
                'views'     => (int) ($c->short_size ?? 0),
                'comments'  => 0,
                'likes'     => 0,
                'status'    => $c->status == 1 ? 'Published' : 'Draft',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Video clips fetched.');
    }

    public function artistSongs($id)
    {
        $songs = Song::where('artist_id', $id)->orderByRaw(['created_at' => -1])->get();
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
        $clips = VideoClip::where('artist_id', $id)->orderByRaw(['created_at' => -1])->get();

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

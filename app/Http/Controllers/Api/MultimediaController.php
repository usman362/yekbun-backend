<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\PermissionHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AIVideo;
use App\Models\Artist;
use App\Models\ArtistFavorite;
use App\Models\Clips;
use App\Models\Feed;
use App\Models\History;
use App\Models\Media;
use App\Models\MusicPlay;
use App\Models\Song;
use App\Models\SongViews;
use App\Models\User;
use App\Models\UserPlaylist;
use App\Models\UserPlaylistGroup;
use App\Models\VideoClip;
use App\Models\VideoClipViews;
use App\Models\VideoPlay;
use MongoDB\BSON\ObjectId;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class MultimediaController extends Controller
{
    public function getAllSongs()
    {
        $songs = Song::with(['artist', 'playlists'])->whereHas('artist', function ($q) {
            $q->where('status', '1');
        })->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($songs, 'All Songs Fetch Successfully!');
    }

    public function getAllClips()
    {
        $videos = VideoClip::with(['artist', 'playlists'])->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($videos, 'All Video Clips Fetch Successfully!');
    }

    public function getAllSongsPublic()
    {
        $songs = Song::with('artist')->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($songs, 'All Songs Fetch Successfully!');
    }

    public function getAllClipsPublic()
    {
        $videos = VideoClip::with('artist')->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($videos, 'All Video Clips Fetch Successfully!');
    }

    public function getSongByArtists(Request $request, $id)
    {
        $artist = Artist::with(['province' => fn($q) => $q->with('country')])->where('status', '1')->find($id);
        if (!$artist) {
            return ResponseHelper::sendResponse([], 'Artist not found!', false, 404);
        }
        $songs = Song::with('playlists')->where('artist_id', $id)->get();
        return ResponseHelper::sendResponse(['artist' => $artist, 'songs' => $songs], 'Songs Fetch Successfully!');
    }

    public function getClipsByArtists(Request $request, $id)
    {
        $artist = Artist::with(['province' => fn($q) => $q->with('country')])->find($id);
        $videos = VideoClip::with('playlists')->where('artist_id', $id)->get();
        return ResponseHelper::sendResponse(['artist' => $artist, 'videos' => $videos], 'Video Clips Fetch Successfully!');
    }

    public function getArtists()
    {
        $alphabet = request('alphabet');
        $search = request('query');

        $artists = Artist::when($alphabet, fn($query, $alphabet) => $query->where('name', 'LIKE', $alphabet . '%'))
            ->when($search, fn($query, $search) => $query->where('name', 'LIKE', '%' . $search . '%'))
            ->with(['songs', 'videos', 'province.country'])
            ->where('status', '1')->orderBy('created_at', 'desc')->get();

        return ResponseHelper::sendResponse($artists, 'All Artists Fetch Successfully!');
    }

    public function getFavArtists()
    {
        $artist_ids = ArtistFavorite::where('user_id', Auth::id())->pluck('artist_id');
        $alphabet = request('alphabet');
        $search = request('query');

        $artists = Artist::when($alphabet, fn($query, $alphabet) => $query->where('name', 'LIKE', $alphabet . '%'))
            ->when($search, fn($query, $search) => $query->where('name', 'LIKE', '%' . $search . '%'))
            ->whereIn('_id', $artist_ids)->with(['songs', 'videos', 'province' => fn($q) => $q->with('country')])
            ->orderBy('created_at', 'desc')->get();

        return ResponseHelper::sendResponse($artists, 'All Artists Fetch Successfully!');
    }

    public function getPopularArtists()
    {
        $alphabet = request('alphabet');
        $search = request('query');

        $artists = Artist::when($alphabet, fn($query, $alphabet) => $query->where('name', 'LIKE', $alphabet . '%'))
            ->when($search, fn($query, $search) => $query->where('name', 'LIKE', '%' . $search . '%'))
            ->where('status', '1')->with(['songs', 'videos', 'province' => fn($q) => $q->with('country')])
            ->orderBy('total_views', 'desc')->get();

        return ResponseHelper::sendResponse($artists, 'All Artists Fetch Successfully!');
    }

    public function getArtistsGrouped(Request $request)
    {
        $alphabet = $request->input('alphabet');
        $search = $request->input('query');
        $userId = Auth::id();

        $baseQuery = Artist::when($alphabet, fn($query) => $query->where('name', 'LIKE', $alphabet . '%'))
            ->when($search, fn($query) => $query->where('name', 'LIKE', '%' . $search . '%'))
            ->with(['songs', 'videos', 'province.country'])
            ->where('status', '1');

        $allArtists = (clone $baseQuery)->orderBy('created_at', 'desc')->get()
            ->map(function ($artist) { $artist->type = 'all'; return $artist; });

        $favArtistIds = ArtistFavorite::where('user_id', $userId)->pluck('artist_id')->toArray();
        $popularArtists = (clone $baseQuery)->orderBy('total_views', 'desc')->limit(20)->get()->pluck('_id')->toArray();

        $finalData = $allArtists->map(function ($artist) use ($favArtistIds, $popularArtists) {
            if (in_array($artist->_id, $favArtistIds)) $artist->type = 'favourite';
            if (in_array($artist->_id, $popularArtists)) $artist->type = 'popular';
            return $artist;
        });

        return ResponseHelper::sendResponse($finalData, 'Artists fetched successfully!');
    }

    public function getArtistsPublic()
    {
        $alphabet = request('alphabet');
        $artists = Artist::when($alphabet, fn($query, $alphabet) => $query->where('name', 'LIKE', $alphabet . '%'))
            ->with(['songs', 'videos', 'province.country'])
            ->where('status', '1')->orderBy('created_at', 'desc')->get();

        return ResponseHelper::sendResponse($artists, 'All Artists Fetch Successfully!');
    }

    public function storeArtist(Request $request)
    {
        $request->validate(['name' => 'required', 'gender' => 'required', 'image' => 'required']);
        try {
            $imagePath = $request->hasFile('image') ? Helpers::fileUpload($request->image, 'images') : null;
            $artist = new Artist();
            $artist->name = $request->name;
            $artist->gender = $request->gender;
            $artist->status = $request->status;
            $artist->province_id = $request->province;
            $artist->image = $imagePath;
            $artist->save();
            return ResponseHelper::sendResponse($artist, 'Artist has been added successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Add Artist!', false, 403);
        }
    }

    public function updateArtist(Request $request, $id)
    {
        $request->validate(['name' => 'required', 'gender' => 'required']);
        try {
            $artist = Artist::find($id);
            $artist->name = $request->name;
            $artist->gender = $request->gender;
            $artist->status = $request->status;
            $artist->province_id = $request->province;
            if ($request->hasFile('image')) {
                $artist->image = Helpers::fileUpload($request->image, 'images');
            }
            $artist->save();
            return ResponseHelper::sendResponse($artist, 'Artist has been Updated successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Update Artist!', false, 403);
        }
    }

    public function deleteArtist($id)
    {
        $artist = Artist::findOrFail($id);
        if ($artist->delete()) {
            return ResponseHelper::sendResponse([], 'Artist has been deleted successfully!');
        }
        return ResponseHelper::sendResponse([], 'Failed to Delete Artist!', false, 403);
    }

    public function statusArtist(Request $request, $id)
    {
        $request->validate(['status' => 'required']);
        $artist = Artist::find($id);
        $artist->status = $request->status;
        if ($artist->update()) {
            return ResponseHelper::sendResponse($artist, 'Status Has been Updated!');
        }
        return ResponseHelper::sendResponse([], 'Failed to Change Status', false, 403);
    }

    public function getArtistDetail(Request $request, $id)
    {
        $artist = Artist::with(['province' => fn($q) => $q->with('country')])
            ->with(['songs' => fn($q) => $q->with('playlists')])
            ->with(['videos' => fn($q) => $q->with('playlists')])
            ->find($id);

        $fav = ArtistFavorite::where('artist_id', $id)->where('user_id', Auth::id())->get();
        $favourites = ArtistFavorite::where('artist_id', $id)->get();
        foreach ($favourites as $favourite) {
            $favUser = User::find($favourite->user_id);
            if (!$favUser) $favourite->delete();
        }

        return ResponseHelper::sendResponse([
            'artist' => $artist,
            'is_favorite' => $fav->count() > 0 ? 1 : 0,
            'favorite_count' => $favourites->count()
        ], 'Artist Detail Fetch Successfully!');
    }

    public function playMusic(Request $request, $id)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'music_allow_music');
        if ($allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Use Musics.', false, 409);
        }

        $userId = Auth::id();
        $today = Carbon::today();

        MusicPlay::where('user_id', $userId)->whereDate('created_at', '<', $today)->delete();
        $todayPlayCount = MusicPlay::where('user_id', $userId)->whereDate('created_at', $today)->distinct('music_id')->count('music_id');
        $alreadyPlayed = MusicPlay::where('user_id', $userId)->where('music_id', $id)->whereDate('created_at', $today)->exists();
        $musicCount = PermissionHelper::checkPermission(Auth::user()->level, 'music_daily_songs');

        if (!$alreadyPlayed && $todayPlayCount >= $musicCount) {
            return ResponseHelper::sendResponse([], 'Your daily music play limit has been exceeded.', false, 409);
        }

        MusicPlay::create(['user_id' => $userId, 'music_id' => $id]);
        return ResponseHelper::sendResponse([], 'Music played successfully.');
    }

    public function playVideo(Request $request, $id)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'video_allow_video');
        if ($allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Use Videos.', false, 409);
        }

        $userId = Auth::id();
        $today = Carbon::today();

        VideoPlay::where('user_id', $userId)->whereDate('created_at', '<', $today)->delete();
        $todayPlayCount = VideoPlay::where('user_id', $userId)->whereDate('created_at', $today)->distinct('video_id')->count('video_id');
        $alreadyPlayed = VideoPlay::where('user_id', $userId)->where('video_id', $id)->whereDate('created_at', $today)->exists();
        $videoCount = PermissionHelper::checkPermission(Auth::user()->level, 'video_daily_videos');

        if (!$alreadyPlayed && $todayPlayCount >= $videoCount) {
            return ResponseHelper::sendResponse([], 'Your daily video play limit has been exceeded.', false, 409);
        }

        VideoPlay::create(['user_id' => $userId, 'video_id' => $id]);
        return ResponseHelper::sendResponse([], 'Video played successfully.');
    }

    public function store_artist_song_views(Request $request, $id)
    {
        try {
            SongViews::updateOrCreate(
                ['user_id' => Auth::id(), 'artist_id' => $id],
                ['user_id' => Auth::id(), 'artist_id' => $id]
            );
            $songViews = SongViews::where('artist_id', $id)->count();
            $videoViews = VideoClipViews::where('artist_id', $id)->count();

            $artist = Artist::find($id);
            if ($artist) {
                $artist->song_views = $songViews;
                $artist->total_views = $songViews + $videoViews;
                $artist->save();
            }
            return ResponseHelper::sendResponse([
                'song_views' => $songViews, 'video_views' => $videoViews, 'total_views' => $songViews + $videoViews
            ], 'Artist Views has been Successfully Saved!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Save Artist Views', false, 403);
        }
    }

    public function store_artist_video_views(Request $request, $id)
    {
        try {
            VideoClipViews::updateOrCreate(
                ['user_id' => Auth::id(), 'artist_id' => $id],
                ['user_id' => Auth::id(), 'artist_id' => $id]
            );
            $songViews = SongViews::where('artist_id', $id)->count();
            $videoViews = VideoClipViews::where('artist_id', $id)->count();

            $artist = Artist::find($id);
            if ($artist) {
                $artist->video_views = $videoViews;
                $artist->total_views = $songViews + $videoViews;
                $artist->save();
            }
            return ResponseHelper::sendResponse([
                'song_views' => $songViews, 'video_views' => $videoViews, 'total_views' => $songViews + $videoViews
            ], 'Artist Views has been Successfully Saved!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Save Artist Views', false, 403);
        }
    }

    public function store_artist_favorites(Request $request, $id)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'music_favorite_artist');
        if ($allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Add Artist as Favorite.', false, 409);
        }
        try {
            $exists = ArtistFavorite::where('user_id', Auth::id())->where('artist_id', $id)->first();
            if ($exists) {
                $exists->delete();
                $message = 'Artist removed from favorites successfully!';
            } else {
                ArtistFavorite::create(['user_id' => Auth::id(), 'artist_id' => $id]);
                $message = 'Artist added to favorites successfully!';
            }
            return ResponseHelper::sendResponse([], $message);
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to toggle artist favorite', false, 500);
        }
    }

    public function getSongsPlaylist(Request $request)
    {
        $userId = Auth::id();
        $playlists = UserPlaylistGroup::with(['playlists' => fn($q) => $q->with(['song' => fn($a) => $a->with('artist')])])
            ->where('user_id', $userId)->get();

        if ($playlists->isEmpty()) {
            $defaults = [
                ['title' => 'Free Playlist', 'bg_image' => 'assets/img/playlistCover1.png', 'type' => 'free', 'limit' => 25],
                ['title' => 'Bronze Playlist', 'bg_image' => 'assets/img/playlistCover2.png', 'type' => 'paid', 'limit' => 50],
                ['title' => 'Silver Playlist', 'bg_image' => 'assets/img/playlistCover3.png', 'type' => 'paid', 'limit' => 75],
                ['title' => 'Gold Playlist', 'bg_image' => 'assets/img/playlistCover4.png', 'type' => 'paid', 'limit' => 100],
            ];
            foreach ($defaults as $d) {
                UserPlaylistGroup::create(array_merge($d, ['user_id' => $userId]));
            }
            $playlists = UserPlaylistGroup::with(['playlists' => fn($q) => $q->with(['song' => fn($a) => $a->with('artist')])])
                ->where('user_id', $userId)->get();
        }

        return ResponseHelper::sendResponse($playlists, 'Songs Playlist has been Fetch Successfully!');
    }

    public function getPlaylistDetail(Request $request, $id)
    {
        $playlists = UserPlaylistGroup::with(['playlists' => function ($q) {
            $q->with(['song' => fn($a) => $a->with('artist')])->with(['video' => fn($a) => $a->with('artist')]);
        }])->where('user_id', Auth::id())->find($id);

        return ResponseHelper::sendResponse($playlists, 'Playlist has been Fetch Successfully!');
    }

    public function storeGroupPlaylist(Request $request)
    {
        $request->validate(['title' => 'required']);
        try {
            $playlist = UserPlaylistGroup::updateOrCreate(['id' => $request->id], [
                'user_id' => Auth::id(),
                'playlist_id' => $request->playlist_id,
                'type' => $request->type
            ]);
            return ResponseHelper::sendResponse($playlist, 'Songs Playlist has been Created Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Create Playlist!', false, 403);
        }
    }

    public function storeSongsPlaylist(Request $request)
    {
        $request->validate(['media_id' => 'required', 'playlist_id' => 'required']);
        try {
            $exists = UserPlaylist::where('user_id', Auth::id())->where('media_id', $request->media_id)->where('playlist_id', $request->playlist_id)->first();
            if (!empty($exists)) {
                return ResponseHelper::sendResponse([], 'Already Added in Playlist!', false, 403);
            }
            UserPlaylist::create([
                'user_id' => Auth::id(), 'media_id' => $request->media_id,
                'playlist_id' => $request->playlist_id, 'type' => 'audio'
            ]);
            $playlists = UserPlaylistGroup::with(['playlists' => fn($q) => $q->with(['song' => fn($a) => $a->with('artist')])])
                ->where('user_id', Auth::id())->get();
            return ResponseHelper::sendResponse($playlists, 'Songs Playlist has been Created Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Create Playlist!', false, 403);
        }
    }

    public function getClipsPlaylist(Request $request)
    {
        $userId = Auth::id();
        $playlists = UserPlaylistGroup::with(['playlists' => fn($q) => $q->where('type', 'video')->with(['video' => fn($a) => $a->with('artist')])])
            ->where('user_id', $userId)->get();

        if ($playlists->isEmpty()) {
            $defaults = [
                ['title' => 'My Playlist', 'bg_image' => 'assets/img/playlistCover1.png', 'type' => 'free'],
                ['title' => 'My Playlist', 'bg_image' => 'assets/img/playlistCover2.png', 'type' => 'paid'],
                ['title' => 'My Playlist', 'bg_image' => 'assets/img/playlistCover3.png', 'type' => 'paid'],
                ['title' => 'My Playlist', 'bg_image' => 'assets/img/playlistCover4.png', 'type' => 'paid'],
            ];
            foreach ($defaults as $d) {
                UserPlaylistGroup::create(array_merge($d, ['user_id' => $userId]));
            }
            $playlists = UserPlaylistGroup::with(['playlists' => fn($q) => $q->where('type', 'video')->with(['video' => fn($a) => $a->with('artist')])])
                ->where('user_id', $userId)->get();
        }

        return ResponseHelper::sendResponse($playlists, 'Clips Playlist has been Fetch Successfully!');
    }

    public function storeClipsPlaylist(Request $request)
    {
        $request->validate(['media_id' => 'required', 'playlist_id' => 'required']);
        try {
            $exists = UserPlaylist::where('user_id', Auth::id())->where('media_id', $request->media_id)->where('playlist_id', $request->playlist_id)->first();
            if (!empty($exists)) {
                return ResponseHelper::sendResponse([], 'Already Added in Playlist!', false, 403);
            }
            UserPlaylist::create([
                'user_id' => Auth::id(), 'media_id' => $request->media_id,
                'playlist_id' => $request->playlist_id, 'type' => 'video'
            ]);
            $playlists = UserPlaylistGroup::with(['playlists' => fn($q) => $q->where('type', 'video')->with(['video' => fn($a) => $a->with('artist')])])
                ->where('user_id', Auth::id())->get();
            return ResponseHelper::sendResponse($playlists, 'Clips Playlist has been Created Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Create Playlist!', false, 403);
        }
    }

    public function deletePlaylistGroup($id)
    {
        $group = UserPlaylistGroup::find($id);
        if (!$group) return ResponseHelper::sendResponse([], 'Playlist Group not found.', false, 404);
        try {
            UserPlaylist::where('playlist_id', $id)->delete();
            $group->delete();
            return ResponseHelper::sendResponse([], 'Playlist has been Deleted Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Something Went Wrong!', false, 403);
        }
    }

    public function deletePlaylist($id)
    {
        $playlist = UserPlaylist::find($id);
        if (!$playlist) return ResponseHelper::sendResponse([], 'Song not found.', false, 404);
        try {
            $playlist->delete();
            return ResponseHelper::sendResponse([], 'Song has been Deleted Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Something Went Wrong!', false, 500);
        }
    }

    public function movePlaylist(Request $request, $id)
    {
        $request->validate(['playlist_id' => 'required']);
        $playlist = UserPlaylist::find($id);
        $group = UserPlaylistGroup::find($request->playlist_id);
        if (!$playlist) return ResponseHelper::sendResponse([], 'Song not found.', false, 404);
        if (!$group) return ResponseHelper::sendResponse([], 'Playlist not found.', false, 404);
        try {
            $playlist->playlist_id = $request->playlist_id;
            $playlist->save();
            return ResponseHelper::sendResponse([], 'Song has been Moved Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Something Went Wrong!', false, 500);
        }
    }

    public function editPlaylist(Request $request, $id)
    {
        try {
            $group = UserPlaylistGroup::find($id);
            if (!empty($request->title)) $group->title = $request->title;
            if (!empty($request->type)) $group->type = $request->type;
            $group->save();
            return ResponseHelper::sendResponse($group, 'Playlist has been Updated Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Update Playlist!', false, 403);
        }
    }

    public function mediaTrimmer(Request $request)
    {
        $request->validate([
            'startTime' => 'required|numeric|min:0',
            'endTime' => 'required|numeric|min:0|gt:startTime',
            'file' => 'nullable|file|mimetypes:audio/*,video/*',
            'url' => 'nullable',
        ]);

        if ($request->hasFile('file')) {
            $originalPath = $request->file('file')->store('public/media/originals');
            $storagePath = storage_path('app/' . $originalPath);
            $extension = $request->file('file')->getClientOriginalExtension();
        } elseif ($request->filled('url')) {
            $fileContents = file_get_contents($request->url);
            $extension = pathinfo(parse_url($request->url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $filename = 'original_' . Str::random(10) . '.' . $extension;
            $originalPath = 'public/media/originals/' . $filename;
            Storage::put($originalPath, $fileContents);
            $storagePath = storage_path('app/' . $originalPath);
        } else {
            return ResponseHelper::sendResponse([], 'No valid file or URL provided.', false, 400);
        }

        $trimmedName = 'trimmed_' . Str::random(10) . '.' . $extension;
        $trimmedPath = storage_path('app/public/media/trimmed/' . $trimmedName);

        $start = escapeshellarg($request->startTime);
        $duration = escapeshellarg($request->endTime - $request->startTime);
        $command = "ffmpeg -ss $start -i " . escapeshellarg($storagePath) . " -t $duration -c copy " . escapeshellarg($trimmedPath);
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            return ResponseHelper::sendResponse([], 'Trimming failed.', false, 500);
        }

        Storage::delete($originalPath);
        return ResponseHelper::sendResponse('media/trimmed/' . $trimmedName, 'Trimmed file uploaded successfully!');
    }

    public function allMediaRecord(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $cursor = $request->get('cursor');

        $query = Media::with('user')->orderBy('_id', 'desc');
        if ($cursor) {
            $query->where('_id', '<', new ObjectId($cursor));
        }

        $media = $query->limit($perPage)->get();
        $nextCursor = optional($media->last())->_id;

        return ResponseHelper::sendResponse([
            'media' => $media,
            'pagination' => [
                'per_page' => $perPage,
                'next_cursor' => $nextCursor,
                'has_more' => $media->count() === $perPage,
            ]
        ], 'Media fetched successfully');
    }
}

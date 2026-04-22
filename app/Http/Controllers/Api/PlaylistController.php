<?php

namespace App\Http\Controllers\Api;

use App\Models\Artist;
use App\Models\Playlist;
use Illuminate\Http\Request;
use App\Models\PlaylistMusic;
use App\Models\FavouriteArtist;
use App\Http\Controllers\Controller;


class PlaylistController extends Controller
{



    protected $fileds = [
        "user_id",
        "playlist_name",
        "visibility",
        "is_music",
        "is_feed",
        "is_vote",
        "is_news",
        "is_history"
    ];

    public function playlist(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'playlist_name' => 'required',
            'visibility' => 'required'
        ]);

        $playlist = new Playlist();
        foreach ($this->fileds as $filed) {
            if ($request->has($filed)) {
                $playlist[$filed] = $request[$filed];
            }
        }
        if ($playlist->save()) {
            return response()->json(['success' => true, 'message' => 'Playlist saved successfully.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Failed to add playlist.']);
        }
    }

    public function get_playlist(Request $request)
    {
        $playlist = Playlist::where('user_id', $request->user_id)->where($request->type, 1)->with('PlaylistMusics')->get();

        if(isset($playlist)) {
            $updated_data = [];
            foreach($playlist as $playlistItem){
                $music_count = count($playlistItem->PlaylistMusics->musics);
                $updated_data[] = [
                    'id' => $playlistItem->id,
                    'playlist_name' => $playlistItem->playlist_name,
                    'user_id' => $playlistItem->user_id,
                    'playlist_musics' => [
                        'id' => $playlistItem->PlaylistMusics->id,
                        'playlist_id' => $playlistItem->PlaylistMusics->playlist_id,
                        'music_count' => $music_count
                    ]
                ];
            }
            return response()->json(['success' => true, 'data' => $updated_data]);
        } else {
            return response()->json(['success' => false, 'data' => []]);
        }
    }

    public function get_single_playlist($playlist_id){

        $single_playlist = PlaylistMusic::where('playlist_id' , $playlist_id)->first();
        if(isset($single_playlist)){
            return response()->json(['success' => true , 'data'=> $single_playlist]);
        }else{
            return response()->json(['success' => false , 'data' => []]);
        }
    }

    public function set_music_to_playlist(Request $request) {
        $request->validate([
            'playlist_id' => 'required',
            'musics' => 'array'
        ]);
    
        $playlist = PlaylistMusic::where('playlist_id', $request->playlist_id)->first();

        if (!$playlist) {
            $playlist = new PlaylistMusic();
            $playlist->playlist_id = $request->playlist_id;
            $playlist->musics = [];
        }
    
        $existingMusics = collect($playlist->musics);
        $message = null;
        if(isset($request->musics)){

            foreach ($request->musics as $music) {
                if (!$existingMusics->contains($music)) {
                    $existingMusics->push($music);
                }else{
                    $message = 'Music Already Exists.';
                }
            }
        }
        $playlist->musics = $existingMusics->toArray();
        $playlist->save();
        if($message){
            
            return response()->json(['success'=> false , 'message' => 'Music Already exists.']);
        }
    
        $playlist->musics = $existingMusics;
        if ($playlist->save()) {
            return response()->json(['success' => true, 'message' => 'Music added to playlist.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Failed to add music.']);
        }
    }


    public function favourite_artist(Request $request){
        $request->validate([
            'user_id' => 'required',
            'artist_id' => 'required|array'
        ]);

        $fav_artist = FavouriteArtist::where('user_id' , $request->user_id)->first();
        if(!$fav_artist){
            $fav_artist =  new FavouriteArtist();
            $fav_artist->user_id = $request->user_id;
            $fav_artist->artist_id = [];
        }
        $existingArtist = collect($fav_artist->artist_id);
        $addedArtists = [];
        $removedArtists = [];

        if(isset($request->artist_id)){

            foreach($request->artist_id as   $artist){
                if(!$existingArtist->contains($artist)){
                    $existingArtist->push($artist);
                    $addedArtists[] =  $artist;
                }else{
                    $existingArtist = $existingArtist->filter(fn($i) => $i !== $artist)->values();
                    $removedArtists[] = $artist;
                }
            }
        }
        
        $fav_artist->artist_id = $existingArtist->toArray();
        if ($fav_artist->save()) { 
            $message = 'Artist ';
            if (!empty($addedArtists)) {
                $message .= 'added to';
            }
            if (!empty($removedArtists)) {
                $message .= (empty($addedArtists) ? '' : ' and ') . 'removed from';
            }
            $message .= ' Favourite.';
    
            return response()->json(['success' => true, 'message' => $message]);
        } else {
            return response()->json(['success' => false, 'message' => 'Failed to update artist favorites.']);
        }
    }

    public function get_favourite_artist($user_id){
        $userFavouriteArtists = FavouriteArtist::where('user_id', $user_id)->first();
        
        if(!$userFavouriteArtists){
            return response()->json(['success' => 'false' , 'message' => 'No user found .']);
        }
        $artists = [];
            foreach ($userFavouriteArtists->artist_id as  $favouriteArtist) {
                $favouriteArtist = Artist::find($favouriteArtist);
                if($favouriteArtist){
                    $favouriteArtist->loadCount('musics');
                    $artists[] = $favouriteArtist;
                }
            }

            if ($artists) {
                $updated_artists = array_map(function ($artist) {
                    return [
                        'id' => $artist->id,
                        'first_name' => $artist->first_name,
                        'last_name' => $artist->last_name,
                        'image' => $artist->image,
                        'musics_count' => $artist->musics_count
                    ];
                }, $artists);
            }

            return response()->json(['success' => 'true' , 'data' => $updated_artists]);
    }


    public function get_favourite_artist_ids($user_id){
        
        $userFavouriteArtists = FavouriteArtist::where('user_id', $user_id)->first();
        if(!$userFavouriteArtists){
            return response()->json(['success' => 'false' , 'message' => 'No user found .']);
            
        }

        return response()->json(['success' => 'true' , 'data' => $userFavouriteArtists]);
        
    }

}

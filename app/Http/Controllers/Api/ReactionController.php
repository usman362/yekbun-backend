<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reaction;

class ReactionController extends Controller
{
    
    // store reaction 

    public function store_reaction(Request $request){
        $request->validate([
            'user_id' => 'required',
        ]);

        $optional_fields = ['feed_id', 'news_id', 'history_id', 'vote_id', 'music_id', 'post_gallery_id'];

        $post_field = '';

        foreach ($optional_fields as $item) {
            if ($request->has($item))
                $post_field = $item;
        }

        $reaction_exist = Reaction::where('user_id', $request->user_id)->where('emoji_id', $request->emoji_id)->where($post_field, $request[$post_field])->first();

        if ($reaction_exist != "" && $reaction_exist->emoji_id == $request->emoji_id) {
            $reaction_exist->delete();

            return response()->json(['success' => true, 'message' => 'Reaction removed.']);
        }

        $reaction =  new Reaction();

        if($request->has('feed_id')){
            $previous_reaction = Reaction::where('user_id', $request->user_id)->where('feed_id', $request->feed_id)->first();

            if ($previous_reaction != "")
                $reaction = $previous_reaction;
            
            $reaction->feed_id = $request->feed_id;
        }

        if($request->has('news_id')){
            $previous_reaction = Reaction::where('user_id', $request->user_id)->where('news_id', $request->news_id)->first();

            if ($previous_reaction != "")
                $reaction = $previous_reaction;
            
            $reaction->news_id = $request->news_id;
        }

        if($request->has('history_id')){
            $previous_reaction = Reaction::where('user_id', $request->user_id)->where('history_id', $request->history_id)->first();

            if ($previous_reaction != "")
                $reaction = $previous_reaction;
            
            $reaction->history_id = $request->history_id;
        }

        if($request->has('vote_id')){
            $previous_reaction = Reaction::where('user_id', $request->user_id)->where('vote_id', $request->vote_id)->first();

            if ($previous_reaction != "")
                $reaction = $previous_reaction;
            
            $reaction->vote_id = $request->vote_id;
        }
        
        if($request->has('music_id')){
            $previous_reaction = Reaction::where('user_id', $request->user_id)->where('music_id', $request->music_id)->first();

            if ($previous_reaction != "")
                $reaction = $previous_reaction;
            
            $reaction->music_id = $request->music_id;
        }
        
        if($request->has('post_gallery_id')){
            $previous_reaction = Reaction::where('user_id', $request->user_id)->where('post_gallery_id', $request->post_gallery_id)->first();

            if ($previous_reaction != "")
                $reaction = $previous_reaction;
            
            $reaction->post_gallery_id = $request->post_gallery_id;
        }

        $reaction->user_id = $request->user_id;
        $reaction->emoji_id = $request->emoji_id;

        if($reaction->save()){
            return response()->json(['success' => true , 'data' => $reaction]);
        }else{
            return response()->json(['success' => false , 'data' => $reaction]);
        }
        
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnimationEmoji;
use App\Models\Reaction;

class AnimationEmojiController extends Controller
{
    public function get_all_emoji($userId = 0, $type = '', $value = '')
    {
        $emoji = AnimationEmoji::get();
        if ($emoji->isEmpty()) {
            return response()->json(['success' => false, 'data' => $emoji]);
        }

        $reaction_exists = null;
        if ($userId != '' && $type != '') {
            $reaction_exists = Reaction::where('user_id', $userId)->where($type, $value)->select('emoji_id')->first();
        }

        $emojiArray = $emoji->toArray();
        $splitData = array_chunk($emojiArray, 4);
        return response()->json(['success' => true, 'data' => $splitData, 'exist' => $reaction_exists ?? '']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Emoji;
use Illuminate\Http\Request;

class EmojiFeedController extends Controller
{
    public function index()
    {
        $emojis = Emoji::all();
        return ResponseHelper::sendResponse($emojis, 'Emojis has been Fetch Successfully!');
    }

    public function store(Request $request)
    {
        $request->validate(['emoji_name' => 'required']);

        $model = new Emoji();
        $model->name = $request->emoji_name;
        if (!empty($request->emoji_image)) {
            $model->image = Helpers::fileUpload($request->emoji_image, 'images/emoji');
        }
        if ($model->save()) {
            return response()->json(['message' => 'Emoji Has been inserted', 'emoji' => $model], 201);
        }
        return response()->json(['message' => 'Failed to add Emoji'], 400);
    }

    public function destroy(Emoji $emoji)
    {
        if ($emoji->delete()) {
            return response()->json(['message' => 'Emoji Has been Deleted'], 201);
        }
        return response()->json(['message' => 'Failed to Delete Emoji'], 400);
    }
}

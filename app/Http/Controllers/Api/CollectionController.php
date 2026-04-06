<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Models\Collection;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Feed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CollectionController extends Controller
{
    public function insert(Request $request)
    {
        $request->validate(['title' => 'required']);

        $collection = new Collection();
        $collection->title = $request->title;
        if ($request->hasFile('image')) {
            $collection->image = Helpers::fileCDNUpload($request->image, 'images/collections');
        }
        $collection->user_id = Auth::id();
        $collection->save();

        return ResponseHelper::sendResponse($collection, 'Collection successfully created.');
    }

    public function add_to_collection(Request $request)
    {
        $collection = Collection::find($request->collection_id);
        if (!$collection) return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);

        $feed = Feed::find($request->feed_id);
        if (!$feed) return ResponseHelper::sendResponse([], 'Feed Not Found!', false, 404);

        $collection->feeds()->sync([$request->feed_id]);
        return ResponseHelper::sendResponse($collection, 'Successfully added to collection.');
    }

    public function get_collection()
    {
        $collections = Collection::with('feeds')->where('user_id', Auth::id())->get();

        $collections = $collections->map(function ($collection) {
            $feeds = $collection->feeds ?? collect([]);
            $relativePath = 'images/collection_feed_empty/empty_feed_collection.jpeg';

            if ($feeds->isEmpty()) {
                $collection->image = $relativePath;
            } elseif (empty($collection->image) && $feeds->isNotEmpty()) {
                foreach ($feeds as $feed) {
                    if (!empty($feed->images) && is_array($feed->images)) {
                        $collection->image = $feed->images[0]['path'] ?? $relativePath;
                        break;
                    }
                }
            }

            $collection->feed_id = $feeds->pluck('_id')->values();
            return $collection;
        });

        return ResponseHelper::sendResponse($collections, 'Collections has been Fetched Successfully.');
    }

    public function destroy($id)
    {
        $collection = Collection::find($id);
        if (!$collection) return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        $collection->delete();
        return ResponseHelper::sendResponse($collection, 'Collection has been Deleted Successfully.');
    }

    public function listCollectionItems($collection_id)
    {
        $collection = Collection::with('feeds.user')->find($collection_id);
        if (!$collection) return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        return ResponseHelper::sendResponse($collection->feeds, 'Collection feeds fetched successfully.');
    }

    public function destroyCollectionFeed($collection_id, $feed_id)
    {
        $collection = Collection::find($collection_id);
        if (!$collection) return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);

        $feed = Feed::find($feed_id);
        if (!$feed) return ResponseHelper::sendResponse([], 'Feed Not Found!', false, 404);

        $collection->feeds()->detach($feed_id);
        return ResponseHelper::sendResponse(null, 'Feed removed from collection successfully.');
    }
}

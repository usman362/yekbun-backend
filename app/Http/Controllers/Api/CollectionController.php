<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CollectionFeed;
use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Feed collections — same idea as music playlists:
 *   Collection       ≈ UserPlaylistGroup  (title + visibility + owner)
 *   CollectionFeed   ≈ UserPlaylist       (one feed inside a collection)
 *
 * Mobile UI (Add to Collection): list cards → add existing; or create with
 * title + Private/Public/Friends/Family and optionally drop the current feed in.
 */
class CollectionController extends Controller
{
    private const VISIBILITIES = ['private', 'public', 'friends', 'family'];

    /** POST /create-collection */
    public function insert(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:120',
            'visibility' => 'nullable|string|in:' . implode(',', self::VISIBILITIES),
            'image' => 'nullable|file|image|max:10240',
            'feed_id' => 'nullable|string',
        ]);

        $collection = new Collection();
        $collection->title = trim($request->title);
        $collection->visibility = $this->normalizeVisibility($request->visibility);
        $collection->user_id = Auth::id();
        if ($request->hasFile('image')) {
            $collection->image = Helpers::fileCDNUpload($request->file('image'), 'images/collections');
        }
        $collection->save();

        if ($request->filled('feed_id')) {
            $added = $this->addFeedToCollection($collection, $request->feed_id);
            if ($added['error']) {
                return ResponseHelper::sendResponse($this->transformCollection($collection), $added['error'], false, $added['status']);
            }
        }

        return ResponseHelper::sendResponse(
            $this->transformCollection($collection, $request->feed_id),
            'Collection successfully created.'
        );
    }

    /** POST /add-to-collection  body: collection_id, feed_id */
    public function add_to_collection(Request $request)
    {
        $request->validate([
            'collection_id' => 'required',
            'feed_id' => 'required',
        ]);

        $collection = $this->ownedCollection($request->collection_id);
        if (!$collection) {
            return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        }

        $added = $this->addFeedToCollection($collection, $request->feed_id);
        if ($added['error']) {
            return ResponseHelper::sendResponse([], $added['error'], false, $added['status']);
        }

        return ResponseHelper::sendResponse(
            $this->transformCollection($collection, $request->feed_id),
            'Successfully added to collection.'
        );
    }

    /** GET /list-collections?feed_id= optional — marks contains_feed on each card */
    public function get_collection(Request $request)
    {
        $feedId = $request->query('feed_id');
        $collections = Collection::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => $this->transformCollection($c, $feedId))
            ->values();

        return ResponseHelper::sendResponse($collections, 'Collections has been Fetched Successfully.');
    }

    /** POST /edit-collection/{id}  — title / visibility / cover, like edit-playlist */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:120',
            'visibility' => 'nullable|string|in:' . implode(',', self::VISIBILITIES),
            'image' => 'nullable|file|image|max:10240',
        ]);

        $collection = $this->ownedCollection($id);
        if (!$collection) {
            return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        }

        if ($request->filled('title')) {
            $collection->title = trim($request->title);
        }
        if ($request->filled('visibility')) {
            $collection->visibility = $this->normalizeVisibility($request->visibility);
        }
        if ($request->hasFile('image')) {
            $collection->image = Helpers::fileCDNUpload($request->file('image'), 'images/collections');
        }
        $collection->save();

        return ResponseHelper::sendResponse($this->transformCollection($collection), 'Collection has been Updated Successfully.');
    }

    /** DELETE /remove-collection/{id} */
    public function destroy($id)
    {
        $collection = $this->ownedCollection($id);
        if (!$collection) {
            return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        }

        CollectionFeed::where('collection_id', (string) $collection->getKey())->delete();
        $collection->delete();

        return ResponseHelper::sendResponse([], 'Collection has been Deleted Successfully.');
    }

    /** GET /list-collection-items/{collection_id} */
    public function listCollectionItems($collection_id)
    {
        $collection = $this->ownedCollection($collection_id);
        if (!$collection) {
            return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        }

        $feedIds = CollectionFeed::where('collection_id', (string) $collection->getKey())
            ->orderBy('created_at', 'desc')
            ->pluck('feed_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $feeds = [];
        foreach ($feedIds as $fid) {
            $feed = $this->findFeed($fid);
            if ($feed) {
                $feed->load('user');
                $feeds[] = $feed;
            }
        }

        return ResponseHelper::sendResponse($feeds, 'Collection feeds fetched successfully.');
    }

    /** DELETE /collections/{collection_id}/feeds/{feed_id} */
    public function destroyCollectionFeed($collection_id, $feed_id)
    {
        $collection = $this->ownedCollection($collection_id);
        if (!$collection) {
            return ResponseHelper::sendResponse([], 'Collection Not Found!', false, 404);
        }

        $deleted = CollectionFeed::where('collection_id', (string) $collection->getKey())
            ->where('feed_id', (string) $feed_id)
            ->delete();

        if (!$deleted) {
            return ResponseHelper::sendResponse([], 'Feed Not Found!', false, 404);
        }

        return ResponseHelper::sendResponse(null, 'Feed removed from collection successfully.');
    }

    /* ───────────────────────── internals ───────────────────────── */

    private function addFeedToCollection(Collection $collection, $feedId): array
    {
        $feed = $this->findFeed($feedId);
        if (!$feed) {
            return ['error' => 'Feed Not Found!', 'status' => 404];
        }

        $cid = (string) $collection->getKey();
        $fid = (string) $feed->getKey();

        $exists = CollectionFeed::where('collection_id', $cid)->where('feed_id', $fid)->first();
        if ($exists) {
            return ['error' => 'Already Added in Collection!', 'status' => 403];
        }

        CollectionFeed::create([
            'collection_id' => $cid,
            'feed_id' => $fid,
            'user_id' => Auth::id(),
        ]);

        if (empty($collection->image)) {
            $thumb = $this->feedThumbnail($feed);
            if ($thumb) {
                $collection->image = $thumb;
                $collection->save();
            }
        }

        return ['error' => null, 'status' => 200];
    }

    private function transformCollection(Collection $collection, $checkFeedId = null): array
    {
        $cid = (string) $collection->getKey();
        $itemIds = CollectionFeed::where('collection_id', $cid)
            ->pluck('feed_id')
            ->map(fn ($id) => (string) $id)
            ->values();

        $image = $collection->image;
        if (empty($image) && $itemIds->isNotEmpty()) {
            $first = $this->findFeed($itemIds->first());
            $image = $this->feedThumbnail($first) ?: 'images/collection_feed_empty/empty_feed_collection.jpeg';
        }
        if (empty($image)) {
            $image = 'images/collection_feed_empty/empty_feed_collection.jpeg';
        }

        $contains = false;
        if ($checkFeedId) {
            $contains = $itemIds->contains((string) $checkFeedId);
        }

        return [
            '_id' => $cid,
            'id' => $cid,
            'title' => $collection->title,
            'image' => $image,
            'visibility' => $this->normalizeVisibility($collection->visibility),
            'user_id' => $collection->user_id,
            'items_count' => $itemIds->count(),
            'feed_id' => $itemIds,
            'contains_feed' => $contains,
            'created_at' => $collection->created_at,
            'updated_at' => $collection->updated_at,
        ];
    }

    private function feedThumbnail(?Feed $feed): ?string
    {
        if (!$feed) {
            return null;
        }
        if (!empty($feed->images) && is_array($feed->images)) {
            $first = $feed->images[0] ?? null;
            $path = is_array($first) ? ($first['path'] ?? null) : $first;
            if ($path) {
                return $path;
            }
        }
        if (!empty($feed->image) && is_string($feed->image)) {
            return $feed->image;
        }
        if (!empty($feed->videos) && is_array($feed->videos)) {
            $first = $feed->videos[0] ?? null;
            if (is_array($first)) {
                return $first['thumbnail'] ?? $first['path'] ?? null;
            }
        }
        if (!empty($feed->background_image)) {
            return $feed->background_image;
        }

        return null;
    }

    private function ownedCollection($id): ?Collection
    {
        try {
            $collection = Collection::find($id);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$collection) {
            return null;
        }
        if ((string) $collection->user_id !== (string) Auth::id()) {
            return null;
        }

        return $collection;
    }

    private function findFeed($id): ?Feed
    {
        try {
            return Feed::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeVisibility($value): string
    {
        $v = strtolower(trim((string) $value));
        return in_array($v, self::VISIBILITIES, true) ? $v : 'private';
    }
}

<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Log;
use App\Models\Story;
use App\Models\FeedReason;
use App\Models\StoryTime;
use App\Models\DeletionCards;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreStoryRequest;
use App\Helpers\Helpers;
use App\Http\Requests\UpdateStoryRequest;
use App\Models\Cards;

class StoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $app = request()->app ?? 'main';
        $stories = Story::where('app', $app)->get();

        return response()->json([
            'status' => true,
            'stories' => $stories,
            'app' => $app,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreStoryRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreStoryRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('thumbnail')) {
            $validated["thumbnail_path"] = $request->thumbnail->store("/stories", "public");
        }

        if ($request->hasFile('media')) {
            $validated["media_path"] = $request->media->store("/stories", "public");
        }

        $story = Story::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Story successfully created.',
            'story' => $story,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateStoryRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateStoryRequest $request, $id)
    {
        $validated = $request->validated();

        if ($request->hasFile('thumbnail')) {
            $validated["thumbnail_path"] = $request->thumbnail->store("/stories", "public");
        }

        if ($request->hasFile('media')) {
            $validated["media_path"] = $request->media->store("/stories", "public");
        }

        $story = Story::find($id);
        if (!$story) {
            return response()->json([
                'status' => false,
                'message' => 'Story not found.',
            ], 404);
        }

        $story->fill($validated);
        $story->save();

        return response()->json([
            'status' => true,
            'message' => 'Story successfully updated.',
            'story' => $story,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $story = Story::find($id);
        if (!$story) {
            return response()->json([
                'status' => false,
                'message' => 'Story not found.',
            ], 404);
        }

        if ($story->thumbnail_path) {
            Storage::delete($story->thumbnail_path);
        }

        if ($story->media_path) {
            Storage::delete($story->media_path);
        }

        $story->delete();

        return response()->json([
            'status' => true,
            'message' => 'Story successfully deleted.',
        ]);
    }

    public function deleteStoryThumbnail($id)
    {
        $story = Story::find($id);
        if (!$story || !isset($story->thumbnail_path)) {
            return response()->json(['status' => false, 'message' => 'Story or thumbnail not found.'], 404);
        }

        $path = public_path('storage/' . $story->thumbnail_path);
        if (file_exists($path)) {
            unlink($path);
        }

        $story->thumbnail_path = null;
        $story->save();

        return response()->json(['status' => true, 'message' => 'Thumbnail successfully deleted.']);
    }

    public function deleteStoryMedia($id)
    {
        $story = Story::find($id);
        if (!$story || !isset($story->media_path)) {
            return response()->json(['status' => false, 'message' => 'Story or media not found.'], 404);
        }

        $path = public_path('storage/' . $story->media_path);
        if (file_exists($path)) {
            unlink($path);
        }

        $story->media_path = null;
        $story->save();

        return response()->json(['status' => true, 'message' => 'Media successfully deleted.']);
    }

    public function ManageStories()
    {
        $cards = Cards::get();

        return response()->json([
            'status' => true,
            'cards' => $cards,
        ]);
    }

    public function deleteCard(Request $request, $id)
    {
        $card = Cards::find($id);
        if (!$card) {
            return response()->json(['status' => false, 'message' => 'Card not found.'], 404);
        }

        $reasonId = $request->input('reason');
        $reasonTitle = $request->input('reason_title');
        $reason_description = $request->input('reason_description');

        $reason = FeedReason::find($reasonId);

        if (!$reason) {
            return response()->json(['status' => false, 'message' => 'Reason not found.'], 404);
        }

        DeletionCards::create([
            'card_id' => $id,
            'reason_id' => $reasonId,
            'reason_title' => $reasonTitle,
            'reason_description' => $reason_description,
        ]);

        $card->delete();

        return response()->json([
            'status' => true,
            'message' => 'Card deleted successfully.',
        ]);
    }

    public function ManageStoriestwo()
    {
        return response()->json(['status' => true, 'message' => 'ManageStoriestwo function called.']);
    }

    public function Listcard()
    {
        $cards = Cards::get();

        return response()->json([
            'status' => true,
            'cards' => $cards,
        ]);
    }

    public function Cardstore(Request $request)
    {
        $request->validate([
            'card_name' => 'required',
        ]);

        $model = new Cards();
        $model->name = $request->card_name;

        if ($request->hasFile('card_image')) {
            $imgPath = Helpers::fileUpload($request->card_image, "images/card_image");
            $model->image = $imgPath;
        }

        if ($model->save()) {
            return response()->json([
                'status' => true,
                'message' => 'Card has been inserted.',
                'card' => $model,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add card.',
            ]);
        }
    }

    public function destroycard(Cards $card)
    {
        try {
            Log::info('Attempting to delete card with ID: ' . $card->_id);
            $card->delete();
            Log::info('Successfully deleted card with ID: ' . $card->_id);
            return response()->json([
                'status' => true,
                'message' => 'Card has been deleted!',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting card: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!',
            ]);
        }
    }
    public function storage_setting()
    {
        try {
            $storyTime = StoryTime::first();
            return response()->json(['status' => true, 'data' => $storyTime], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching story time settings: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to fetch story time settings'], 500);
        }
    }
    
    public function storetime(Request $request)
    {
        try {
            $validated = $request->validate([
                'length' => 'nullable|string|min:0|max:100',
                'is_active' => 'nullable|string',
            ]);
    
            $storyTime = StoryTime::first(); // Assuming there's only one record
            if ($storyTime) {
                $storyTime->update($validated);
            } else {
                StoryTime::create($validated);
            }
    
            return response()->json(['status' => true, 'message' => 'Story Time updated successfully!'], 200);
        } catch (\Exception $e) {
            Log::error('Error updating Story Time: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to update Story Time'], 500);
        }
    }
    
}

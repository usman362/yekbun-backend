<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BackgroundFeed;
use App\Models\Emoji;
use Illuminate\Http\Request;

class FeedsSettingsAdminController extends Controller
{
    public function backgrounds()
    {
        $rows = BackgroundFeed::orderBy('created_at', 'desc')->get()->map(fn ($b) => [
            'id' => (string) $b->_id,
            'name' => $b->name ?? 'Banner',
            'url' => Helpers::mediaUrl($b->image) ?? '',
        ]);

        return ResponseHelper::sendResponse($rows, 'Backgrounds loaded.');
    }

    public function storeBackground(Request $request)
    {
        $request->validate(['name' => 'required|string', 'url' => 'required|string']);
        $b = BackgroundFeed::create(['name' => $request->name, 'image' => $request->url]);

        return ResponseHelper::sendResponse(['id' => (string) $b->_id], 'Background added.');
    }

    public function updateBackground(Request $request, string $id)
    {
        $b = BackgroundFeed::find($id);
        if (!$b) {
            return ResponseHelper::sendResponse([], 'Not found.', false, 404);
        }
        if ($request->has('name')) {
            $b->name = $request->name;
        }
        if ($request->has('url')) {
            $b->image = $request->url;
        }
        $b->save();

        return ResponseHelper::sendResponse($b, 'Updated.');
    }

    public function destroyBackground(string $id)
    {
        $b = BackgroundFeed::find($id);
        if (!$b) {
            return ResponseHelper::sendResponse([], 'Not found.', false, 404);
        }
        $b->delete();

        return ResponseHelper::sendResponse([], 'Deleted.');
    }

    public function emojis()
    {
        $rows = Emoji::orderBy('name')->get()->map(fn ($e) => [
            'id' => (string) $e->_id,
            'name' => $e->name ?? '',
            'url' => Helpers::mediaUrl($e->image) ?? '',
            'enabled' => true,
        ]);

        return ResponseHelper::sendResponse($rows, 'Emojis loaded.');
    }

    public function storeEmoji(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $e = new Emoji;
        $e->name = $request->name;
        if ($request->filled('url')) {
            $e->image = $request->url;
        }
        $e->save();

        return ResponseHelper::sendResponse(['id' => (string) $e->_id], 'Emoji added.');
    }

    public function destroyEmoji(string $id)
    {
        $e = Emoji::find($id);
        if (!$e) {
            return ResponseHelper::sendResponse([], 'Not found.', false, 404);
        }
        $e->delete();

        return ResponseHelper::sendResponse([], 'Deleted.');
    }
}

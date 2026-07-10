<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\History;
use App\Models\HistoryCategory;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index()
    {
        $histories = History::where('status', '1')->orderBy('created_at', 'desc')->get();
        $histories->transform(function ($history) {
            $history->comments_count = $history->comments->count();
            $history->voice_comments_count = $history->voice_comments->count();
            $history->likes_count = $history->likes->count();
            $history->views_count = $history->views->count();
            $history->shares_count = $history->shares->count();
            $this->presentMedia($history);
            return $history;
        });
        return ResponseHelper::sendResponse($histories, 'History has been Fetch Successfully!');
    }

    public function categorgy_history($id)
    {
        $history = History::select('_id', 'description', 'title', 'category_id', 'created_at')
            ->with('gallery')
            ->where('category_id', $id)
            ->inRandomOrder()
            ->limit(8)
            ->get();

        if ($history->isNotEmpty()) {
            $history = $history->map(function ($h) {
                $h->setAttribute('formatted_created_at', $h->created_at->format('M d Y'));
                return $h;
            });
            return response()->json(['success' => true, 'data' => $history]);
        }
        return response()->json(['success' => false, 'message' => 'No history found.']);
    }

    public function cover_history()
    {
        $history = History::select('_id', 'title', 'description', 'category_id', 'created_at')
            ->with('gallery')
            ->limit(3)
            ->get();

        if ($history->isNotEmpty()) {
            $history = $history->map(function ($h) {
                $h->setAttribute('formatted_created_at', $h->created_at->format('M d Y'));
                return $h;
            });
            return response()->json(['success' => true, 'data' => $history]);
        }
        return response()->json(['success' => false, 'message' => 'No history found.']);
    }

    public function categories()
    {
        $categories = HistoryCategory::all();
        if ($categories->isNotEmpty()) {
            return response()->json(['success' => true, 'data' => $categories]);
        }
        return response()->json(['success' => false, 'message' => 'No history category found.']);
    }

    public function detail($id)
    {
        $history = History::select('_id', 'title', 'description', 'category_id', 'created_at')
            ->with(['history_category', 'gallery'])
            ->find($id);

        if ($history) {
            $history->setAttribute('formatted_created_at', $history->created_at->format('M d Y'));
            return response()->json(['success' => true, 'data' => $history]);
        }
        return response()->json(['success' => false, 'message' => 'No history found.']);
    }

    public function search(Request $request)
    {
        $history = History::select('_id', 'description', 'title', 'category_id')
            ->with('gallery')
            ->where('title', 'like', '%' . $request->search . '%')
            ->get();

        if ($history->isNotEmpty()) {
            return response()->json(['success' => true, 'data' => $history]);
        }
        return response()->json(['success' => false, 'message' => 'No history found.']);
    }

    /** Resolve stored relative CDN paths to full URLs for mobile clients. */
    private function presentMedia(History $history): void
    {
        if (!empty($history->thumbnail)) {
            $history->thumbnail = Helpers::mediaUrl($history->thumbnail);
        }
        if (is_array($history->video)) {
            $history->video = array_map(function ($v) {
                if (is_array($v) && !empty($v['path'])) {
                    $v['path'] = Helpers::mediaUrl($v['path']);
                }
                return $v;
            }, $history->video);
        }
    }
}

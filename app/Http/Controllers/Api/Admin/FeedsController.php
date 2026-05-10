<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Feed;
use App\Models\FeedComments;
use App\Models\ReportFeeds;
use App\Models\Report;
use App\Models\ReportComments;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FeedsController extends Controller
{
    public function latest(Request $request)
    {
        $perPage = (int) $request->get('per_page', 8);
        $page    = (int) $request->get('page', 1);
        $filter  = $request->get('filter', 'all');

        $query = Feed::whereNull('is_deleted')
            ->orWhere('is_deleted', false);

        if ($filter === 'user') {
            $query->where('user_type', 'friends & family');
        } elseif ($filter === 'channel') {
            $query->where('user_type', 'channel');
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $feeds = $this->transformFeeds(collect($paginated->items()));

        return ResponseHelper::sendResponse([
            'feeds'     => $feeds,
            'total'     => $paginated->total(),
            'page'      => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ], 'Latest feeds fetched.');
    }

    public function onHold(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $reportedFeedIds = ReportFeeds::pluck('feed_id')->unique()->toArray();

        $query = Feed::whereIn('_id', $reportedFeedIds);

        if ($filter === 'user') {
            $query->where('user_type', 'friends & family');
        } elseif ($filter === 'channel') {
            $query->where('user_type', 'channel');
        }

        $feeds = $this->transformFeeds($query->orderBy('created_at', 'desc')->get());

        return ResponseHelper::sendResponse($feeds, 'On-hold feeds fetched.');
    }

    public function stats()
    {
        $reportedFeeds    = ReportFeeds::distinct('feed_id')->count();
        $reportedComments = ReportComments::count();

        return ResponseHelper::sendResponse([
            'reported_feeds'    => $reportedFeeds,
            'reported_comments' => $reportedComments,
        ], 'Feed stats fetched.');
    }

    private function transformFeeds($feeds): array
    {
        $userIds = $feeds->pluck('user_id')->unique()->filter()->toArray();
        $users   = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $feedIds = $feeds->pluck('_id')->toArray();
        $commentsByFeed = FeedComments::whereIn('feed_id', $feedIds)
            ->where('comment_type', 'normal')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('feed_id');

        $reportCounts = ReportFeeds::whereIn('feed_id', $feedIds)
            ->get()
            ->groupBy('feed_id')
            ->map(fn($g) => $g->count());

        return $feeds->map(function ($feed) use ($users, $commentsByFeed, $reportCounts) {
            $user = $users->get($feed->user_id);

            $media = $this->buildMedia($feed);
            $firstImage = $media[0]['url'] ?? '';

            $feedComments = $commentsByFeed->get($feed->_id, collect());
            $commentUsers = User::whereIn('_id', $feedComments->pluck('user_id')->unique()->toArray())->get()->keyBy('_id');

            $comments = $feedComments->take(3)->map(function ($c) use ($commentUsers) {
                $cu = $commentUsers->get($c->user_id);
                return [
                    'id'        => $c->_id,
                    'username'  => $cu->username ?? $cu->name ?? 'User',
                    'avatar'    => Helpers::mediaUrl($cu->image) ?? '',
                    'text'      => $c->comment ?? '',
                    'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                ];
            })->values()->toArray();

            return [
                'id'        => $feed->_id,
                'username'  => $user->username ?? $user->name ?? 'Unknown',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'timestamp' => Carbon::parse($feed->created_at)->diffForHumans(),
                'image'     => $firstImage,
                'media'     => count($media) > 1 ? $media : [],
                'views'     => (int) ($feed->views_count ?? 0),
                'shares'    => (int) ($feed->shares_count ?? 0),
                'edits'     => 0,
                'reports'   => (int) ($reportCounts->get($feed->_id, 0)),
                'reactions' => (int) ($feed->likes_count ?? 0),
                'flags'     => (int) ($reportCounts->get($feed->_id, 0)),
                'maxFlags'  => 5,
                'location'  => $feed->location ?? null,
                'comments'  => $comments,
            ];
        })->values()->toArray();
    }

    private function buildMedia(Feed $feed): array
    {
        $media = [];

        if (!empty($feed->images) && is_array($feed->images)) {
            foreach ($feed->images as $img) {
                $path = $img['path'] ?? '';
                $media[] = [
                    'id'   => md5($path),
                    'type' => 'image',
                    'url'  => Helpers::mediaUrl($path) ?? '',
                ];
            }
        }

        if (!empty($feed->videos) && is_array($feed->videos)) {
            foreach ($feed->videos as $vid) {
                $path = $vid['path'] ?? '';
                $media[] = [
                    'id'   => md5($path),
                    'type' => 'video',
                    'url'  => Helpers::mediaUrl($path) ?? '',
                ];
            }
        }

        if (empty($media) && $feed->image) {
            $media[] = [
                'id'   => 'main',
                'type' => 'image',
                'url'  => Helpers::mediaUrl($feed->image),
            ];
        }

        return $media;
    }
}

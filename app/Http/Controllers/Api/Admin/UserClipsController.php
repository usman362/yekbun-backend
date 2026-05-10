<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ClipTemplates;
use App\Models\Clips;
use App\Models\ClipsViews;
use App\Models\ClipsLikes;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;

class UserClipsController extends Controller
{
    public function templates()
    {
        $templates = ClipTemplates::orderBy('created_at', 'desc')->get();

        $result = $templates->map(function ($t) {
            return [
                'id'              => $t->_id,
                'title'           => $t->title ?? $t->name ?? 'Untitled',
                'date'            => $t->created_at ? Carbon::parse($t->created_at)->format('d/m/Y') : '',
                'image'           => $t->thumbnail ?? $t->image ?? '',
                'variant'         => $t->variant ?? 'portrait',
                'educatedPrice'   => $t->educated_price ?? 'Free',
                'cultivatedPrice' => $t->cultivated_price ?? 'Free',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Templates fetched.');
    }

    public function clips()
    {
        $clips = Clips::orderBy('created_at', 'desc')->get();

        $userIds = $clips->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $result = $clips->map(function ($c) use ($users) {
            $user = $users->get($c->user_id);
            return [
                'id'        => $c->_id,
                'username'  => $user->username ?? $user->name ?? 'Unknown',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                'image'     => Helpers::mediaUrl($c->thumbnail) ?? '',
                'media'     => [],
                'views'     => (int) ClipsViews::where('clip_id', $c->_id)->count(),
                'shares'    => 0,
                'edits'     => 0,
                'reports'   => 0,
                'reactions' => (int) ClipsLikes::where('clip_id', $c->_id)->count(),
                'flags'     => 0,
                'maxFlags'  => 5,
                'location'  => null,
                'comments'  => [],
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'User clips fetched.');
    }

    public function reported()
    {
        $reportedClipIds = Report::where('report_type', 'clip')
            ->orWhere('reported_type', 'clip')
            ->pluck('reported_post_id')
            ->unique()
            ->filter()
            ->toArray();

        if (empty($reportedClipIds)) {
            return ResponseHelper::sendResponse([], 'No reported clips.');
        }

        $clips = Clips::whereIn('_id', $reportedClipIds)->get();
        $userIds = $clips->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $reportCounts = Report::whereIn('reported_post_id', $reportedClipIds)
            ->get()
            ->groupBy('reported_post_id')
            ->map(fn($g) => $g->count());

        $result = $clips->map(function ($c) use ($users, $reportCounts) {
            $user = $users->get($c->user_id);
            $reports = $reportCounts->get($c->_id, 0);
            return [
                'id'        => $c->_id,
                'username'  => $user->username ?? $user->name ?? 'Unknown',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                'image'     => Helpers::mediaUrl($c->thumbnail) ?? '',
                'media'     => [],
                'views'     => (int) ClipsViews::where('clip_id', $c->_id)->count(),
                'shares'    => 0,
                'edits'     => 0,
                'reports'   => (int) $reports,
                'reactions' => (int) ClipsLikes::where('clip_id', $c->_id)->count(),
                'flags'     => (int) $reports,
                'maxFlags'  => 5,
                'location'  => null,
                'comments'  => [],
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Reported clips fetched.');
    }
}

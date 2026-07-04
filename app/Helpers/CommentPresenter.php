<?php

namespace App\Helpers;

use App\Models\FeedComments;
use Carbon\Carbon;

/**
 * Shared mapper for the content detail-modal comment lists (History, AI Videos, …).
 *
 * Comments for these content types live in `feed_comments`, keyed by `feed_id` +
 * `feed_type` ('history' | 'ai_videos'). This returns the top-level comments (no
 * replies) shaped exactly how the appdash gallery modal renders them.
 */
class CommentPresenter
{
    public static function forFeed($feedId, string $feedType): array
    {
        $comments = FeedComments::where('feed_id', (string) $feedId)
            ->where('feed_type', $feedType)
            ->whereNull('parent_id')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return $comments->map(function ($c) {
            $user = $c->user;
            $isAudio = ($c->comment_type ?? '') === 'audio' || !empty($c->audio);

            return [
                'id'        => (string) $c->getKey(),
                'username'  => $user->name ?? $user->username ?? 'User',
                'avatar'    => Helpers::mediaUrl($user->image ?? null) ?? '',
                'text'      => (string) ($c->comment ?? ''),
                'audio'     => $isAudio ? (Helpers::mediaUrl($c->audio ?? null) ?? '') : null,
                'is_audio'  => $isAudio,
                'timestamp' => $c->created_at ? Carbon::parse($c->created_at)->diffForHumans() : '',
            ];
        })->values()->all();
    }
}

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

        return $comments->map(fn($c) => self::present($c))->values()->all();
    }

    /**
     * Create a comment on this content as the currently-authenticated ADMIN and return it in
     * the same shape the modal renders. History / AI Videos are admin-authored content, so the
     * comment is attributed to the logged-in admin (name + avatar).
     */
    public static function addComment($feedId, string $feedType, string $text): array
    {
        $admin = auth()->user();

        $comment = FeedComments::create([
            'user_id'      => $admin ? (string) $admin->getKey() : null,
            'feed_id'      => (string) $feedId,
            'comment'      => $text,
            'feed_type'    => $feedType,
            'comment_type' => 'normal',
            'status'       => 'active',
        ]);

        // Attach the admin so present() renders their name/avatar without a second lookup.
        $comment->setRelation('user', $admin);
        return self::present($comment);
    }

    /** Map one FeedComments row to the modal comment shape. */
    protected static function present($c): array
    {
        $user = $c->user;
        $isAudio = ($c->comment_type ?? '') === 'audio' || !empty($c->audio);

        return [
            'id'        => (string) $c->getKey(),
            'username'  => $user->name ?? $user->username ?? 'User',
            'avatar'    => Helpers::mediaUrl($user->image ?? null) ?? '',
            'text'      => (string) ($c->comment ?? ''),
            'audio'     => $isAudio ? (Helpers::mediaUrl($c->audio ?? null) ?? '') : null,
            'is_audio'  => $isAudio,
            'timestamp' => $c->created_at ? Carbon::parse($c->created_at)->diffForHumans() : 'just now',
        ];
    }
}

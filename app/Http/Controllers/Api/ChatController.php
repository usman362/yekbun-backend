<?php

namespace App\Http\Controllers\Api;

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageReacted;
use App\Events\Chat\MessageRead;
use App\Events\Chat\NewMessage;
use App\Events\Chat\UserTyping;
use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /* ================================================================
     *  CONVERSATIONS — list / create / show
     * ================================================================ */

    /** GET /chat/conversations — paginated list with last message + unread count */
    public function conversations(Request $request)
    {
        $userId = (string) Auth::id();
        $perPage = (int) ($request->query('per_page', 20));

        $participantIds = ConversationParticipant::where('user_id', $userId)
            ->whereNull('removed_at')
            ->pluck('conversation_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return ResponseHelper::sendResponse([], 'No conversations.');
        }

        $conversations = Conversation::whereIn('_id', $participantIds->all())
            ->orderBy('last_message_at', 'desc')
            ->paginate($perPage);

        $items = collect($conversations->items())->map(fn ($c) => $this->transformConversation($c, $userId));

        return ResponseHelper::sendResponse([
            'data'         => $items,
            'current_page' => $conversations->currentPage(),
            'last_page'    => $conversations->lastPage(),
            'total'        => $conversations->total(),
        ], 'Conversations fetched.');
    }

    /** POST /chat/conversations — create private (1-to-1) or group */
    public function createConversation(Request $request)
    {
        $request->validate([
            'type'        => 'required|in:private,group',
            'user_id'     => 'required_if:type,private|string',
            'name'        => 'required_if:type,group|string|max:120',
            'description' => 'nullable|string|max:500',
            'image'       => 'nullable|file|image|max:10240',
            'member_ids'  => 'required_if:type,group|array|min:1',
            'member_ids.*' => 'string',
        ]);

        $userId = (string) Auth::id();

        if ($request->type === 'private') {
            $otherId = (string) $request->user_id;
            if ($otherId === $userId) {
                return ResponseHelper::sendResponse([], 'Cannot start a conversation with yourself.', false, 422);
            }
            if (!User::find($otherId)) {
                return ResponseHelper::sendResponse([], 'User not found.', false, 404);
            }

            $existing = $this->findPrivateConversation($userId, $otherId);
            if ($existing) {
                return ResponseHelper::sendResponse(
                    $this->transformConversation($existing, $userId),
                    'Conversation already exists.'
                );
            }

            $conversation = Conversation::create([
                'type'       => 'private',
                'created_by' => $userId,
            ]);

            $now = Carbon::now();
            ConversationParticipant::insert([
                ['conversation_id' => (string) $conversation->getKey(), 'user_id' => $userId, 'role' => 'member', 'joined_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['conversation_id' => (string) $conversation->getKey(), 'user_id' => $otherId, 'role' => 'member', 'joined_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ]);

            return ResponseHelper::sendResponse(
                $this->transformConversation($conversation->fresh(), $userId),
                'Conversation created.'
            );
        }

        // Group creation
        $conversation = new Conversation();
        $conversation->type = 'group';
        $conversation->name = trim($request->name);
        $conversation->description = $request->description;
        $conversation->created_by = $userId;
        if ($request->hasFile('image')) {
            $conversation->image = Helpers::fileCDNUpload($request->file('image'), 'images/chat/groups');
        }
        $conversation->save();

        $cid = (string) $conversation->getKey();
        $now = Carbon::now();

        $members = collect($request->member_ids)
            ->map(fn ($id) => (string) $id)
            ->push($userId)
            ->unique()
            ->values();

        $rows = $members->map(fn ($uid) => [
            'conversation_id' => $cid,
            'user_id'         => $uid,
            'role'            => $uid === $userId ? 'owner' : 'member',
            'joined_at'       => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->all();

        ConversationParticipant::insert($rows);

        $this->systemMessage($conversation, Auth::user()->name . ' created the group.');

        return ResponseHelper::sendResponse(
            $this->transformConversation($conversation->fresh(), $userId),
            'Group created.'
        );
    }

    /** GET /chat/conversations/{id} — single conversation detail */
    public function showConversation($id)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        return ResponseHelper::sendResponse(
            $this->transformConversation($conversation, $userId, true),
            'Conversation details.'
        );
    }

    /* ================================================================
     *  MESSAGES — list / send
     * ================================================================ */

    /** GET /chat/conversations/{id}/messages?page=&per_page= */
    public function messages($conversationId, Request $request)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($conversationId, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        $perPage = (int) ($request->query('per_page', 30));

        $messages = Message::where('conversation_id', (string) $conversation->getKey())
            ->where(function ($q) use ($userId) {
                $q->whereNull('deleted_for')
                  ->orWhere('deleted_for', 'not', 'all');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = collect($messages->items())->map(fn ($m) => $this->transformMessage($m, $userId));

        return ResponseHelper::sendResponse([
            'data'         => $items,
            'current_page' => $messages->currentPage(),
            'last_page'    => $messages->lastPage(),
            'total'        => $messages->total(),
        ], 'Messages fetched.');
    }

    /** POST /chat/conversations/{id}/messages — send text/media message */
    public function sendMessage($conversationId, Request $request)
    {
        $request->validate([
            'type'        => 'nullable|in:text,image,voice,file',
            'body'        => 'required_without:media|nullable|string|max:5000',
            'media'       => 'nullable|array',
            'media.*'     => 'file|max:51200',
            'reply_to_id' => 'nullable|string',
        ]);

        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($conversationId, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        $type = $request->type ?? 'text';
        $mediaArr = [];

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $mime = $file->getMimeType();
                $cdnPath = Helpers::fileCDNUpload($file, 'chat/media');
                $mediaArr[] = [
                    'path'          => $cdnPath,
                    'mime'          => $mime,
                    'size'          => $file->getSize(),
                    'original_name' => $file->getClientOriginalName(),
                ];
            }
            if ($type === 'text' && !empty($mediaArr)) {
                $firstMime = $mediaArr[0]['mime'] ?? '';
                if (str_starts_with($firstMime, 'image/')) $type = 'image';
                elseif (str_starts_with($firstMime, 'audio/')) $type = 'voice';
                else $type = 'file';
            }
        }

        $message = Message::create([
            'conversation_id' => (string) $conversation->getKey(),
            'sender_id'       => $userId,
            'type'            => $type,
            'body'            => $request->body,
            'media'           => $mediaArr ?: null,
            'reply_to_id'     => $request->reply_to_id,
            'delivered_to'    => [$userId],
            'read_by'         => [['user_id' => $userId, 'read_at' => Carbon::now()->toISOString()]],
        ]);

        $conversation->last_message_id = (string) $message->getKey();
        $conversation->last_message_at = $message->created_at;
        $conversation->save();

        broadcast(new NewMessage($message))->toOthers();

        return ResponseHelper::sendResponse(
            $this->transformMessage($message, $userId),
            'Message sent.'
        );
    }

    /** POST /chat/conversations/{id}/forward — forward a message to another conversation */
    public function forwardMessage($conversationId, Request $request)
    {
        $request->validate([
            'message_id'              => 'required|string',
            'target_conversation_id'  => 'required|string',
        ]);

        $userId = (string) Auth::id();

        $source = $this->authorizedConversation($conversationId, $userId);
        if (!$source) {
            return ResponseHelper::sendResponse([], 'Source conversation not found.', false, 404);
        }

        $target = $this->authorizedConversation($request->target_conversation_id, $userId);
        if (!$target) {
            return ResponseHelper::sendResponse([], 'Target conversation not found.', false, 404);
        }

        $original = Message::where('_id', $request->message_id)
            ->where('conversation_id', (string) $source->getKey())
            ->first();
        if (!$original) {
            return ResponseHelper::sendResponse([], 'Message not found.', false, 404);
        }

        $forwarded = Message::create([
            'conversation_id'   => (string) $target->getKey(),
            'sender_id'         => $userId,
            'type'              => $original->type,
            'body'              => $original->body,
            'media'             => $original->media,
            'forwarded_from_id' => (string) $original->getKey(),
            'delivered_to'      => [$userId],
            'read_by'           => [['user_id' => $userId, 'read_at' => Carbon::now()->toISOString()]],
        ]);

        $target->last_message_id = (string) $forwarded->getKey();
        $target->last_message_at = $forwarded->created_at;
        $target->save();

        broadcast(new NewMessage($forwarded))->toOthers();

        return ResponseHelper::sendResponse(
            $this->transformMessage($forwarded, $userId),
            'Message forwarded.'
        );
    }

    /* ================================================================
     *  READ RECEIPTS / TYPING / DELIVERED
     * ================================================================ */

    /** POST /chat/conversations/{id}/read */
    public function markRead($conversationId, Request $request)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($conversationId, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        $now = Carbon::now();

        // Update participant last_read_at
        ConversationParticipant::where('conversation_id', (string) $conversation->getKey())
            ->where('user_id', $userId)
            ->update(['last_read_at' => $now]);

        // Stamp read_by on unread messages from others
        $unread = Message::where('conversation_id', (string) $conversation->getKey())
            ->where('sender_id', '!=', $userId)
            ->get()
            ->filter(function ($msg) use ($userId) {
                $readBy = $msg->read_by ?? [];
                return !collect($readBy)->contains('user_id', $userId);
            });

        $lastMsgId = null;
        foreach ($unread as $msg) {
            $readBy = $msg->read_by ?? [];
            $readBy[] = ['user_id' => $userId, 'read_at' => $now->toISOString()];
            $msg->read_by = $readBy;

            $delivered = $msg->delivered_to ?? [];
            if (!in_array($userId, $delivered)) {
                $delivered[] = $userId;
                $msg->delivered_to = $delivered;
            }
            $msg->save();
            $lastMsgId = (string) $msg->getKey();
        }

        broadcast(new MessageRead(
            (string) $conversation->getKey(),
            $userId,
            $now->toISOString(),
            $lastMsgId,
        ))->toOthers();

        return ResponseHelper::sendResponse(null, 'Marked as read.');
    }

    /** POST /chat/conversations/{id}/typing — body: { is_typing: bool } */
    public function typing($conversationId, Request $request)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($conversationId, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        broadcast(new UserTyping(
            (string) $conversation->getKey(),
            $userId,
            Auth::user()->name ?? 'User',
            (bool) $request->input('is_typing', true),
        ))->toOthers();

        return ResponseHelper::sendResponse(null, 'OK');
    }

    /* ================================================================
     *  DELETE MESSAGES
     * ================================================================ */

    /** DELETE /chat/messages/{id} — query: ?for_everyone=1 */
    public function deleteMessage($messageId, Request $request)
    {
        $userId = (string) Auth::id();
        $message = Message::find($messageId);
        if (!$message) {
            return ResponseHelper::sendResponse([], 'Message not found.', false, 404);
        }

        $conversation = $this->authorizedConversation($message->conversation_id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Not authorized.', false, 403);
        }

        $forEveryone = (bool) $request->query('for_everyone', false);

        if ($forEveryone) {
            if ((string) $message->sender_id !== $userId) {
                return ResponseHelper::sendResponse([], 'Only the sender can delete for everyone.', false, 403);
            }
            $message->deleted_for_everyone = true;
            $message->save();

            broadcast(new MessageDeleted(
                (string) $conversation->getKey(),
                (string) $message->getKey(),
                true,
            ))->toOthers();
        } else {
            $deletedFor = $message->deleted_for ?? [];
            if (!in_array($userId, $deletedFor)) {
                $deletedFor[] = $userId;
                $message->deleted_for = $deletedFor;
                $message->save();
            }
        }

        return ResponseHelper::sendResponse(null, 'Message deleted.');
    }

    /* ================================================================
     *  REACTIONS
     * ================================================================ */

    /** POST /chat/messages/{id}/reactions — body: { emoji: "👍" } */
    public function addReaction($messageId, Request $request)
    {
        $request->validate(['emoji' => 'required|string|max:10']);

        $userId = (string) Auth::id();
        $message = Message::find($messageId);
        if (!$message) {
            return ResponseHelper::sendResponse([], 'Message not found.', false, 404);
        }

        $conversation = $this->authorizedConversation($message->conversation_id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Not authorized.', false, 403);
        }

        $existing = MessageReaction::where('message_id', (string) $message->getKey())
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->emoji = $request->emoji;
            $existing->save();
        } else {
            MessageReaction::create([
                'message_id' => (string) $message->getKey(),
                'user_id'    => $userId,
                'emoji'      => $request->emoji,
            ]);
        }

        broadcast(new MessageReacted(
            (string) $conversation->getKey(),
            (string) $message->getKey(),
            $userId,
            Auth::user()->name ?? 'User',
            $request->emoji,
        ))->toOthers();

        return ResponseHelper::sendResponse(null, 'Reaction added.');
    }

    /** DELETE /chat/messages/{id}/reactions */
    public function removeReaction($messageId)
    {
        $userId = (string) Auth::id();
        $message = Message::find($messageId);
        if (!$message) {
            return ResponseHelper::sendResponse([], 'Message not found.', false, 404);
        }

        $conversation = $this->authorizedConversation($message->conversation_id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Not authorized.', false, 403);
        }

        MessageReaction::where('message_id', (string) $message->getKey())
            ->where('user_id', $userId)
            ->delete();

        broadcast(new MessageReacted(
            (string) $conversation->getKey(),
            (string) $message->getKey(),
            $userId,
            Auth::user()->name ?? 'User',
            null,
        ))->toOthers();

        return ResponseHelper::sendResponse(null, 'Reaction removed.');
    }

    /* ================================================================
     *  GROUP MANAGEMENT
     * ================================================================ */

    /** PUT /chat/conversations/{id}/group — update name / description / image */
    public function updateGroup($id, Request $request)
    {
        $request->validate([
            'name'        => 'nullable|string|max:120',
            'description' => 'nullable|string|max:500',
            'image'       => 'nullable|file|image|max:10240',
        ]);

        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation || $conversation->type !== 'group') {
            return ResponseHelper::sendResponse([], 'Group not found.', false, 404);
        }

        if (!$this->isGroupAdmin($id, $userId)) {
            return ResponseHelper::sendResponse([], 'Only group admins can update group info.', false, 403);
        }

        $changes = [];
        if ($request->filled('name')) {
            $conversation->name = trim($request->name);
            $changes[] = 'name_changed';
        }
        if ($request->has('description')) {
            $conversation->description = $request->description;
        }
        if ($request->hasFile('image')) {
            $conversation->image = Helpers::fileCDNUpload($request->file('image'), 'images/chat/groups');
            $changes[] = 'image_changed';
        }
        $conversation->save();

        foreach ($changes as $action) {
            broadcast(new ConversationUpdated(
                (string) $conversation->getKey(),
                $action,
                ['by' => $userId, 'name' => Auth::user()->name],
            ));
        }

        return ResponseHelper::sendResponse(
            $this->transformConversation($conversation->fresh(), $userId, true),
            'Group updated.'
        );
    }

    /** GET /chat/conversations/{id}/members */
    public function members($id)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        $participants = ConversationParticipant::where('conversation_id', (string) $conversation->getKey())
            ->whereNull('removed_at')
            ->get();

        $members = $participants->map(function ($p) {
            $user = User::find($p->user_id);
            return [
                'user_id'  => $p->user_id,
                'role'     => $p->role ?? 'member',
                'name'     => $user->name ?? null,
                'username' => $user->username ?? null,
                'image'    => $user->image ?? null,
                'muted'    => $p->isMuted(),
                'joined_at' => $p->joined_at?->toISOString(),
            ];
        });

        return ResponseHelper::sendResponse($members, 'Members fetched.');
    }

    /** POST /chat/conversations/{id}/members — body: { user_ids: [...] } */
    public function addMembers($id, Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'string',
        ]);

        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation || $conversation->type !== 'group') {
            return ResponseHelper::sendResponse([], 'Group not found.', false, 404);
        }

        if (!$this->isGroupAdmin($id, $userId)) {
            return ResponseHelper::sendResponse([], 'Only admins can add members.', false, 403);
        }

        $cid = (string) $conversation->getKey();
        $now = Carbon::now();
        $added = [];

        foreach ($request->user_ids as $uid) {
            $uid = (string) $uid;
            if (!User::find($uid)) continue;

            $existing = ConversationParticipant::where('conversation_id', $cid)
                ->where('user_id', $uid)
                ->first();

            if ($existing && $existing->removed_at) {
                $existing->removed_at = null;
                $existing->role = 'member';
                $existing->joined_at = $now;
                $existing->save();
                $added[] = $uid;
            } elseif (!$existing) {
                ConversationParticipant::create([
                    'conversation_id' => $cid,
                    'user_id'         => $uid,
                    'role'            => 'member',
                    'joined_at'       => $now,
                ]);
                $added[] = $uid;
            }
        }

        if (!empty($added)) {
            $names = User::whereIn('_id', $added)->pluck('name')->implode(', ');
            $this->systemMessage($conversation, Auth::user()->name . ' added ' . $names);
            broadcast(new ConversationUpdated($cid, 'member_added', ['user_ids' => $added]));
        }

        return ResponseHelper::sendResponse(['added' => $added], count($added) . ' member(s) added.');
    }

    /** DELETE /chat/conversations/{id}/members/{userId} */
    public function removeMember($id, $memberId)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation || $conversation->type !== 'group') {
            return ResponseHelper::sendResponse([], 'Group not found.', false, 404);
        }

        $memberId = (string) $memberId;

        // Members can leave themselves; admins can remove others
        if ($memberId !== $userId && !$this->isGroupAdmin($id, $userId)) {
            return ResponseHelper::sendResponse([], 'Only admins can remove members.', false, 403);
        }

        $cid = (string) $conversation->getKey();

        // Cannot remove the owner
        $targetPart = ConversationParticipant::where('conversation_id', $cid)
            ->where('user_id', $memberId)
            ->whereNull('removed_at')
            ->first();

        if (!$targetPart) {
            return ResponseHelper::sendResponse([], 'Member not found.', false, 404);
        }
        if ($targetPart->role === 'owner' && $memberId !== $userId) {
            return ResponseHelper::sendResponse([], 'Cannot remove the group owner.', false, 403);
        }

        $targetPart->removed_at = Carbon::now();
        $targetPart->save();

        $memberUser = User::find($memberId);
        $action = $memberId === $userId ? 'left' : 'was removed by ' . Auth::user()->name;
        $this->systemMessage($conversation, ($memberUser->name ?? 'User') . ' ' . $action);
        broadcast(new ConversationUpdated($cid, 'member_removed', ['user_id' => $memberId]));

        return ResponseHelper::sendResponse(null, 'Member removed.');
    }

    /** PUT /chat/conversations/{id}/members/{userId}/role — body: { role: 'admin'|'member' } */
    public function updateMemberRole($id, $memberId, Request $request)
    {
        $request->validate(['role' => 'required|in:admin,member']);

        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation || $conversation->type !== 'group') {
            return ResponseHelper::sendResponse([], 'Group not found.', false, 404);
        }

        if (!$this->isGroupOwner($id, $userId)) {
            return ResponseHelper::sendResponse([], 'Only the group owner can change roles.', false, 403);
        }

        $cid = (string) $conversation->getKey();
        $memberId = (string) $memberId;

        $part = ConversationParticipant::where('conversation_id', $cid)
            ->where('user_id', $memberId)
            ->whereNull('removed_at')
            ->first();

        if (!$part) {
            return ResponseHelper::sendResponse([], 'Member not found.', false, 404);
        }
        if ($part->role === 'owner') {
            return ResponseHelper::sendResponse([], 'Cannot change the owner role.', false, 403);
        }

        $part->role = $request->role;
        $part->save();

        broadcast(new ConversationUpdated($cid, 'admin_changed', [
            'user_id' => $memberId,
            'role'    => $request->role,
        ]));

        return ResponseHelper::sendResponse(null, 'Role updated.');
    }

    /** POST /chat/conversations/{id}/mute — body: { duration: 'forever'|'8h'|'1w' | null to unmute } */
    public function muteConversation($id, Request $request)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        $part = ConversationParticipant::where('conversation_id', (string) $conversation->getKey())
            ->where('user_id', $userId)
            ->whereNull('removed_at')
            ->first();

        if (!$part) {
            return ResponseHelper::sendResponse([], 'Not a participant.', false, 403);
        }

        $duration = $request->input('duration');
        if (!$duration) {
            $part->muted_until = null;
        } elseif ($duration === 'forever') {
            $part->muted_until = Carbon::now()->addYears(100);
        } elseif ($duration === '8h') {
            $part->muted_until = Carbon::now()->addHours(8);
        } elseif ($duration === '1w') {
            $part->muted_until = Carbon::now()->addWeek();
        } else {
            $part->muted_until = Carbon::now()->addHours(8);
        }
        $part->save();

        return ResponseHelper::sendResponse(
            ['muted_until' => $part->muted_until?->toISOString()],
            $duration ? 'Conversation muted.' : 'Conversation unmuted.'
        );
    }

    /** POST /chat/conversations/{id}/pin — body: { message_id: string | null to unpin } */
    public function pinMessage($id, Request $request)
    {
        $userId = (string) Auth::id();
        $conversation = $this->authorizedConversation($id, $userId);
        if (!$conversation) {
            return ResponseHelper::sendResponse([], 'Conversation not found.', false, 404);
        }

        if ($conversation->type === 'group' && !$this->isGroupAdmin($id, $userId)) {
            return ResponseHelper::sendResponse([], 'Only admins can pin messages.', false, 403);
        }

        $messageId = $request->input('message_id');
        if ($messageId) {
            $msg = Message::where('_id', $messageId)
                ->where('conversation_id', (string) $conversation->getKey())
                ->first();
            if (!$msg) {
                return ResponseHelper::sendResponse([], 'Message not found.', false, 404);
            }
        }

        $conversation->pinned_message_id = $messageId;
        $conversation->save();

        broadcast(new ConversationUpdated(
            (string) $conversation->getKey(),
            $messageId ? 'message_pinned' : 'message_unpinned',
            ['message_id' => $messageId],
        ));

        return ResponseHelper::sendResponse(null, $messageId ? 'Message pinned.' : 'Message unpinned.');
    }

    /* ================================================================
     *  HELPERS
     * ================================================================ */

    private function authorizedConversation($id, $userId): ?Conversation
    {
        try {
            $conversation = Conversation::find($id);
        } catch (\Throwable) {
            return null;
        }
        if (!$conversation) return null;

        $participant = ConversationParticipant::where('conversation_id', (string) $conversation->getKey())
            ->where('user_id', $userId)
            ->whereNull('removed_at')
            ->first();

        return $participant ? $conversation : null;
    }

    private function findPrivateConversation(string $userA, string $userB): ?Conversation
    {
        $aConvIds = ConversationParticipant::where('user_id', $userA)
            ->whereNull('removed_at')
            ->pluck('conversation_id')
            ->map(fn ($id) => (string) $id);

        $bConvIds = ConversationParticipant::where('user_id', $userB)
            ->whereNull('removed_at')
            ->pluck('conversation_id')
            ->map(fn ($id) => (string) $id);

        $shared = $aConvIds->intersect($bConvIds);
        if ($shared->isEmpty()) return null;

        return Conversation::whereIn('_id', $shared->all())
            ->where('type', 'private')
            ->first();
    }

    private function isGroupAdmin(string $conversationId, string $userId): bool
    {
        return ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('removed_at')
            ->whereIn('role', ['admin', 'owner'])
            ->exists();
    }

    private function isGroupOwner(string $conversationId, string $userId): bool
    {
        return ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('removed_at')
            ->where('role', 'owner')
            ->exists();
    }

    private function systemMessage(Conversation $conversation, string $text): Message
    {
        $message = Message::create([
            'conversation_id' => (string) $conversation->getKey(),
            'sender_id'       => null,
            'type'            => 'system',
            'body'            => $text,
        ]);

        $conversation->last_message_id = (string) $message->getKey();
        $conversation->last_message_at = $message->created_at;
        $conversation->save();

        broadcast(new NewMessage($message));

        return $message;
    }

    private function transformConversation(Conversation $conversation, string $userId, bool $withMembers = false): array
    {
        $cid = (string) $conversation->getKey();

        $participant = ConversationParticipant::where('conversation_id', $cid)
            ->where('user_id', $userId)
            ->whereNull('removed_at')
            ->first();

        // Unread count: messages after last_read_at from other senders
        $unread = 0;
        if ($participant && $participant->last_read_at) {
            $unread = Message::where('conversation_id', $cid)
                ->where('sender_id', '!=', $userId)
                ->where('created_at', '>', $participant->last_read_at)
                ->where(function ($q) {
                    $q->where('deleted_for_everyone', '!=', true)
                      ->orWhereNull('deleted_for_everyone');
                })
                ->count();
        } elseif ($participant) {
            $unread = Message::where('conversation_id', $cid)
                ->where('sender_id', '!=', $userId)
                ->where(function ($q) {
                    $q->where('deleted_for_everyone', '!=', true)
                      ->orWhereNull('deleted_for_everyone');
                })
                ->count();
        }

        // For private chats, resolve the other user's info
        $otherUser = null;
        if ($conversation->type === 'private') {
            $otherId = ConversationParticipant::where('conversation_id', $cid)
                ->where('user_id', '!=', $userId)
                ->whereNull('removed_at')
                ->value('user_id');
            if ($otherId) {
                $u = User::find($otherId);
                if ($u) {
                    $otherUser = [
                        'id'       => (string) $u->getKey(),
                        'name'     => $u->name,
                        'username' => $u->username,
                        'image'    => $u->image,
                    ];
                }
            }
        }

        $lastMsg = null;
        if ($conversation->last_message_id) {
            $lm = Message::find($conversation->last_message_id);
            if ($lm) {
                $lastMsg = [
                    'id'         => (string) $lm->getKey(),
                    'type'       => $lm->type,
                    'body'       => $lm->deleted_for_everyone ? null : $lm->body,
                    'sender_id'  => $lm->sender_id,
                    'created_at' => $lm->created_at?->toISOString(),
                ];
            }
        }

        $result = [
            'id'             => $cid,
            'type'           => $conversation->type,
            'name'           => $conversation->type === 'group' ? $conversation->name : ($otherUser['name'] ?? null),
            'image'          => $conversation->type === 'group' ? ($conversation->image ? Helpers::mediaUrl($conversation->image) : null) : ($otherUser['image'] ?? null),
            'description'    => $conversation->description,
            'other_user'     => $otherUser,
            'unread_count'   => $unread,
            'last_message'   => $lastMsg,
            'muted'          => $participant?->isMuted() ?? false,
            'pinned_message_id' => $conversation->pinned_message_id,
            'created_at'     => $conversation->created_at?->toISOString(),
            'last_message_at' => $conversation->last_message_at?->toISOString(),
        ];

        if ($withMembers && $conversation->type === 'group') {
            $parts = ConversationParticipant::where('conversation_id', $cid)
                ->whereNull('removed_at')
                ->get();
            $result['members_count'] = $parts->count();
            $result['members'] = $parts->map(function ($p) {
                $u = User::find($p->user_id);
                return [
                    'user_id'  => $p->user_id,
                    'role'     => $p->role ?? 'member',
                    'name'     => $u->name ?? null,
                    'username' => $u->username ?? null,
                    'image'    => $u->image ?? null,
                ];
            })->values();
        }

        return $result;
    }

    private function transformMessage(Message $msg, string $userId): ?array
    {
        if ($msg->deleted_for_everyone) {
            return [
                'id'              => (string) $msg->getKey(),
                'conversation_id' => $msg->conversation_id,
                'type'            => 'system',
                'body'            => 'This message was deleted.',
                'deleted'         => true,
                'created_at'      => $msg->created_at?->toISOString(),
            ];
        }

        $deletedFor = $msg->deleted_for ?? [];
        if (in_array($userId, $deletedFor)) {
            return null;
        }

        $msg->load('sender');
        $sender = $msg->sender;

        $replyTo = null;
        if ($msg->reply_to_id) {
            $orig = Message::find($msg->reply_to_id);
            if ($orig) {
                $origSender = $orig->sender;
                $replyTo = [
                    'id'        => (string) $orig->getKey(),
                    'body'      => $orig->deleted_for_everyone ? null : $orig->body,
                    'type'      => $orig->type,
                    'sender_id' => $orig->sender_id,
                    'sender_name' => $origSender->name ?? null,
                ];
            }
        }

        $reactions = MessageReaction::where('message_id', (string) $msg->getKey())->get();
        $groupedReactions = $reactions->groupBy('emoji')->map(function ($group, $emoji) {
            return [
                'emoji' => $emoji,
                'count' => $group->count(),
                'users' => $group->map(fn ($r) => $r->user_id)->values(),
            ];
        })->values();

        $mediaArr = $msg->media;
        if (is_array($mediaArr)) {
            $mediaArr = array_map(function ($m) {
                if (isset($m['path'])) {
                    $m['url'] = Helpers::mediaUrl($m['path']);
                }
                return $m;
            }, $mediaArr);
        }

        return [
            'id'              => (string) $msg->getKey(),
            'conversation_id' => $msg->conversation_id,
            'sender_id'       => $msg->sender_id,
            'sender'          => $sender ? [
                'id'       => (string) $sender->getKey(),
                'name'     => $sender->name,
                'username' => $sender->username,
                'image'    => $sender->image,
            ] : null,
            'type'            => $msg->type,
            'body'            => $msg->body,
            'media'           => $mediaArr,
            'reply_to'        => $replyTo,
            'forwarded'       => !empty($msg->forwarded_from_id),
            'reactions'       => $groupedReactions,
            'delivered_to'    => $msg->delivered_to ?? [],
            'read_by'         => $msg->read_by ?? [],
            'created_at'      => $msg->created_at?->toISOString(),
            'updated_at'      => $msg->updated_at?->toISOString(),
        ];
    }
}

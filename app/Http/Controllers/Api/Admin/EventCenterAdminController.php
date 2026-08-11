<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AgentEvent;
use App\Models\FlaggedUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class EventCenterAdminController extends Controller
{
    private const STATUSES = ['new', 'claimed', 'processing', 'done', 'failed', 'review'];
    private const WORKERS = ['moderator', 'feed', 'filter', 'developer', 'marketing'];
    private const TYPES = [
        'approved_feed_ready', 'reply_to_agent_comment', 'thread_update',
        'review_required', 'publish_failed',
    ];

    private const TASK_TYPE = [
        'approved_feed_ready' => 'initial_comment',
        'reply_to_agent_comment' => 'thread_reply',
        'thread_update' => 'followup_check',
        'review_required' => 'manual_review',
        'publish_failed' => 'retry_publish',
    ];

    private const GENERATED = [
        'approved_feed_ready' => 'Großartiger Beitrag! Die kurdische Kultur verdient mehr Sichtbarkeit. Wir freuen uns, diesen Content zu teilen. 🌟',
        'reply_to_agent_comment' => 'Vielen Dank für Ihr Feedback! Wir schätzen Ihre Rückmeldung und werden das berücksichtigen.',
        'thread_update' => 'Thread wurde überprüft. Alle Kommentare entsprechen den Community-Richtlinien. Keine Eskalation nötig.',
        'review_required' => 'Inhalt wurde manuell geprüft. Der Beitrag ist konform mit unseren Richtlinien und wurde freigegeben.',
        'publish_failed' => 'Retry erfolgreich – Beitrag wurde veröffentlicht. API-Verbindung wiederhergestellt.',
    ];

    private const ACTION_MAP = [
        'approved_feed_ready' => 'reply',
        'reply_to_agent_comment' => 'reply',
        'thread_update' => 'close',
        'review_required' => 'close',
        'publish_failed' => 'reply',
    ];

    private function findByKey(string $id): ?AgentEvent
    {
        $row = AgentEvent::where('event_key', $id)->first();
        if ($row) {
            return $row;
        }
        return AgentEvent::find($id);
    }

    private function pushLog(AgentEvent $row, string $action): void
    {
        $log = is_array($row->activity_log) ? $row->activity_log : [];
        $log[] = ['action' => $action, 'timestamp' => now()->toIso8601String()];
        $row->activity_log = array_slice($log, -40);
    }

    private function present(AgentEvent $row): array
    {
        $created = $row->created_at_event ?? $row->created_at;
        return [
            'id' => (string) ($row->event_key ?: $row->_id),
            'type' => $row->type,
            'worker' => $row->worker,
            'platform' => $row->platform,
            'preview' => $row->preview,
            'language' => $row->language,
            'priority' => $row->priority,
            'status' => $row->status,
            'created_at' => $created ? $created->toIso8601String() : now()->toIso8601String(),
            'thread_id' => $row->thread_id,
            'post_id' => $row->post_id,
            'original_post' => $row->original_post,
            'agent_comment' => $row->agent_comment,
            'user_reply' => $row->user_reply,
            'thread_messages' => is_array($row->thread_messages) ? $row->thread_messages : [],
            'sentiment' => $row->sentiment,
            'risk_level' => $row->risk_level,
            'payload' => is_array($row->payload) ? $row->payload : [],
            'claimed_by' => $row->claimed_by,
            'claimed_at' => $row->claimed_at ? $row->claimed_at->toIso8601String() : null,
            'result' => is_array($row->result) ? $row->result : null,
            'task' => is_array($row->task) ? $row->task : null,
            'activity_log' => is_array($row->activity_log) ? $row->activity_log : [],
            'is_duplicate' => (bool) $row->is_duplicate,
        ];
    }

    private function counters($query = null): array
    {
        $base = $query ?: AgentEvent::query();
        $status = [];
        foreach (self::STATUSES as $s) {
            $status[$s] = (clone $base)->where('status', $s)->count();
        }
        $worker = [];
        foreach (self::WORKERS as $w) {
            $worker[$w] = (clone $base)->where('worker', $w)->count();
        }
        return ['status' => $status, 'worker' => $worker, 'total' => (clone $base)->count()];
    }

    private function ensureSeeded(): void
    {
        if (AgentEvent::count() === 0) {
            Artisan::call('event-center:seed-defaults');
        }
    }

    /** GET /admin/event-center/events */
    public function index(Request $request)
    {
        $this->ensureSeeded();
        $q = AgentEvent::query()->orderBy('created_at_event', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $q->where('status', $request->status);
        }
        if ($request->filled('worker') && $request->worker !== 'all') {
            $q->where('worker', $request->worker);
        }
        if ($request->filled('type') && $request->type !== 'all') {
            $q->where('type', $request->type);
        }
        if ($request->filled('platform') && $request->platform !== 'all') {
            $q->where('platform', $request->platform);
        }
        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $q->where(function ($qq) use ($s) {
                $qq->where('event_key', 'like', "%{$s}%")
                    ->orWhere('preview', 'like', "%{$s}%")
                    ->orWhere('type', 'like', "%{$s}%");
            });
        }

        $limit = max(1, min((int) $request->get('limit', 100), 200));
        $rows = $q->limit($limit)->get()->map(fn ($r) => $this->present($r))->values();

        return ResponseHelper::sendResponse([
            'events' => $rows,
            'counters' => $this->counters(),
        ], 'Events loaded.');
    }

    /** GET /admin/event-center/events/{id} */
    public function show(string $id)
    {
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        return ResponseHelper::sendResponse($this->present($row), 'Event loaded.');
    }

    /** POST /admin/event-center/events/{id}/claim */
    public function claim(string $id)
    {
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        $worker = (string) $row->worker;
        $label = ucfirst($worker) . ' Worker';
        $row->status = 'claimed';
        $row->claimed_by = $label;
        $row->claimed_at = now();
        $this->pushLog($row, "Claimed by {$label}");
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Event claimed.');
    }

    /** POST /admin/event-center/events/{id}/process */
    public function process(string $id)
    {
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        $row->status = 'processing';
        if (!$row->claimed_by) {
            $row->claimed_by = ucfirst((string) $row->worker) . ' Worker';
            $row->claimed_at = now();
        }
        $this->pushLog($row, 'Status → processing');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Event processing.');
    }

    /** POST /admin/event-center/events/{id}/run  body: { auto_mode?: bool } */
    public function run(Request $request, string $id)
    {
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        $auto = $request->boolean('auto_mode');
        $type = (string) $row->type;
        $label = ucfirst((string) $row->worker) . ' Worker';

        $row->status = $auto ? 'done' : 'processing';
        $row->claimed_by = $label;
        $row->claimed_at = $row->claimed_at ?: now();
        $suffix = Str::after($row->event_key ?: $id, '-');
        $row->task = [
            'task_id' => 'TSK-' . ($suffix ?: Str::random(4)),
            'task_type' => self::TASK_TYPE[$type] ?? 'manual_review',
            'task_status' => $auto ? 'completed' : 'in_progress',
            'created_at' => now()->toIso8601String(),
        ];
        $row->result = [
            'generated_text' => self::GENERATED[$type] ?? 'Action completed.',
            'action' => self::ACTION_MAP[$type] ?? 'close',
            'is_draft' => !$auto,
            ...($auto ? ['completed_at' => now()->toIso8601String()] : []),
        ];
        $this->pushLog($row, $auto ? 'Auto Mode run completed' : 'Agent run — draft generated');
        if ($auto) {
            $this->pushLog($row, 'Status → done');
        }
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Agent run finished.');
    }

    /** POST /admin/event-center/events/{id}/reply  body: { text?: string } */
    public function reply(Request $request, string $id)
    {
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        $text = trim((string) $request->input('text', ''));
        $prev = is_array($row->result) ? $row->result : [];
        if ($text === '') {
            $text = (string) ($prev['generated_text'] ?? '');
        }
        $row->status = 'done';
        $row->result = array_merge($prev, [
            'generated_text' => $text,
            'action' => $prev['action'] ?? (self::ACTION_MAP[$row->type] ?? 'reply'),
            'completed_at' => now()->toIso8601String(),
            'is_draft' => false,
        ]);
        if (is_array($row->task)) {
            $task = $row->task;
            $task['task_status'] = 'completed';
            $row->task = $task;
        }
        $this->pushLog($row, 'Reply published');
        $this->pushLog($row, 'Status → done');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Reply published.');
    }

    /** POST /admin/event-center/events/{id}/status  body: { status } */
    public function updateStatus(Request $request, string $id)
    {
        $status = (string) $request->input('status', '');
        if (!in_array($status, self::STATUSES, true)) {
            return ResponseHelper::sendResponse(null, 'Invalid status', false, 422);
        }
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        $row->status = $status;
        $this->pushLog($row, "Status → {$status}");
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Status updated.');
    }

    /** DELETE /admin/event-center/events/{id} */
    public function destroy(string $id)
    {
        $row = $this->findByKey($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Event not found', false, 404);
        }
        $row->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Event deleted.');
    }

    /** POST /admin/event-center/seed */
    public function seed(Request $request)
    {
        $params = $request->boolean('force') ? ['--force' => true] : [];
        Artisan::call('event-center:seed-defaults', $params);
        return ResponseHelper::sendResponse([
            'total' => AgentEvent::count(),
            'output' => Artisan::output(),
        ], 'Event Center seeded.');
    }

    /**
     * GET /admin/event-center/queues/{agent}
     * Aggregated counters for workspace tabs (domain-backed where possible).
     */
    public function queue(string $agent)
    {
        $agent = strtolower($agent);
        $map = [
            'complaint' => 'complaint',
            'user-clips' => 'user-clips',
            'feed-filter' => 'feed-filter',
            'zercash' => 'zercash',
            'flagged-users' => 'flagged-users',
            'flagged-comments' => 'flagged-comments',
        ];
        if (!isset($map[$agent])) {
            return ResponseHelper::sendResponse(null, 'Unknown agent', false, 404);
        }

        // Event-queue slice by worker heuristic + domain overview counts
        $workerHint = match ($agent) {
            'complaint', 'flagged-users', 'flagged-comments' => 'moderator',
            'user-clips', 'feed-filter' => 'filter',
            'zercash' => 'developer',
            default => null,
        };

        $eq = AgentEvent::query();
        if ($workerHint) {
            $eq->where('worker', $workerHint);
        }
        $byStatus = [];
        foreach (self::STATUSES as $s) {
            $byStatus[$s] = (clone $eq)->where('status', $s)->count();
        }

        $overview = 0;
        try {
            if ($agent === 'flagged-users') {
                $overview = (int) FlaggedUser::count();
            } elseif ($agent === 'complaint') {
                // Complaints collection — schemaless status filter
                $overview = (int) \App\Models\Complaint::where('status', 'pending')->count();
            } else {
                $overview = $byStatus['new'] + $byStatus['claimed'] + $byStatus['processing'] + $byStatus['review'];
            }
        } catch (\Throwable $e) {
            $overview = $byStatus['new'] + $byStatus['review'];
        }

        return ResponseHelper::sendResponse([
            'agent' => $agent,
            'counters' => [
                'overview' => $overview,
                'new' => $byStatus['new'],
                'processing' => $byStatus['processing'] + $byStatus['claimed'],
                'review' => $byStatus['review'],
                'failed' => $byStatus['failed'],
                'completed' => $byStatus['done'],
            ],
            'events' => (clone $eq)->orderBy('created_at_event', 'desc')->limit(50)
                ->get()->map(fn ($r) => $this->present($r))->values(),
        ], 'Queue loaded.');
    }
}

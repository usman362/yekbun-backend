<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoconetState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class LoconetAdminController extends Controller
{
    private function snapshot(): LoconetState
    {
        $row = LoconetState::orderBy('updated_at', 'desc')->first();
        if ($row) {
            return $row;
        }

        Artisan::call('loconet:seed-defaults');
        $row = LoconetState::orderBy('updated_at', 'desc')->first();
        if ($row) {
            return $row;
        }

        return LoconetState::create(['project_id' => 'yekbun-prod-01']);
    }

    private function present(LoconetState $row): array
    {
        $data = $row->toArray();
        $id = (string) ($row->_id ?? $row->id ?? '');
        $data['id'] = $id;
        $data['project_id'] = $row->project_id ?? ($data['project_id'] ?? 'yekbun-prod-01');
        return $data;
    }

    private function actor(): string
    {
        return optional(auth()->user())->name
            ?? optional(auth()->user())->username
            ?? 'Admin';
    }

    private function pushActivity(LoconetState $row, string $text, string $tone = 'blue'): void
    {
        $activity = is_array($row->activity) ? $row->activity : [];
        array_unshift($activity, ['text' => $text, 'time' => 'Just now', 'tone' => $tone]);
        $row->activity = array_slice($activity, 0, 20);
    }

    private function updateList(LoconetState $row, string $field, string $id, callable $mutator): bool
    {
        $list = is_array($row->{$field}) ? $row->{$field} : [];
        $found = false;
        foreach ($list as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemId = (string) ($item['id'] ?? $item['number'] ?? '');
            if ($itemId === $id) {
                $list[$i] = $mutator($item);
                $found = true;
                break;
            }
        }
        if ($found) {
            $row->{$field} = $list;
        }
        return $found;
    }

    /** GET /admin/loconet */
    public function show()
    {
        return ResponseHelper::sendResponse($this->present($this->snapshot()), 'LoCoNet snapshot loaded.');
    }

    /** POST /admin/loconet/seed */
    public function seed(Request $request)
    {
        $force = $request->boolean('force');
        Artisan::call('loconet:seed-defaults', $force ? ['--force' => true] : []);
        return ResponseHelper::sendResponse($this->present($this->snapshot()), 'LoCoNet seed complete.');
    }

    /** PUT /admin/loconet/settings */
    public function updateSettings(Request $request)
    {
        $row = $this->snapshot();
        $current = is_array($row->settings) ? $row->settings : [];
        $patch = $request->input('settings', $request->all());
        if (!is_array($patch)) {
            return ResponseHelper::sendResponse(null, 'settings must be an object', false, 422);
        }
        $row->settings = array_replace_recursive($current, $patch);
        $this->pushActivity($row, 'Communication settings updated', 'green');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Settings saved.');
    }

    /** PUT /admin/loconet/integration */
    public function updateIntegration(Request $request)
    {
        $row = $this->snapshot();
        $current = is_array($row->integration) ? $row->integration : [];
        $patch = $request->input('integration', $request->all());
        if (!is_array($patch)) {
            return ResponseHelper::sendResponse(null, 'integration must be an object', false, 422);
        }
        $row->integration = array_replace_recursive($current, $patch);
        $this->pushActivity($row, 'LoCoNet integration updated', 'blue');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Integration saved.');
    }

    /** POST /admin/loconet/test-connection */
    public function testConnection()
    {
        $row = $this->snapshot();
        $integration = is_array($row->integration) ? $row->integration : [];
        $integration['connected'] = true;
        $integration['last_test_at'] = now()->toIso8601String();
        $row->integration = $integration;

        $logs = is_array($row->integrationLogs) ? $row->integrationLogs : [];
        array_unshift($logs, [
            'time' => now()->format('H:i:s'),
            'level' => 'info',
            'text' => 'POST /v1/projects/' . ($row->project_id ?? 'yekbun-prod-01') . '/ping · 200 · 42 ms',
        ]);
        $row->integrationLogs = array_slice($logs, 0, 40);
        $this->pushActivity($row, 'LoCoNet connection test succeeded', 'green');
        $row->save();

        return ResponseHelper::sendResponse([
            'ok' => true,
            'latency_ms' => 42,
            'snapshot' => $this->present($row),
        ], 'Connection OK.');
    }

    /** POST /admin/loconet/chats/{id}/status */
    public function chatStatus(Request $request, string $id)
    {
        $status = $request->input('status');
        $allowed = ['Active', 'Archived', 'Blocked', 'Reported'];
        if (!in_array($status, $allowed, true)) {
            return ResponseHelper::sendResponse(null, 'Invalid status', false, 422);
        }
        $row = $this->snapshot();
        $ok = $this->updateList($row, 'chats', $id, function ($item) use ($status) {
            $item['status'] = $status;
            return $item;
        });
        if (!$ok) {
            return ResponseHelper::sendResponse(null, 'Chat not found', false, 404);
        }
        $this->pushActivity($row, "Chat {$id} marked {$status}", $status === 'Blocked' ? 'red' : 'green');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Chat updated.');
    }

    /** POST /admin/loconet/chats/{id}/audit */
    public function chatAudit(Request $request, string $id)
    {
        $reason = trim((string) $request->input('reason', 'User report'));
        $row = $this->snapshot();
        $audit = is_array($row->auditAccess) ? $row->auditAccess : [];
        array_unshift($audit, [
            'admin' => $this->actor(),
            'reason' => $reason . " · {$id}",
            'time' => 'Just now',
        ]);
        $row->auditAccess = array_slice($audit, 0, 50);
        $this->pushActivity($row, "Audit access granted for {$id}", 'amber');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Audit log entry created.');
    }

    /** POST /admin/loconet/reports/{id}/action */
    public function reportAction(Request $request, string $id)
    {
        $action = (string) $request->input('action', 'Review');
        $statusMap = [
            'Review' => 'Pending Review',
            'Approve Report' => 'Resolved',
            'Reject Report' => 'Resolved',
            'Escalate' => 'Escalated',
            'Warn User' => 'Pending Review',
            'Mute User' => 'Pending Review',
            'Suspend User' => 'Escalated',
            'Block User' => 'Escalated',
            'Delete Message' => 'Resolved',
        ];
        $next = $statusMap[$action] ?? 'Pending Review';
        $row = $this->snapshot();
        $ok = $this->updateList($row, 'reports', $id, function ($item) use ($next) {
            $item['status'] = $next;
            $item['moderator'] = $this->actor();
            return $item;
        });
        if (!$ok) {
            return ResponseHelper::sendResponse(null, 'Report not found', false, 404);
        }
        $this->pushActivity($row, "Report {$id}: {$action}", $next === 'Escalated' ? 'red' : 'green');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Report updated.');
    }

    /** POST /admin/loconet/streams/{id}/action */
    public function streamAction(Request $request, string $id)
    {
        $action = (string) $request->input('action', 'Open');
        $row = $this->snapshot();
        $ok = $this->updateList($row, 'streams', $id, function ($item) use ($action) {
            if ($action === 'Stop') {
                $item['group'] = 'ended';
                $item['status'] = 'Ended';
                $item['viewers'] = 0;
            } elseif ($action === 'Suspend') {
                $item['group'] = 'reported';
                $item['status'] = 'Under review';
            } elseif ($action === 'Warn') {
                $item['reports'] = (int) ($item['reports'] ?? 0) + 1;
                if (($item['group'] ?? '') === 'live') {
                    $item['status'] = 'Live · Reported';
                }
            }
            return $item;
        });
        if (!$ok) {
            return ResponseHelper::sendResponse(null, 'Stream not found', false, 404);
        }
        $this->pushActivity($row, "Stream {$id}: {$action}", $action === 'Stop' ? 'amber' : 'violet');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Stream updated.');
    }

    /** POST /admin/loconet/scheduled/{id}/action */
    public function scheduledAction(Request $request, string $id)
    {
        $action = (string) $request->input('action', 'Approve');
        $row = $this->snapshot();
        $ok = $this->updateList($row, 'scheduled', $id, function ($item) use ($action) {
            if ($action === 'Approve') {
                $item['approval'] = 'Approved';
            } elseif ($action === 'Reject' || $action === 'Cancel') {
                $item['approval'] = 'Rejected';
                $item['reserved'] = 0;
            } elseif ($action === 'Notify Followers') {
                $item['reminder'] = 'Sent';
            } elseif ($action === 'Reserve Minutes') {
                $item['reserved'] = max(50, (int) ($item['reserved'] ?? 0));
                $item['reminder'] = $item['reminder'] === 'Not set' ? 'Scheduled' : $item['reminder'];
            } elseif ($action === 'Release Reserved Minutes') {
                $item['reserved'] = 0;
            }
            return $item;
        });
        if (!$ok) {
            return ResponseHelper::sendResponse(null, 'Scheduled stream not found', false, 404);
        }
        $this->pushActivity($row, "Scheduled {$id}: {$action}", 'blue');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Scheduled stream updated.');
    }

    /** POST /admin/loconet/streamers/{id}/action */
    public function streamerAction(Request $request, string $id)
    {
        $action = (string) $request->input('action', 'toggle-stream');
        $streamDelta = (int) $request->input('stream_minutes', 0);
        $callDelta = (int) $request->input('call_minutes', 0);
        $row = $this->snapshot();
        $ok = $this->updateList($row, 'streamers', $id, function ($item) use ($action, $streamDelta, $callDelta) {
            if ($action === 'toggle-stream') {
                $item['streamingPermission'] = empty($item['streamingPermission']) ? true : false;
            } elseif ($action === 'toggle-call') {
                $item['callPermission'] = empty($item['callPermission']) ? true : false;
            } elseif ($action === 'suspend') {
                $item['status'] = 'Suspended';
                $item['streamingPermission'] = false;
            } elseif ($action === 'unsuspend') {
                $item['status'] = 'Active';
            } elseif ($action === 'allocate') {
                $item['streamAssigned'] = max(0, (int) ($item['streamAssigned'] ?? 0) + $streamDelta);
                $item['callAssigned'] = max(0, (int) ($item['callAssigned'] ?? 0) + $callDelta);
            }
            $assigned = max(1, (int) ($item['streamAssigned'] ?? 1));
            $used = (int) ($item['streamUsed'] ?? 0);
            if (($item['status'] ?? '') !== 'Suspended') {
                if ($used >= $assigned) {
                    $item['status'] = 'Out of Minutes';
                } elseif ($used / $assigned >= 0.8) {
                    $item['status'] = 'Near Limit';
                } else {
                    $item['status'] = 'Active';
                }
            }
            return $item;
        });
        if (!$ok) {
            return ResponseHelper::sendResponse(null, 'Streamer not found', false, 404);
        }
        $this->pushActivity($row, "Streamer {$id}: {$action}", 'violet');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Streamer updated.');
    }

    /** POST /admin/loconet/purchases */
    public function createPurchase(Request $request)
    {
        $row = $this->snapshot();
        $pkgId = (string) $request->input('pkg_id', '');
        $qty = max(1, (int) $request->input('quantity', 1));
        $packages = is_array($row->packages) ? $row->packages : [];
        $pkg = collect($packages)->firstWhere('id', $pkgId);
        if (!$pkg) {
            return ResponseHelper::sendResponse(null, 'Package not found', false, 404);
        }

        $price = ((float) ($pkg['price'] ?? 0) * $qty) * (1 + (float) ($pkg['taxRate'] ?? 0));
        $stream = ((int) ($pkg['streamMinutes'] ?? 0)) * $qty;
        $call = ((int) ($pkg['callMinutes'] ?? 0)) * $qty;
        $requests = is_array($row->purchaseRequests) ? $row->purchaseRequests : [];
        $id = 'PR-' . (2040 + count($requests) + 1);
        array_unshift($requests, [
            'id' => $id,
            'pkg' => $pkg['name'] ?? $pkgId,
            'quantity' => $qty,
            'totalMinutes' => number_format($stream) . ' stream · ' . number_format($call) . ' call',
            'price' => round($price, 1),
            'createdBy' => $this->actor(),
            'created' => now()->toDateString(),
            'status' => 'Submitted',
            'invoice' => '—',
        ]);
        $row->purchaseRequests = $requests;
        $this->pushActivity($row, "Purchase request {$id} submitted", 'blue');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Purchase request created.');
    }

    /** POST /admin/loconet/purchases/{id}/status */
    public function purchaseStatus(Request $request, string $id)
    {
        $status = (string) $request->input('status');
        $allowed = ['Draft', 'Submitted', 'Under Review', 'Approved', 'Payment Pending', 'Paid', 'Minutes Credited', 'Rejected', 'Cancelled'];
        if (!in_array($status, $allowed, true)) {
            return ResponseHelper::sendResponse(null, 'Invalid status', false, 422);
        }
        $row = $this->snapshot();
        $ok = $this->updateList($row, 'purchaseRequests', $id, function ($item) use ($status) {
            $item['status'] = $status;
            return $item;
        });
        if (!$ok) {
            return ResponseHelper::sendResponse(null, 'Purchase request not found', false, 404);
        }
        $this->pushActivity($row, "Purchase {$id} → {$status}", 'green');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Purchase updated.');
    }
}

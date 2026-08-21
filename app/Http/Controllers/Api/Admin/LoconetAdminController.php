<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoconetState;
use App\Services\LoconetClient;
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

        $integration = is_array($data['integration'] ?? null) ? $data['integration'] : [];
        $client = app(LoconetClient::class);
        $resolved = $client->resolve($integration);

        // Fill default endpoint URLs for the admin UI when not yet saved.
        $endpoints = is_array($integration['endpoints'] ?? null) ? $integration['endpoints'] : [];
        foreach ($resolved['urls'] as $key => $defaultUrl) {
            $existing = $endpoints[$key] ?? null;
            if (!is_array($existing)) {
                $endpoints[$key] = [
                    'url' => $defaultUrl,
                    'status' => 'grey',
                    'checked' => '—',
                    'latency' => '—',
                ];
                continue;
            }
            if (trim((string) ($existing['url'] ?? '')) === '') {
                $endpoints[$key]['url'] = $defaultUrl;
            }
        }
        $integration['endpoints'] = $endpoints;

        $cert = (string) ($integration['primaryCert'] ?? $integration['apiKey'] ?? '');
        $envCert = (string) config('services.loconet.certificate', '');
        $hasCert = ($cert !== '' && !str_starts_with($cert, '•')) || $envCert !== '';
        $integration['has_certificate'] = $hasCert;
        // Never send the raw certificate to the browser.
        $integration['primaryCert'] = $hasCert ? '••••••••••••••••' : '';
        unset($integration['apiKey'], $integration['apiSecret']);

        if (empty($integration['projectId'])) {
            $integration['projectId'] = $resolved['projectId'];
        }
        if (empty($integration['projectSlug'])) {
            $integration['projectSlug'] = $resolved['projectSlug'];
        }
        if (empty($integration['appId'])) {
            $integration['appId'] = $resolved['appId'];
        }
        if (empty($integration['apiBase'])) {
            $integration['apiBase'] = $resolved['apiBase'];
        }
        if (empty($integration['webhookUrl'])) {
            $integration['webhookUrl'] = $resolved['urls']['webhook'];
        }

        $data['integration'] = $integration;
        $data['project_id'] = $integration['projectId'] ?? $data['project_id'];

        return $data;
    }

    private function toneFromStatus(string $status): string
    {
        return match ($status) {
            'operational' => 'green',
            'degraded' => 'amber',
            default => 'red',
        };
    }

    private function applyProbeToEndpoint(array $endpoints, string $key, array $probe): array
    {
        if (!isset($endpoints[$key]) || !is_array($endpoints[$key])) {
            $endpoints[$key] = ['url' => ''];
        }
        $endpoints[$key]['status'] = $this->toneFromStatus($probe['status'] ?? 'offline');
        $endpoints[$key]['checked'] = now()->format('H:i:s');
        $endpoints[$key]['latency'] = ((int) ($probe['latency_ms'] ?? 0)) . ' ms';
        $endpoints[$key]['last_code'] = $probe['http_code'] ?? null;
        $endpoints[$key]['last_message'] = $probe['message'] ?? '';
        return $endpoints;
    }

    private function syncServiceHealth(LoconetState $row, array $results): void
    {
        $map = [
            'REST API' => 'rest',
            'Socket.IO' => 'socket',
            'WebRTC / LiveKit' => 'webrtc',
            'Push Notifications' => 'webhook',
        ];
        // Prefer health for API if present
        if (isset($results['health'])) {
            $map['REST API'] = 'health';
        }

        $services = is_array($row->services) ? $row->services : [];
        foreach ($services as $i => $svc) {
            if (!is_array($svc)) {
                continue;
            }
            $name = (string) ($svc['name'] ?? '');
            $key = $map[$name] ?? null;
            if (!$key || !isset($results[$key])) {
                continue;
            }
            $probe = $results[$key];
            $services[$i]['status'] = $probe['status'] ?? 'offline';
            $services[$i]['latency'] = ((int) ($probe['latency_ms'] ?? 0)) . ' ms';
        }
        $row->services = $services;
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

        // Keep existing secret when UI sends masked / empty certificate.
        $incomingCert = (string) ($patch['primaryCert'] ?? '');
        if ($incomingCert === '' || str_starts_with($incomingCert, '•')) {
            unset($patch['primaryCert']);
        }

        $merged = array_replace_recursive($current, $patch);

        // Normalize camelCase keys used by the dashboard UI.
        if (isset($patch['projectId']) && is_string($patch['projectId']) && $patch['projectId'] !== '') {
            $merged['projectId'] = $patch['projectId'];
            $row->project_id = $patch['projectId'];
        }
        if (isset($patch['projectSlug']) && is_string($patch['projectSlug']) && $patch['projectSlug'] !== '') {
            $merged['projectSlug'] = $patch['projectSlug'];
        }
        if (isset($patch['appId']) && is_string($patch['appId'])) {
            $merged['appId'] = $patch['appId'];
        }
        if (array_key_exists('enabled', $patch)) {
            $merged['enabled'] = (bool) $patch['enabled'];
            $merged['connected'] = (bool) $patch['enabled'];
        }

        // Persist endpoint URLs from UI shape { rest: { url, status... } }.
        if (isset($patch['endpoints']) && is_array($patch['endpoints'])) {
            $eps = is_array($merged['endpoints'] ?? null) ? $merged['endpoints'] : [];
            foreach ($patch['endpoints'] as $key => $ep) {
                if (is_string($ep)) {
                    $eps[$key] = array_merge(is_array($eps[$key] ?? null) ? $eps[$key] : [], ['url' => $ep]);
                } elseif (is_array($ep)) {
                    $eps[$key] = array_replace(is_array($eps[$key] ?? null) ? $eps[$key] : [], $ep);
                }
            }
            $merged['endpoints'] = $eps;
        }

        $row->integration = $merged;
        $this->pushActivity($row, 'LoCoNet integration updated', 'blue');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Integration saved.');
    }

    /** POST /admin/loconet/test-connection */
    public function testConnection(LoconetClient $client)
    {
        $row = $this->snapshot();
        $integration = is_array($row->integration) ? $row->integration : [];
        $probe = $client->probeAll($integration);

        $endpoints = is_array($integration['endpoints'] ?? null) ? $integration['endpoints'] : [];
        foreach ($probe['results'] as $key => $result) {
            $endpoints = $this->applyProbeToEndpoint($endpoints, $key, $result);
        }
        $integration['endpoints'] = $endpoints;
        $integration['connected'] = (bool) $probe['ok'];
        $integration['enabled'] = (bool) ($integration['enabled'] ?? $probe['ok']);
        $integration['last_test_at'] = now()->toIso8601String();
        $integration['last_test_ok'] = (bool) $probe['ok'];
        $integration['last_test_message'] = $probe['message'];
        $row->integration = $integration;

        $this->syncServiceHealth($row, $probe['results']);

        $project = $probe['config']['projectId'] ?? ($row->project_id ?? 'yekbun-prod-01');
        $level = $probe['ok'] ? 'info' : 'error';
        $logs = is_array($row->integrationLogs) ? $row->integrationLogs : [];
        array_unshift($logs, [
            'time' => now()->format('H:i:s'),
            'level' => $level,
            'text' => sprintf(
                'PROBE /projects/%s · %s · %d ms',
                $project,
                $probe['ok'] ? 'OK' : 'FAIL',
                (int) $probe['latency_ms']
            ),
        ]);
        $row->integrationLogs = array_slice($logs, 0, 40);
        $this->pushActivity(
            $row,
            $probe['ok'] ? 'LoCoNet connection test succeeded' : 'LoCoNet connection test failed',
            $probe['ok'] ? 'green' : 'red'
        );
        $row->save();

        return ResponseHelper::sendResponse([
            'ok' => (bool) $probe['ok'],
            'latency_ms' => (int) $probe['latency_ms'],
            'message' => $probe['message'],
            'results' => $probe['results'],
            'snapshot' => $this->present($row),
        ], $probe['ok'] ? 'Connection OK.' : 'Connection failed.', $probe['ok']);
    }

    /** POST /admin/loconet/test-endpoint */
    public function testEndpoint(Request $request, LoconetClient $client)
    {
        $key = (string) $request->input('key', '');
        $allowed = ['rest', 'socket', 'token', 'webhook', 'media', 'webrtc', 'health'];
        if (!in_array($key, $allowed, true)) {
            return ResponseHelper::sendResponse(null, 'Invalid endpoint key', false, 422);
        }

        $row = $this->snapshot();
        $integration = is_array($row->integration) ? $row->integration : [];
        $cfg = $client->resolve($integration);

        $overrideUrl = trim((string) $request->input('url', ''));
        $url = $overrideUrl !== '' ? $overrideUrl : ($cfg['urls'][$key] ?? '');
        $probe = $client->probe($url, $cfg['certificate'], $cfg['appId']);

        $endpoints = is_array($integration['endpoints'] ?? null) ? $integration['endpoints'] : [];
        if ($overrideUrl !== '') {
            if (!isset($endpoints[$key]) || !is_array($endpoints[$key])) {
                $endpoints[$key] = [];
            }
            $endpoints[$key]['url'] = $overrideUrl;
        }
        $endpoints = $this->applyProbeToEndpoint($endpoints, $key, $probe);
        $integration['endpoints'] = $endpoints;
        $row->integration = $integration;

        $this->syncServiceHealth($row, [$key => $probe]);

        $logs = is_array($row->integrationLogs) ? $row->integrationLogs : [];
        array_unshift($logs, [
            'time' => now()->format('H:i:s'),
            'level' => $probe['ok'] ? 'info' : 'error',
            'text' => sprintf(
                'TEST %s · %s · %d ms · %s',
                $key,
                $probe['ok'] ? 'OK' : 'FAIL',
                (int) $probe['latency_ms'],
                $probe['message'] ?? ''
            ),
        ]);
        $row->integrationLogs = array_slice($logs, 0, 40);
        $row->save();

        return ResponseHelper::sendResponse([
            'ok' => (bool) $probe['ok'],
            'key' => $key,
            'latency_ms' => (int) $probe['latency_ms'],
            'status' => $probe['status'],
            'message' => $probe['message'],
            'http_code' => $probe['http_code'],
            'snapshot' => $this->present($row),
        ], $probe['ok'] ? 'Endpoint OK.' : 'Endpoint failed.', $probe['ok']);
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

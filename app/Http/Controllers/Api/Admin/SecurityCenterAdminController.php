<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SecurityCenterState;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityCenterAdminController extends Controller
{
    private function adminId(): string
    {
        $user = auth()->user();
        return (string) ($user->_id ?? $user->id ?? 'unknown');
    }

    private function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || !str_contains($email, '@')) {
            return 'm****an@yekbun.app';
        }
        [$local, $domain] = explode('@', $email, 2);
        $keep = mb_substr($local, 0, 1);
        return $keep . '****' . mb_substr($local, -2) . '@' . $domain;
    }

    private function maskPhone(?string $phone): string
    {
        $phone = preg_replace('/\s+/', ' ', trim((string) $phone)) ?: '+49 ••• ••• 42';
        if (mb_strlen($phone) < 6) {
            return $phone;
        }
        return mb_substr($phone, 0, 4) . ' ••• ••• ' . mb_substr($phone, -2);
    }

    private function randomSecret(): string
    {
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $parts[] = strtoupper(Str::random(4));
        }
        return implode(' ', $parts);
    }

    private function randomBackupKey(): string
    {
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $parts[] = strtoupper(Str::random(5));
        }
        return implode('-', $parts);
    }

    private function randomCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    private function defaultState(): array
    {
        $user = auth()->user();
        $email = $this->maskEmail($user->email ?? null);
        $phone = $this->maskPhone($user->phone ?? $user->mobile ?? null);
        $ua = request()->userAgent() ?: 'Chrome';
        $browser = str_contains($ua, 'Edg') ? 'Edge' : (str_contains($ua, 'Firefox') ? 'Firefox' : (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome') ? 'Safari' : 'Chrome'));

        return [
            'admin_id' => $this->adminId(),
            'passkey' => false,
            'passkey_registered' => false,
            'authenticator' => false,
            'email_verify' => true,
            'sms_verify' => false,
            'totp_secret' => $this->randomSecret(),
            'backup_key' => $this->randomBackupKey(),
            'recovery_codes' => [],
            'codes_generated' => false,
            'recovery_email' => $email,
            'recovery_phone' => $phone,
            'devices' => [],
            'sessions' => [[
                'id' => 's-current',
                'device' => 'This device · ' . $browser,
                'country' => 'Germany',
                'city' => 'Berlin',
                'loginTime' => now()->format('H:i'),
                'duration' => '0m',
                'current' => true,
            ]],
            'history' => [[
                'id' => 'h-' . Str::random(6),
                'time' => 'Just now',
                'type' => 'Dashboard Login',
                'device' => 'This device',
                'os' => PHP_OS_FAMILY,
                'city' => 'Berlin',
                'country' => 'Germany',
                'ip' => request()->ip() ?: '127.0.0.1',
                'browser' => $browser,
                'method' => 'Password',
                'ok' => true,
            ]],
            'alerts' => [[
                'level' => 'info',
                'title' => 'Security center initialized',
                'desc' => 'Methods, devices and sessions are stored for this admin account.',
                'time' => 'Just now',
            ]],
        ];
    }

    private function snapshot(): SecurityCenterState
    {
        $adminId = $this->adminId();
        $row = SecurityCenterState::where('admin_id', $adminId)->first();
        if ($row) {
            return $row;
        }
        return SecurityCenterState::create($this->defaultState());
    }

    private function present(SecurityCenterState $row, bool $secrets = false): array
    {
        $data = [
            'id' => (string) ($row->_id ?? $row->id ?? ''),
            'passkey' => (bool) $row->passkey,
            'passkey_registered' => (bool) $row->passkey_registered,
            'authenticator' => (bool) $row->authenticator,
            'email_verify' => (bool) $row->email_verify,
            'sms_verify' => (bool) $row->sms_verify,
            'codes_generated' => (bool) $row->codes_generated,
            'recovery_email' => $row->recovery_email,
            'recovery_phone' => $row->recovery_phone,
            'devices' => is_array($row->devices) ? $row->devices : [],
            'sessions' => is_array($row->sessions) ? $row->sessions : [],
            'history' => is_array($row->history) ? $row->history : [],
            'alerts' => is_array($row->alerts) ? $row->alerts : [],
        ];

        $enabled = (int) $data['passkey'] + (int) $data['authenticator'] + (int) $data['email_verify'];
        $score = min(100, $enabled * 30 + ($data['sms_verify'] ? 5 : 0) + ($data['codes_generated'] ? 5 : 0));
        $data['score'] = $score;
        $data['auth_strength'] = $enabled >= 3 ? 'Strong' : ($enabled === 2 ? 'Good' : ($enabled === 1 ? 'Basic' : 'Weak'));
        $data['checks'] = [
            ['label' => 'Password Protected', 'ok' => true],
            ['label' => 'Passkey Enabled', 'ok' => $data['passkey']],
            ['label' => 'Authenticator Enabled', 'ok' => $data['authenticator']],
            ['label' => 'Recovery Codes Generated', 'ok' => $data['codes_generated']],
        ];

        if ($secrets) {
            $data['totp_secret'] = $row->totp_secret;
            $data['backup_key'] = $row->backup_key;
            $data['recovery_codes'] = is_array($row->recovery_codes) ? $row->recovery_codes : [];
        } else {
            $data['totp_secret_masked'] = $this->maskSecret((string) $row->totp_secret);
            $data['has_backup_key'] = !empty($row->backup_key);
        }

        return $data;
    }

    private function maskSecret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }
        return mb_substr($secret, 0, 4) . ' •••• ' . mb_substr($secret, -4);
    }

    private function pushAlert(SecurityCenterState $row, string $title, string $desc, string $level = 'info'): void
    {
        $alerts = is_array($row->alerts) ? $row->alerts : [];
        array_unshift($alerts, compact('level', 'title', 'desc') + ['time' => 'Just now']);
        $row->alerts = array_slice($alerts, 0, 12);
    }

    private function pushHistory(SecurityCenterState $row, array $event): void
    {
        $history = is_array($row->history) ? $row->history : [];
        array_unshift($history, array_merge([
            'id' => 'h-' . Str::random(6),
            'time' => 'Just now',
            'device' => 'This device',
            'os' => PHP_OS_FAMILY,
            'city' => 'Berlin',
            'country' => 'Germany',
            'ip' => request()->ip() ?: '127.0.0.1',
            'browser' => 'Chrome',
            'method' => 'Dashboard',
            'ok' => true,
        ], $event));
        $row->history = array_slice($history, 0, 40);
    }

    /** GET /admin/security-center */
    public function show()
    {
        return ResponseHelper::sendResponse($this->present($this->snapshot()), 'Security center loaded.');
    }

    /** PUT /admin/security-center/methods */
    public function updateMethods(Request $request)
    {
        $row = $this->snapshot();
        foreach (['passkey', 'authenticator', 'email_verify', 'sms_verify'] as $key) {
            if ($request->has($key)) {
                $row->{$key} = $request->boolean($key);
            }
        }
        if ($row->passkey && !$row->passkey_registered) {
            $row->passkey = false;
        }
        $this->pushAlert($row, 'Authentication method changed', 'Security methods updated');
        $this->pushHistory($row, ['type' => 'Authentication Updated', 'method' => 'Security Center']);
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Methods updated.');
    }

    /** POST /admin/security-center/devices */
    public function addDevice(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:80',
            'os' => 'nullable|string|max:80',
            'browser' => 'nullable|string|max:80',
            'type' => 'nullable|string|max:40',
        ]);
        $row = $this->snapshot();
        $devices = is_array($row->devices) ? $row->devices : [];
        $device = [
            'id' => 'd-' . Str::random(8),
            'name' => $request->input('name'),
            'os' => $request->input('os', 'Unknown'),
            'browser' => $request->input('browser', 'Unknown'),
            'type' => $request->input('type', 'Desktop'),
            'created' => now()->format('d M Y'),
            'lastUsed' => 'Just now',
            'current' => empty($devices),
        ];
        if (!empty($devices) && $request->boolean('current', true)) {
            $devices = array_map(function ($d) {
                $d['current'] = false;
                return $d;
            }, $devices);
            $device['current'] = true;
        }
        array_unshift($devices, $device);
        $row->devices = $devices;
        $row->passkey_registered = true;
        $row->passkey = true;
        $this->pushAlert($row, 'New device registered', $device['name'] . ' added to trusted devices');
        $this->pushHistory($row, ['type' => 'Passkey Registered', 'device' => $device['name'], 'os' => $device['os'], 'browser' => $device['browser']]);
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Device added.');
    }

    /** PUT /admin/security-center/devices/{id} */
    public function renameDevice(Request $request, string $id)
    {
        $request->validate(['name' => 'required|string|max:80']);
        $row = $this->snapshot();
        $devices = is_array($row->devices) ? $row->devices : [];
        $found = false;
        foreach ($devices as $i => $d) {
            if (($d['id'] ?? '') === $id) {
                $devices[$i]['name'] = $request->input('name');
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ResponseHelper::sendResponse(null, 'Device not found', false, 404);
        }
        $row->devices = $devices;
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Device renamed.');
    }

    /** DELETE /admin/security-center/devices/{id} */
    public function removeDevice(string $id)
    {
        $row = $this->snapshot();
        $devices = is_array($row->devices) ? $row->devices : [];
        $removed = null;
        $devices = array_values(array_filter($devices, function ($d) use ($id, &$removed) {
            if (($d['id'] ?? '') === $id) {
                $removed = $d;
                return false;
            }
            return true;
        }));
        if (!$removed) {
            return ResponseHelper::sendResponse(null, 'Device not found', false, 404);
        }
        if (!empty($devices) && empty(array_filter($devices, fn ($d) => !empty($d['current'])))) {
            $devices[0]['current'] = true;
        }
        $row->devices = $devices;
        if (count($devices) === 0) {
            $row->passkey = false;
            $row->passkey_registered = false;
        }
        $this->pushAlert($row, 'Device removed', ($removed['name'] ?? 'Device') . ' is no longer trusted', 'warn');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Device removed.');
    }

    /** POST /admin/security-center/sessions/{id}/terminate */
    public function terminateSession(string $id)
    {
        $row = $this->snapshot();
        $sessions = is_array($row->sessions) ? $row->sessions : [];
        $target = collect($sessions)->firstWhere('id', $id);
        if (!$target) {
            return ResponseHelper::sendResponse(null, 'Session not found', false, 404);
        }
        if (!empty($target['current'])) {
            return ResponseHelper::sendResponse(null, 'Cannot terminate the current session', false, 422);
        }
        $row->sessions = array_values(array_filter($sessions, fn ($s) => ($s['id'] ?? '') !== $id));
        $this->pushAlert($row, 'Session terminated', ($target['device'] ?? 'Device') . ' was signed out', 'warn');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Session terminated.');
    }

    /** POST /admin/security-center/sessions/terminate-others */
    public function terminateOthers()
    {
        $row = $this->snapshot();
        $sessions = is_array($row->sessions) ? $row->sessions : [];
        $row->sessions = array_values(array_filter($sessions, fn ($s) => !empty($s['current'])));
        $this->pushAlert($row, 'Sessions terminated', 'All other sessions were signed out', 'warn');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Other sessions terminated.');
    }

    /** POST /admin/security-center/totp/regenerate */
    public function regenerateTotp()
    {
        $row = $this->snapshot();
        $row->totp_secret = $this->randomSecret();
        $row->backup_key = $this->randomBackupKey();
        $row->authenticator = false;
        $this->pushAlert($row, 'Authenticator secret regenerated', 'Re-scan the QR code to enable TOTP again', 'warn');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row, true), 'New TOTP secret generated.');
    }

    /** GET /admin/security-center/secrets */
    public function secrets()
    {
        return ResponseHelper::sendResponse($this->present($this->snapshot(), true), 'Secrets loaded.');
    }

    /** POST /admin/security-center/recovery-codes */
    public function regenerateCodes()
    {
        $row = $this->snapshot();
        $row->recovery_codes = $this->randomCodes();
        $row->codes_generated = true;
        $this->pushAlert($row, 'Recovery codes generated', 'Ten single-use codes are now active');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row, true), 'Recovery codes generated.');
    }

    /** PUT /admin/security-center/recovery-contact */
    public function updateContact(Request $request)
    {
        $kind = $request->input('kind', 'email');
        $value = trim((string) $request->input('value', ''));
        if ($value === '') {
            return ResponseHelper::sendResponse(null, 'value required', false, 422);
        }
        $row = $this->snapshot();
        if ($kind === 'phone') {
            $row->recovery_phone = $this->maskPhone($value);
        } else {
            $row->recovery_email = str_contains($value, '@') ? $this->maskEmail($value) : $value;
        }
        $this->pushAlert($row, 'Recovery contact updated', 'New ' . ($kind === 'phone' ? 'phone number' : 'email address') . ' saved');
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'Recovery contact updated.');
    }

    /** POST /admin/security-center/history */
    public function addHistory(Request $request)
    {
        $row = $this->snapshot();
        $this->pushHistory($row, [
            'type' => (string) $request->input('type', 'Authentication Test'),
            'device' => (string) $request->input('device', 'This device'),
            'method' => (string) $request->input('method', 'Passkey (WebAuthn)'),
            'ok' => $request->boolean('ok', true),
        ]);
        $row->save();
        return ResponseHelper::sendResponse($this->present($row), 'History updated.');
    }
}

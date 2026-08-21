<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * LoCoNet provider client — probes configured project endpoints.
 * URLs/cert come from admin integration snapshot, with env fallbacks.
 *
 * Auth style (Agora-like): X-App-Id + X-Project-Secret on server-side calls.
 */
class LoconetClient
{
    public function defaults(): array
    {
        $project = (string) config('services.loconet.project_id', '');
        $slug = (string) config('services.loconet.project_slug', $project);
        $apiBase = rtrim((string) config('services.loconet.api_base', ''), '/');

        $rest = ($apiBase !== '' && $slug !== '') ? "{$apiBase}/projects/{$slug}" : '';
        $token = $rest !== '' ? "{$rest}/auth/token" : '';

        return [
            'projectId' => $project,
            'projectSlug' => $slug,
            'appId' => (string) config('services.loconet.app_id', ''),
            'apiBase' => $apiBase,
            'rest' => $rest,
            'socket' => (string) config('services.loconet.socket_url', ''),
            'token' => $token,
            'webhook' => (string) config('services.loconet.webhook_url', 'https://api.appdash.yekbun.org/api/webhooks/loconet'),
            'media' => (string) config('services.loconet.media_url', ''),
            'webrtc' => (string) config('services.loconet.webrtc_url', ''),
            'health' => (string) config('services.loconet.health_url', ''),
            'certificate' => (string) config('services.loconet.certificate', ''),
        ];
    }

    /**
     * Merge saved integration + env defaults into a resolvable config bag.
     */
    public function resolve(array $integration = []): array
    {
        $d = $this->defaults();
        $endpoints = is_array($integration['endpoints'] ?? null) ? $integration['endpoints'] : [];

        $url = function (string $key, string $fallback) use ($endpoints, $integration): string {
            $fromEp = $endpoints[$key]['url'] ?? $endpoints[$key] ?? null;
            if (is_string($fromEp) && trim($fromEp) !== '') {
                return trim($fromEp);
            }
            $fromTop = $integration[$key] ?? null;
            if (is_string($fromTop) && trim($fromTop) !== '') {
                return trim($fromTop);
            }
            return $fallback;
        };

        $cert = (string) ($integration['primaryCert'] ?? $integration['projectSecret'] ?? $integration['apiKey'] ?? $d['certificate']);
        if ($cert === '' || str_starts_with($cert, '•')) {
            $cert = $d['certificate'];
        }

        $projectId = (string) ($integration['projectId'] ?? $integration['project_id'] ?? $d['projectId']);
        $slug = (string) ($integration['projectSlug'] ?? $integration['project_slug'] ?? $d['projectSlug']);
        if ($slug === '') {
            $slug = $projectId;
        }
        $appId = (string) ($integration['appId'] ?? $integration['app_id'] ?? $d['appId']);

        // Rebuild REST/token from slug when endpoints not explicitly set.
        $restDefault = $d['rest'];
        $tokenDefault = $d['token'];
        if ($slug !== '' && $d['apiBase'] !== '') {
            $restDefault = rtrim($d['apiBase'], '/') . '/projects/' . $slug;
            $tokenDefault = $restDefault . '/auth/token';
        }

        return [
            'projectId' => $projectId !== '' ? $projectId : $d['projectId'],
            'projectSlug' => $slug,
            'appId' => $appId,
            'apiBase' => (string) ($integration['apiBase'] ?? $d['apiBase']),
            'certificate' => $cert,
            'environment' => (string) ($integration['environment'] ?? 'Live'),
            'authMode' => (string) ($integration['authMode'] ?? 'secure'),
            'enabled' => (bool) ($integration['enabled'] ?? $integration['connected'] ?? false),
            'urls' => [
                'rest' => $url('rest', $restDefault),
                'socket' => $url('socket', $d['socket']),
                'token' => $url('token', $tokenDefault),
                'webhook' => $url('webhook', $d['webhook']),
                'media' => $url('media', $d['media']),
                'webrtc' => $url('webrtc', $d['webrtc']),
                'health' => $url('health', $d['health']),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,http_code:int|null,latency_ms:int,message:string}
     */
    public function probe(string $url, ?string $certificate = null, ?string $appId = null): array
    {
        $url = trim($url);
        if ($url === '') {
            return [
                'ok' => false,
                'status' => 'offline',
                'http_code' => null,
                'latency_ms' => 0,
                'message' => 'Endpoint URL is empty',
            ];
        }

        if (str_starts_with($url, 'wss://') || str_starts_with($url, 'ws://')) {
            return $this->probeWebsocketHost($url);
        }

        return $this->probeHttp($url, $certificate, $appId);
    }

    /**
     * @return array{ok:bool,status:string,http_code:int|null,latency_ms:int,message:string,results:array,config:array}
     */
    public function probeAll(array $integration = []): array
    {
        $cfg = $this->resolve($integration);
        // Prefer token + rest first — health may not exist on all LoCoNet hosts.
        $keys = ['token', 'rest', 'health', 'socket', 'webrtc', 'media', 'webhook'];
        $results = [];
        $anyOk = false;
        $maxLatency = 0;

        foreach ($keys as $key) {
            $target = $cfg['urls'][$key] ?? '';
            if ($target === '') {
                $results[$key] = [
                    'ok' => false,
                    'status' => 'offline',
                    'http_code' => null,
                    'latency_ms' => 0,
                    'message' => 'Not configured',
                ];
                continue;
            }
            $probe = $this->probe($target, $cfg['certificate'], $cfg['appId']);
            $results[$key] = $probe;
            if ($probe['ok']) {
                $anyOk = true;
            }
            $maxLatency = max($maxLatency, (int) $probe['latency_ms']);
        }

        // Connection OK if REST or token is reachable (401 with valid host still counts as wired).
        $coreOk = (bool) (($results['token']['ok'] ?? false) || ($results['rest']['ok'] ?? false));

        return [
            'ok' => $coreOk,
            'status' => $coreOk ? 'operational' : ($anyOk ? 'degraded' : 'offline'),
            'http_code' => null,
            'latency_ms' => $maxLatency,
            'message' => $coreOk
                ? 'LoCoNet project endpoints reachable'
                : 'Could not reach LoCoNet REST/token endpoints',
            'results' => $results,
            'config' => [
                'projectId' => $cfg['projectId'],
                'appId' => $cfg['appId'],
                'has_certificate' => $cfg['certificate'] !== '',
            ],
        ];
    }

    private function probeHttp(string $url, ?string $certificate, ?string $appId): array
    {
        $started = microtime(true);
        try {
            $headers = [
                'User-Agent' => 'YekBun-LoCoNet-Connector/1.0',
                'Accept' => 'application/json',
            ];
            if ($appId) {
                $headers['X-App-Id'] = $appId;
            }
            if ($certificate) {
                $headers['X-Project-Secret'] = $certificate;
                $headers['Authorization'] = 'Bearer ' . $certificate;
            }

            $req = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders($headers);

            $isToken = str_contains($url, '/auth');
            if ($isToken) {
                $response = $req->asJson()->post($url, [
                    'ping' => true,
                    'user_id' => 'yekbun-health-check',
                ]);
            } else {
                $response = $req->get($url);
                if ($response->status() === 405) {
                    $response = $req->asJson()->post($url, ['ping' => true]);
                }
            }

            $ms = (int) round((microtime(true) - $started) * 1000);
            $code = $response->status();
            $body = (string) $response->body();
            $reachable = $code > 0 && $code < 500;
            // 2xx = fully OK. 401 with "Invalid project credentials" means host is up
            // but secret/app mismatch. 401 "Unauthenticated" often means wrong auth shape
            // but still proves DNS/TLS/API gateway.
            $ok = ($code >= 200 && $code < 400) || ($reachable && in_array($code, [401, 403], true));
            $status = ($code >= 200 && $code < 400)
                ? 'operational'
                : ($reachable ? 'degraded' : 'offline');

            $msg = "HTTP {$code}";
            if ($body !== '' && strlen($body) < 200) {
                $msg .= ' · ' . trim($body);
            }

            return [
                'ok' => $ok,
                'status' => $status,
                'http_code' => $code,
                'latency_ms' => $ms,
                'message' => $msg,
            ];
        } catch (Throwable $e) {
            $ms = (int) round((microtime(true) - $started) * 1000);
            return [
                'ok' => false,
                'status' => 'offline',
                'http_code' => null,
                'latency_ms' => $ms,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function probeWebsocketHost(string $url): array
    {
        $started = microtime(true);
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $scheme = $parts['scheme'] ?? 'wss';
        $port = $parts['port'] ?? ($scheme === 'wss' ? 443 : 80);

        if ($host === '') {
            return [
                'ok' => false,
                'status' => 'offline',
                'http_code' => null,
                'latency_ms' => 0,
                'message' => 'Invalid WebSocket URL',
            ];
        }

        $errno = 0;
        $errstr = '';
        $target = ($scheme === 'wss' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($target, $errno, $errstr, 5, STREAM_CLIENT_CONNECT);
        $ms = (int) round((microtime(true) - $started) * 1000);

        if (is_resource($fp)) {
            fclose($fp);
            return [
                'ok' => true,
                'status' => 'operational',
                'http_code' => null,
                'latency_ms' => $ms,
                'message' => "TCP reachability OK ({$host}:{$port})",
            ];
        }

        return [
            'ok' => false,
            'status' => 'offline',
            'http_code' => null,
            'latency_ms' => $ms,
            'message' => $errstr !== '' ? $errstr : "Cannot reach {$host}:{$port}",
        ];
    }
}

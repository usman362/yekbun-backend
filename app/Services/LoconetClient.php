<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * LoCoNet provider client — probes configured project endpoints.
 * URLs/cert come from admin integration snapshot, with env fallbacks.
 */
class LoconetClient
{
    public function defaults(): array
    {
        $project = (string) config('services.loconet.project_id', 'yekbun-prod-01');
        $apiBase = rtrim((string) config('services.loconet.api_base', 'https://api.loconet.io/v1'), '/');

        return [
            'projectId' => $project,
            'apiBase' => $apiBase,
            'rest' => "{$apiBase}/projects/{$project}",
            'socket' => (string) config('services.loconet.socket_url', "wss://realtime.loconet.io/socket/{$project}"),
            'token' => "{$apiBase}/projects/{$project}/auth/token",
            'webhook' => (string) config('services.loconet.webhook_url', 'https://api.appdash.yekbun.org/api/webhooks/loconet'),
            'media' => (string) config('services.loconet.media_url', "https://media.loconet.io/v1/upload/{$project}"),
            'webrtc' => (string) config('services.loconet.webrtc_url', "wss://rtc.loconet.io/signal/{$project}"),
            'health' => (string) config('services.loconet.health_url', "{$apiBase}/health"),
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

        $cert = (string) ($integration['primaryCert'] ?? $integration['apiKey'] ?? $d['certificate']);
        if ($cert === '' || str_starts_with($cert, '•')) {
            $cert = $d['certificate'];
        }

        $projectId = (string) ($integration['projectId'] ?? $integration['project_id'] ?? $d['projectId']);

        return [
            'projectId' => $projectId !== '' ? $projectId : $d['projectId'],
            'apiBase' => (string) ($integration['apiBase'] ?? $d['apiBase']),
            'certificate' => $cert,
            'environment' => (string) ($integration['environment'] ?? 'Live'),
            'authMode' => (string) ($integration['authMode'] ?? 'secure'),
            'enabled' => (bool) ($integration['enabled'] ?? $integration['connected'] ?? false),
            'urls' => [
                'rest' => $url('rest', $d['rest']),
                'socket' => $url('socket', $d['socket']),
                'token' => $url('token', $d['token']),
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
    public function probe(string $url, ?string $certificate = null): array
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

        return $this->probeHttp($url, $certificate);
    }

    /**
     * @return array{ok:bool,status:string,http_code:int|null,latency_ms:int,message:string,results:array}
     */
    public function probeAll(array $integration = []): array
    {
        $cfg = $this->resolve($integration);
        $keys = ['health', 'rest', 'token', 'socket', 'webrtc', 'media', 'webhook'];
        $results = [];
        $allOk = true;
        $maxLatency = 0;

        foreach ($keys as $key) {
            $probe = $this->probe($cfg['urls'][$key], $cfg['certificate']);
            $results[$key] = $probe;
            if (!$probe['ok']) {
                $allOk = false;
            }
            $maxLatency = max($maxLatency, (int) $probe['latency_ms']);
        }

        return [
            'ok' => $allOk,
            'status' => $allOk ? 'operational' : 'degraded',
            'http_code' => null,
            'latency_ms' => $maxLatency,
            'message' => $allOk ? 'All reachable endpoints responded' : 'One or more endpoints failed',
            'results' => $results,
            'config' => [
                'projectId' => $cfg['projectId'],
                'has_certificate' => $cfg['certificate'] !== '',
            ],
        ];
    }

    private function probeHttp(string $url, ?string $certificate): array
    {
        $started = microtime(true);
        try {
            $req = Http::timeout(8)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'YekBun-LoCoNet-Connector/1.0']);

            if ($certificate) {
                $req = $req->withToken($certificate)
                    ->withHeaders(['X-LoCoNet-Certificate' => $certificate]);
            }

            // Prefer GET; some token endpoints only accept POST — fall back on 405.
            $response = $req->get($url);
            if ($response->status() === 405) {
                $response = $req->asJson()->post($url, ['ping' => true]);
            }

            $ms = (int) round((microtime(true) - $started) * 1000);
            $code = $response->status();
            // 2xx/3xx/401/403 = host reachable (auth may still need valid cert)
            $reachable = $code > 0 && $code < 500;
            $ok = $code >= 200 && $code < 400;

            $status = $ok ? 'operational' : ($reachable ? 'degraded' : 'offline');

            return [
                'ok' => $ok || ($reachable && in_array($code, [401, 403], true)),
                'status' => $status,
                'http_code' => $code,
                'latency_ms' => $ms,
                'message' => "HTTP {$code}",
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

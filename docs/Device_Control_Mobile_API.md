# YekBûn — Device Control Mobile APIs (Final)

**Base:** `https://api.appdash.yekbun.org/api`  
**Auth:** not required for these 3 endpoints  
**Cache pack:** v1.15.0

## Boot flow
1. Cold start → `GET /api/app/device-profile`
2. Apply `data.runtime` + `data.cache` locally
3. Heartbeat → `POST /api/app/device-telemetry`
4. Crash/ANR → `POST /api/app/device-crash`

No separate cache endpoint — cache is inside resolve.

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/api/app/device-profile` | Resolve device + runtime + cache |
| POST | `/api/app/device-telemetry` | Heartbeat upsert by `device_id` |
| POST | `/api/app/device-crash` | Crash/ANR → `problem_devices` |

### Resolve
```
GET /api/app/device-profile?ram=4%20GB&cpu_tier=low&os=Android&os_version=13
```
Params: `ram` or `ram_class` (4|6|8|12+), `cpu_tier` (entry|low|mid|high|flagship), `os`, `os_version`, `manufacturer`, `model`.

### Apply from runtime
- `video.max_active`, `video.quality`, `video.preload`, `video.buffer`, `video.autoplay`
- `reels.initial`, `reels.next_preload`, `reels.video_queue`
- `feed.batch_size`, `feed.strategy`
- `api.max_parallel`, `api.timeout_ms`
- `cache.categories[].max_size` (MB caps)

### Cache budgets (v1.15.0)
| Tier | RAM | Total | Video | Reels | Feed |
|------|-----|-------|-------|-------|------|
| Entry | ≤4 GB | 169 | 28 | 20 | 20 |
| Low | 4 GB | 266 | 48 | 36 | 32 |
| Balanced | 6–8 | 476 | 96 | 64 | 48 |
| High | 8–12 | 784 | 180 | 120 | 80 |
| Ultra | 12+ | 1248 | 320 | 200 | 120 |

### Telemetry
Required: `device_id`. Upserts `device_telemetry`.

### Crash
Required: `device_id`, `problem_type`. Writes `problem_devices`, bumps telemetry crash_count.

## QA collections
- resolve reads: `device_profiles`, `runtime_profiles`, `cache_profiles`
- telemetry writes: `device_telemetry`
- crash writes: `problem_devices`

If resolve 404: `php artisan device-control:seed-defaults`

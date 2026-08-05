# YekBûn — Device Control Mobile APIs (Final)

**Base:** `https://api.appdash.yekbun.org/api`  
**Auth:** not required for these endpoints  
**Cache pack:** v1.15.0

## Boot flow
1. Cold start → `GET /api/app/device-profile`
2. Apply `data.runtime` + `data.cache` locally
3. Heartbeat → `POST /api/app/device-telemetry`
4. When cache size changes → `POST /api/app/device-cache-current`
5. Crash/ANR → `POST /api/app/device-crash`

`max_size` comes from resolve (`cache.categories[]`).  
`current` is **per-device** usage — report it via cache-current (does not change admin profile caps).

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/api/app/device-profile` | Resolve device + runtime + cache |
| POST | `/api/app/device-telemetry` | Heartbeat upsert by `device_id` |
| POST | `/api/app/device-cache-current` | Update category `current` MB by type |
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

### Cache current (per device)
```
POST /api/app/device-cache-current
Content-Type: application/json
```

**Single category**
```json
{
  "device_id": "ANDROID-IMEI-OR-UUID",
  "type": "video",
  "current": 15,
  "profile_key": "entry"
}
```

**Batch**
```json
{
  "device_id": "ANDROID-IMEI-OR-UUID",
  "profile_key": "entry",
  "categories": [
    { "type": "system", "current": 9 },
    { "type": "feed", "current": 11 },
    { "type": "video", "current": 15 },
    { "type": "reels", "current": 11 }
  ]
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `device_id` | yes | Unique device id |
| `type` | single mode | Category: `system`, `feed`, `video`, `reels`, `image`, `music`, `chat`, `maps`, … |
| `current` | single mode | Used MB (number). Aliases: `current_size`, `value` |
| `categories[]` | batch mode | Each item: `type` + `current` |
| `profile_key` | no | e.g. `entry` / `low` — used to return `max_size` hint |

**Response `data`**
```json
{
  "device_id": "ANDROID-IMEI-OR-UUID",
  "profile_key": "entry",
  "updated": [
    { "type": "video", "current": 15, "previous": 12, "max_size": 28, "updated_at": "…" }
  ],
  "cache_categories": {
    "video": { "current": 15, "updated_at": "…" }
  },
  "total_current_mb": 15
}
```

Allowed `type` values:  
`system`, `feed`, `video`, `reels`, `image`, `music`, `chat`, `maps`, `notification`, `offline`, `downloads`, `fonts`, `emoji`, `languages`, `policy`, `profile`, `temp`

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
- telemetry / cache-current writes: `device_telemetry` (`cache_categories`)
- crash writes: `problem_devices`

If resolve 404: `php artisan device-control:seed-defaults`

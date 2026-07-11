<?php

namespace App\Helpers;

use App\Jobs\SendPushNotification;
use App\Models\NotificationCenter;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     * Create an in-app Notification Center row (after DB save of the triggering action),
     * then dispatch FCM delivery through the Queue.
     *
     * Status flow: created → queued → (job) sending → sent|failed → (user opens) read
     *
     * Never throws — callers must not fail the parent action because of notification issues.
     */
    public static function notifyUser(
        $userId,
        ?string $title,
        ?string $body,
        string $type = 'general',
        $sendById = null,
        $actorImage = null,
        ?string $dedupeKey = null,
        $relatedId = null,
        ?string $relatedType = null,
        bool $skipSelf = true
    ): ?NotificationCenter {
        try {
            if ($userId === null || $userId === '') {
                return null;
            }

            if ($skipSelf && $sendById !== null && (string) $userId === (string) $sendById) {
                return null;
            }

            if ($dedupeKey) {
                $existing = NotificationCenter::where('dedupe_key', $dedupeKey)->first();
                if ($existing) {
                    // Friend request can be cancelled and re-sent — refresh instead of silently
                    // dropping the new notify (permanent dedupe would block forever).
                    if ($type === 'friend_request') {
                        $existing->delete();
                    } else {
                        return null;
                    }
                }
            }

            $notification = NotificationCenter::create([
                'title'         => $title,
                'description'   => $body,
                'user_id'       => $userId,
                'send_by_id'    => $sendById,
                'user_image'    => $actorImage,
                'type'          => $type,
                'related_id'    => $relatedId,
                'related_type'  => $relatedType,
                'dedupe_key'    => $dedupeKey,
                'status'        => 'created',
                'is_read'       => 0,
            ]);

            $notification->status = 'queued';
            $notification->save();

            SendPushNotification::dispatch((string) $notification->id);

            return $notification;
        } catch (\Throwable $e) {
            Log::warning('notifyUser failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'type'    => $type,
            ]);
            return null;
        }
    }

    /** Display name for notification copy ("John Doe"). */
    public static function actorName($user): string
    {
        if (!$user) {
            return 'Someone';
        }
        $name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : (string) ($user->username ?? 'Someone');
    }

    /**
     * Sync FCM push. Returns true when push succeeded OR was skipped because the user
     * has no device token / FCM is not configured (in-app row still counts as delivered).
     * Returns false only when an FCM attempt with a token fails.
     */
    public static function pushFcm($user_id, $title = null, $body = null): bool
    {
        $user = \App\Models\User::find($user_id);
        $fcm = $user->fcm_token ?? null;

        if (!$fcm) {
            return true; // in-app only — no push target
        }

        try {
            $projectId = env('FCM_PROJECT_ID');
            $credentialsFilePath = storage_path('app/json/services.json');

            if (!$projectId || !file_exists($credentialsFilePath)) {
                Log::warning('FCM not configured — push skipped.', [
                    'has_project_id' => (bool) $projectId,
                    'has_credentials' => file_exists($credentialsFilePath),
                ]);
                return true;
            }

            $client = new GoogleClient();
            $client->setAuthConfig($credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            $access_token = $token['access_token'] ?? null;

            if (!$access_token) {
                Log::error('FCM: could not obtain access token.');
                return false;
            }

            $headers = [
                "Authorization: Bearer $access_token",
                'Content-Type: application/json',
            ];

            $payload = json_encode([
                'message' => [
                    'token' => $fcm,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                ],
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                Log::error('FCM curl error: ' . $err);
                return false;
            }

            if ($httpCode >= 400) {
                Log::error('FCM HTTP error', [
                    'http' => $httpCode,
                    'body' => $response,
                    'user_id' => $user_id,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('FCM push failed: ' . $e->getMessage(), ['user_id' => $user_id]);
            return false;
        }
    }

    /**
     * Legacy sync wrapper — still used by a few call sites. Prefer notifyUser for new work.
     * Failure-safe: never throws into the parent request.
     */
    public static function sendNotification($user_id, $title = null, $body = null)
    {
        self::pushFcm($user_id, $title, $body);
        return response()->json(['message' => 'Notification processed']);
    }

    /**
     * Config-driven broadcast for admin/content notifications (Portal Notifications).
     *
     * Reads the admin's `Notifications` config row and fires a push + in-app notification
     * for a given type key (e.g. 'new_history', 'new_music'). Mirrors the legacy portal
     * notification behaviour:
     *   - Skips entirely unless the admin enabled that type (`{key} == 'true'`).
     *   - Uses the admin-configured title + description ([name] etc. placeholders replaced).
     *   - Sends only to users who have a device token, opted into that type at the user
     *     level (same `{key}` field = 'true'), and enabled banner/alert notifications.
     *
     * Never throws — a broken config or token can't break the content upload that called it.
     *
     * @param string $key      config field, e.g. 'new_history'
     * @param array  $replace  placeholder => value map for the description (e.g. ['[name]' => $title])
     * @param string $type     NotificationCenter row type (e.g. 'history')
     */
    public static function sendConfiguredBroadcast(string $key, array $replace = [], string $type = 'general'): void
    {
        try {
            $config = \App\Models\Notifications::first();
            if (!$config || (string) ($config->{$key} ?? 'false') !== 'true') {
                return; // admin has this notification type switched off
            }

            $title = (string) ($config->{$key . '_title'} ?? '');
            $description = str_replace(
                array_keys($replace),
                array_values($replace),
                (string) ($config->{$key . '_description'} ?? '')
            );

            // Reach every push-enabled user (info_banner banner/alert) EXCEPT those who
            // explicitly disabled this specific type. Using `!= 'false'` (Mongo $ne) means
            // users whose per-type field was never set — e.g. new_ai_videos, which registration
            // never seeds — still receive it, instead of the old `== 'true'` which matched
            // nobody for any type other than new_history.
            $users = \App\Models\User::whereNotNull('fcm_token')
                ->where($key, '!=', 'false')
                ->whereIn('info_banner', ['banner', 'alert'])
                ->get();

            foreach ($users as $user) {
                self::sendNotification($user->id, $title, $description);
                \App\Models\NotificationCenter::create([
                    'title'       => $title,
                    'description' => $description,
                    'user_id'     => $user->id,
                    'user_image'  => $user->image ?? null,
                    'type'        => $type,
                    'status'      => 'sent',
                    'is_read'     => 0,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('sendConfiguredBroadcast failed: ' . $e->getMessage(), ['key' => $key]);
        }
    }
}

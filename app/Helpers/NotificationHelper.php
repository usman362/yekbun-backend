<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Google\Client as GoogleClient;

class NotificationHelper
{
    public static function sendNotification($user_id, $title = null, $body = null)
    {
        $user = \App\Models\User::find($user_id);
        $fcm = $user->fcm_token ?? null;

        if (!$fcm) {
            return response()->json(['message' => 'User does not have a device token'], 400);
        }

        // Never let a push failure bubble up — this helper is called inline inside friend
        // requests, comments, etc. An uncaught FCM error (missing services.json, expired
        // token, network blip) would otherwise break the whole parent operation.
        try {
            $projectId = env('FCM_PROJECT_ID');
            $credentialsFilePath = Storage::path('json/services.json');

            if (!$projectId || !file_exists($credentialsFilePath)) {
                \Illuminate\Support\Facades\Log::warning('FCM not configured — push skipped.', [
                    'has_project_id' => (bool) $projectId,
                    'has_credentials' => file_exists($credentialsFilePath),
                ]);
                return response()->json(['message' => 'FCM not configured — push skipped'], 200);
            }

            $client = new GoogleClient();
            $client->setAuthConfig($credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            $access_token = $token['access_token'] ?? null;

            if (!$access_token) {
                \Illuminate\Support\Facades\Log::error('FCM: could not obtain access token.');
                return response()->json(['message' => 'FCM auth failed — push skipped'], 200);
            }

            $headers = [
                "Authorization: Bearer $access_token",
                'Content-Type: application/json'
            ];

            $data = [
                "message" => [
                    "token" => $fcm,
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                    ],
                ]
            ];
            $payload = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                \Illuminate\Support\Facades\Log::error('FCM curl error: ' . $err);
                return response()->json(['message' => 'Curl Error: ' . $err], 200);
            }

            return response()->json([
                'message' => 'Notification has been sent',
                'response' => json_decode($response, true)
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('FCM push failed: ' . $e->getMessage(), ['user_id' => $user_id]);
            return response()->json(['message' => 'Notification push failed (logged)', 'error' => $e->getMessage()], 200);
        }
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

            $users = \App\Models\User::whereNotNull('fcm_token')
                ->where($key, 'true')
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
                    'is_read'     => 0,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('sendConfiguredBroadcast failed: ' . $e->getMessage(), ['key' => $key]);
        }
    }
}

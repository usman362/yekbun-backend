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
}

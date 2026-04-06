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

        $projectId = env('FCM_PROJECT_ID');
        $credentialsFilePath = Storage::path('json/services.json');

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();
        $access_token = $token['access_token'];

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
            return response()->json(['message' => 'Curl Error: ' . $err], 500);
        }

        return response()->json([
            'message' => 'Notification has been sent',
            'response' => json_decode($response, true)
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Helpers\NotificationHelper;
use App\Models\NotificationCenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers an already-created NotificationCenter row via FCM and updates its status.
 * Status flow: queued → sending → sent|failed
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = NotificationCenter::find($this->notificationId);
        if (!$notification) {
            return;
        }

        // Already delivered or marked read — don't re-push.
        if (in_array((string) ($notification->status ?? ''), ['sent', 'read'], true)) {
            return;
        }

        $notification->status = 'sending';
        $notification->save();

        try {
            $ok = NotificationHelper::pushFcm(
                $notification->user_id,
                $notification->title,
                $notification->description
            );

            $notification->status = $ok ? 'sent' : 'failed';
            if ($ok) {
                $notification->sent_at = now();
            }
            $notification->save();
        } catch (\Throwable $e) {
            Log::error('SendPushNotification failed: ' . $e->getMessage(), [
                'notification_id' => $this->notificationId,
            ]);
            $notification->status = 'failed';
            $notification->save();
            throw $e;
        }
    }
}

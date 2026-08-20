<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\LoconetState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public webhook receiver for LoCoNet → YekBûn event callbacks.
 * POST /api/webhooks/loconet
 */
class LoconetWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = (string) config('services.loconet.webhook_secret', '');
        if ($secret !== '') {
            $header = (string) ($request->header('X-LoCoNet-Signature')
                ?? $request->header('X-Webhook-Secret')
                ?? '');
            if (!hash_equals($secret, $header)) {
                return ResponseHelper::sendResponse(null, 'Invalid webhook signature', false, 401);
            }
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');

        Log::info('loconet.webhook', [
            'event' => $event,
            'keys' => array_keys($payload),
        ]);

        $row = LoconetState::orderBy('updated_at', 'desc')->first();
        if ($row) {
            $logs = is_array($row->integrationLogs) ? $row->integrationLogs : [];
            array_unshift($logs, [
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'text' => 'WEBHOOK ' . $event . ' · accepted',
            ]);
            $row->integrationLogs = array_slice($logs, 0, 40);

            $activity = is_array($row->activity) ? $row->activity : [];
            array_unshift($activity, [
                'text' => 'LoCoNet webhook: ' . $event,
                'time' => 'Just now',
                'tone' => 'blue',
            ]);
            $row->activity = array_slice($activity, 0, 20);
            $row->save();
        }

        return ResponseHelper::sendResponse([
            'received' => true,
            'event' => $event,
        ], 'Webhook accepted.');
    }
}

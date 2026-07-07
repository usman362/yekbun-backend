<?php

namespace App\Http\Middleware;

use App\Models\Maintenance;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks a request with HTTP 410 when the platform (or the given section) is under maintenance.
 * Attach to any route group with the section key, e.g. `->middleware('maintenance:music')`.
 * The mobile app just checks for status 410 to show its maintenance UI — no separate polling.
 */
class CheckMaintenance
{
    public function handle(Request $request, Closure $next, ?string $section = null)
    {
        $config = Maintenance::first();

        if ($config) {
            // Whole app offline → everything returns 410.
            if ($config->full_platform) {
                return $this->offline('The app is currently under maintenance. Please try again later.', null);
            }

            // Is this specific section switched offline?
            if ($section) {
                foreach (($config->categories ?? []) as $cat) {
                    foreach (($cat['subcategories'] ?? []) as $sub) {
                        $match = in_array(strtolower($section), [
                            strtolower($sub['key'] ?? ''),
                            strtolower($sub['name'] ?? ''),
                        ], true);
                        if ($match && empty($sub['online'])) {
                            return $this->offline(
                                ($sub['name'] ?? $section) . ' is temporarily under maintenance.',
                                $sub['eta'] ?? null
                            );
                        }
                    }
                }
            }
        }

        return $next($request);
    }

    private function offline(string $message, $eta)
    {
        return response()->json([
            'success'     => false,
            'maintenance' => true,
            'message'     => $message,
            'eta'         => $eta,
        ], 410);
    }
}

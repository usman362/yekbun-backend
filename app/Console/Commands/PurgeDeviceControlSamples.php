<?php

namespace App\Console\Commands;

use App\Models\CacheProfile;
use App\Models\DeviceProfile;
use App\Models\DeviceTelemetry;
use App\Models\ProblemDevice;
use App\Models\RuntimeProfile;
use Illuminate\Console\Command;

/**
 * Remove Device Control scaffolding / dummy rows (seeded telemetry + problem groups).
 * Keeps Entry→Ultra device / runtime / cache profiles intact.
 * Also zeroes stored assigned/affected counts (UI now uses live telemetry).
 *
 *   php artisan device-control:purge-samples
 *   php artisan device-control:purge-samples --all   # wipe every telemetry + problem row
 */
class PurgeDeviceControlSamples extends Command
{
    protected $signature = 'device-control:purge-samples
                            {--all : Delete ALL device_telemetry and problem_devices (not only known seed ids)}
                            {--force : Required confirmation for destructive delete}';

    protected $description = 'Delete Device Control dummy telemetry / problem-device rows; zero seed fleet counts (profiles stay).';

    /** Seeded sample telemetry ids from SeedDeviceControlDefaults. */
    private const SAMPLE_DEVICE_IDS = [
        'D-01', 'D-02', 'D-03', 'D-04', 'D-05', 'D-06', 'D-07', 'D-08',
        'D-09', 'D-10', 'D-11', 'D-12', 'D-13', 'D-14', 'D-15',
        'APP-USE-1', 'APP-USE-2', 'TEST-AUTO-1', 'MOBILE-TEST-1',
    ];

    /** Seeded problem group ids. */
    private const SAMPLE_GROUP_IDS = [
        'PG-001', 'PG-002', 'PG-003', 'PG-004', 'PG-005',
    ];

    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->error('Refusing without --force.');
            $this->line('Run: php artisan device-control:purge-samples --force');
            $this->line('Or wipe everything: php artisan device-control:purge-samples --all --force');
            return self::FAILURE;
        }

        if ($this->option('all')) {
            $t = DeviceTelemetry::query()->delete();
            $p = ProblemDevice::query()->delete();
            $this->zeroProfileFleetCounts();
            $this->warn("Deleted ALL telemetry={$t}, problems={$p}. Profiles kept; fleet counts zeroed.");
            return self::SUCCESS;
        }

        $telemetryDeleted = DeviceTelemetry::whereIn('device_id', self::SAMPLE_DEVICE_IDS)->delete();

        // Also catch leftover test ids (TEST-*, APP-USE-*, MOBILE-TEST-*, D-##)
        $extraTelemetry = 0;
        foreach (DeviceTelemetry::query()->get(['_id', 'device_id']) as $row) {
            $id = (string) ($row->device_id ?? '');
            if ($id === '') {
                continue;
            }
            if (preg_match('/^(D-\d+|APP-USE-|TEST-|MOBILE-TEST-)/i', $id)) {
                $row->delete();
                $extraTelemetry++;
            }
        }

        $problemsDeleted = ProblemDevice::whereIn('group_id', self::SAMPLE_GROUP_IDS)->delete();

        // Seeded UI scaffolding used PG-00x; also drop orphan test crash groups without a live device_id
        $extraProblems = 0;
        foreach (ProblemDevice::query()->get(['_id', 'group_id', 'device_id', 'device_group']) as $row) {
            $gid = (string) ($row->group_id ?? '');
            if (preg_match('/^PG-00\d+$/i', $gid)) {
                $row->delete();
                $extraProblems++;
                continue;
            }
            // Crash API test groups look like PG- + hex and often have no device_id
            if (preg_match('/^PG-[0-9A-F]{6,}$/i', $gid) && empty($row->device_id)) {
                $row->delete();
                $extraProblems++;
            }
        }

        $this->zeroProfileFleetCounts();

        $this->info('Dummy Device Control rows removed (profiles kept).');
        $this->line('  telemetry deleted: ' . ($telemetryDeleted + $extraTelemetry));
        $this->line('  problems deleted:  ' . ($problemsDeleted + $extraProblems));
        $this->line('  telemetry left:    ' . DeviceTelemetry::count());
        $this->line('  problems left:     ' . ProblemDevice::count());
        $this->line('  profile fleet counts zeroed (live counts come from telemetry).');

        return self::SUCCESS;
    }

    /** Clear seeded placeholder fleet sizes stored on profile docs. */
    private function zeroProfileFleetCounts(): void
    {
        DeviceProfile::query()->update(['assigned_devices' => 0]);
        RuntimeProfile::query()->update(['affected_devices' => 0]);
        CacheProfile::query()->update(['affected_devices' => 0]);
    }
}

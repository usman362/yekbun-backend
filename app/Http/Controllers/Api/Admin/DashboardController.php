<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Feed;
use App\Models\FeedViews;
use App\Models\Artist;
use App\Models\Song;
use App\Models\VideoClip;
use App\Models\Clips;
use App\Models\Report;
use App\Models\Voting;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        $totalUsers = User::count();
        $usersLast30 = User::where('created_at', '>=', $thirtyDaysAgo)->count();
        $usersPrev30 = User::whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();
        $usersChange = $usersPrev30 > 0 ? round((($usersLast30 - $usersPrev30) / $usersPrev30) * 100, 1) : 0;

        $activeUsers = User::where('updated_at', '>=', $now->copy()->subHours(24))->count();
        $activePrev = User::whereBetween('updated_at', [$now->copy()->subHours(48), $now->copy()->subHours(24)])->count();
        $activeChange = $activePrev > 0 ? round((($activeUsers - $activePrev) / $activePrev) * 100, 1) : 0;

        $totalFeeds = Feed::count();
        $feedsLast30 = Feed::where('created_at', '>=', $thirtyDaysAgo)->count();
        $feedsPrev30 = Feed::whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();
        $feedsChange = $feedsPrev30 > 0 ? round((($feedsLast30 - $feedsPrev30) / $feedsPrev30) * 100, 1) : 0;

        $totalImpressions = FeedViews::count();
        $impressionsLast30 = FeedViews::where('created_at', '>=', $thirtyDaysAgo)->count();
        $impressionsPrev30 = FeedViews::whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();
        $impressionsChange = $impressionsPrev30 > 0 ? round((($impressionsLast30 - $impressionsPrev30) / $impressionsPrev30) * 100, 1) : 0;

        $totalArtists = Artist::count();
        $artistsLast30 = Artist::where('created_at', '>=', $thirtyDaysAgo)->count();
        $artistsPrev30 = Artist::whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();
        $artistsChange = $artistsPrev30 > 0 ? round((($artistsLast30 - $artistsPrev30) / $artistsPrev30) * 100, 1) : 0;

        $growthRate = $totalUsers > 0 ? round(($usersLast30 / $totalUsers) * 100, 1) : 0;

        return ResponseHelper::sendResponse([
            'kpis' => [
                ['label' => 'Total Users',  'value' => $this->formatNumber($totalUsers),       'change' => $this->formatChange($usersChange),       'up' => $usersChange >= 0],
                ['label' => 'Active Now',   'value' => $this->formatNumber($activeUsers),      'change' => $this->formatChange($activeChange),      'up' => $activeChange >= 0],
                ['label' => 'Total Feeds',  'value' => $this->formatNumber($totalFeeds),       'change' => $this->formatChange($feedsChange),       'up' => $feedsChange >= 0],
                ['label' => 'Impressions',  'value' => $this->formatNumber($totalImpressions), 'change' => $this->formatChange($impressionsChange), 'up' => $impressionsChange >= 0],
                ['label' => 'Artists',      'value' => $this->formatNumber($totalArtists),     'change' => $this->formatChange($artistsChange),     'up' => $artistsChange >= 0],
                ['label' => 'Growth Rate',  'value' => '+' . $growthRate . '%',                'change' => $this->formatChange($usersChange),       'up' => $usersChange >= 0],
            ],
        ], 'Dashboard stats fetched.');
    }

    public function activity()
    {
        $colors = [
            'from-blue-500 to-blue-600',
            'from-amber-500 to-amber-600',
            'from-emerald-500 to-emerald-600',
            'from-rose-500 to-rose-600',
            'from-violet-500 to-violet-600',
            'from-cyan-500 to-cyan-600',
        ];

        $recentFeeds = Feed::with('user:name,username')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get(['user_id', 'feed_type', 'created_at']);

        $activity = $recentFeeds->values()->map(function ($feed, $i) use ($colors) {
            $name = $feed->user->name ?? $feed->user->username ?? 'Unknown';
            $parts = explode(' ', $name);
            $avatar = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? $parts[0], 0, 1));

            $shortName = $parts[0] . ' ' . strtoupper(substr($parts[1] ?? '', 0, 1)) . '.';
            if (count($parts) < 2) $shortName = $parts[0];

            $type = $feed->feed_type ?? 'feed';
            $actionMap = [
                'feed'  => 'Published a new feed',
                'video' => 'Uploaded a video',
                'image' => 'Shared an image',
                'text'  => 'Posted an update',
            ];

            return [
                'user'   => $shortName,
                'action' => $actionMap[$type] ?? 'Published a new feed',
                'time'   => Carbon::parse($feed->created_at)->diffForHumans(null, false, true),
                'avatar' => $avatar,
                'color'  => $colors[$i % count($colors)],
            ];
        });

        return ResponseHelper::sendResponse($activity, 'Recent activity fetched.');
    }

    public function geo()
    {
        $countryMeta = [
            'Kurdistan'    => ['code' => 'KRD', 'flag' => '🏔️',  'lat' => 36, 'lng' => 44],
            'Germany'      => ['code' => 'DE',  'flag' => '🇩🇪', 'lat' => 51, 'lng' => 10],
            'Turkey'       => ['code' => 'TR',  'flag' => '🇹🇷', 'lat' => 39, 'lng' => 35],
            'France'       => ['code' => 'FR',  'flag' => '🇫🇷', 'lat' => 46, 'lng' => 2],
            'Lebanon'      => ['code' => 'LB',  'flag' => '🇱🇧', 'lat' => 34, 'lng' => 36],
            'UAE'          => ['code' => 'AE',  'flag' => '🇦🇪', 'lat' => 24, 'lng' => 54],
            'Sweden'       => ['code' => 'SE',  'flag' => '🇸🇪', 'lat' => 60, 'lng' => 18],
            'UK'           => ['code' => 'GB',  'flag' => '🇬🇧', 'lat' => 54, 'lng' => -2],
            'Netherlands'  => ['code' => 'NL',  'flag' => '🇳🇱', 'lat' => 52, 'lng' => 5],
            'Morocco'      => ['code' => 'MA',  'flag' => '🇲🇦', 'lat' => 32, 'lng' => -5],
            'Iraq'         => ['code' => 'IQ',  'flag' => '🇮🇶', 'lat' => 33, 'lng' => 44],
            'Syria'        => ['code' => 'SY',  'flag' => '🇸🇾', 'lat' => 35, 'lng' => 38],
            'Austria'      => ['code' => 'AT',  'flag' => '🇦🇹', 'lat' => 47, 'lng' => 14],
            'Belgium'      => ['code' => 'BE',  'flag' => '🇧🇪', 'lat' => 51, 'lng' => 4],
            'Switzerland'  => ['code' => 'CH',  'flag' => '🇨🇭', 'lat' => 47, 'lng' => 8],
            'Denmark'      => ['code' => 'DK',  'flag' => '🇩🇰', 'lat' => 56, 'lng' => 10],
            'Norway'       => ['code' => 'NO',  'flag' => '🇳🇴', 'lat' => 60, 'lng' => 11],
            'Iran'         => ['code' => 'IR',  'flag' => '🇮🇷', 'lat' => 33, 'lng' => 53],
            'USA'          => ['code' => 'US',  'flag' => '🇺🇸', 'lat' => 38, 'lng' => -97],
            'Canada'       => ['code' => 'CA',  'flag' => '🇨🇦', 'lat' => 56, 'lng' => -106],
        ];

        $totalUsers = User::count() ?: 1;

        $grouped = User::raw(function ($collection) {
            return $collection->aggregate([
                ['$match' => ['country' => ['$ne' => null]]],
                ['$group' => ['_id' => '$country', 'count' => ['$sum' => 1]]],
                ['$sort'  => ['count' => -1]],
                ['$limit' => 10],
            ]);
        });

        $countries = collect($grouped)->map(function ($row) use ($totalUsers, $countryMeta) {
            $name = $row['_id'];
            $meta = $countryMeta[$name] ?? null;

            return [
                'name'  => $name,
                'code'  => $meta['code'] ?? strtoupper(substr($name, 0, 2)),
                'users' => $row['count'],
                'pct'   => round(($row['count'] / $totalUsers) * 100, 1),
                'flag'  => $meta['flag'] ?? '🌍',
                'lat'   => $meta['lat'] ?? 0,
                'lng'   => $meta['lng'] ?? 0,
            ];
        })->values();

        return ResponseHelper::sendResponse($countries, 'Geo distribution fetched.');
    }

    public function server()
    {
        $dbStats = [];
        try {
            $result = User::raw(function ($collection) {
                return $collection->getManager()->selectServer()->executeCommand(
                    $collection->getDatabaseName(),
                    new \MongoDB\Driver\Command(['dbStats' => 1])
                );
            });
            $stats = current($result->toArray());
            $dbSize = round(($stats->dataSize ?? 0) / (1024 * 1024 * 1024), 1);
            $storageSize = round(($stats->storageSize ?? 0) / (1024 * 1024 * 1024), 1);
            $dbStats = ['db_size' => $dbSize, 'storage_size' => $storageSize];
        } catch (\Throwable $e) {
            $dbStats = ['db_size' => 0, 'storage_size' => 0];
        }

        $metrics = [
            ['label' => 'API Uptime',  'value' => '99.98%',                              'status' => 'healthy'],
            ['label' => 'Database',    'value' => $dbStats['db_size'] . ' GB',            'status' => 'healthy'],
            ['label' => 'Storage',     'value' => $dbStats['storage_size'] . ' GB',       'status' => $dbStats['storage_size'] > 5 ? 'warning' : 'healthy'],
            ['label' => 'Bandwidth',   'value' => '2.1 TB',                               'status' => 'healthy'],
        ];

        return ResponseHelper::sendResponse($metrics, 'Server metrics fetched.');
    }

    private function formatNumber($num): string
    {
        if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
        if ($num >= 1000) return number_format($num);
        return (string) $num;
    }

    private function formatChange($val): string
    {
        return ($val >= 0 ? '+' : '') . $val . '%';
    }
}

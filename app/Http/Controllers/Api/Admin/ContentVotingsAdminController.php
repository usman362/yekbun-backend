<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\Helpers;
use App\Helpers\NotificationHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voting;
use App\Models\VotingReaction;
use App\Models\VotingViews;
use App\Services\BunnyCDNService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentVotingsAdminController extends Controller
{
    public function index()
    {
        $rows = Voting::orderBy('created_at', 'desc')->limit(200)->get();
        $ids = $rows->pluck('_id')->toArray();

        $reactionsByVoting = VotingReaction::whereIn('voting_id', $ids)
            ->get()
            ->groupBy('voting_id');

        $viewsByVoting = VotingViews::whereIn('voting_id', $ids)
            ->get()
            ->groupBy('voting_id')
            ->map(fn($g) => $g->count());

        $result = $rows->map(function ($v) use ($reactionsByVoting, $viewsByVoting) {
            $reactions = $reactionsByVoting->get($v->_id, collect());
            $byType = $reactions->groupBy('type')->map(fn($g) => $g->count());

            $options = is_array($v->options) ? $v->options : [];
            $optionsOut = array_map(function ($opt, $idx) use ($byType) {
                $type = $opt['type'] ?? ($idx + 1);
                return [
                    'title' => $opt['title'] ?? "Option " . ($idx + 1),
                    'type'  => (int) $type,
                    'image' => $opt['image'] ?? null,
                    'count' => (int) ($byType->get((string) $type, $byType->get($type, 0))),
                ];
            }, $options, array_keys($options));

            return [
                'id'           => $v->_id,
                'name'         => $v->name ?? '',
                'description'  => $v->description ?? '',
                'banner'       => $v->banner ?? $v->image ?? '',
                'viewBanner'   => $v->view_banner ?? null,
                'audio'        => $v->audio ?? null,
                'status'       => (string) ($v->status ?? '0'),
                'voteType'     => $v->vote_type ?? 'single',
                'options'      => $optionsOut,
                'totalVotes'   => $reactions->count(),
                'totalViews'   => (int) ($viewsByVoting->get($v->_id, 0)),
                'createdAt'    => $v->created_at ? Carbon::parse($v->created_at)->toIso8601String() : null,
            ];
        })->values()->toArray();

        return ResponseHelper::sendResponse($result, 'Votings loaded.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'status'  => 'required|in:0,1',
            'banner'  => 'required|string',
        ]);

        $v = new Voting();
        $this->fillVoting($v, $request);
        $v->save();

        // Notify opted-in users about a newly published survey (config-driven Portal Notification).
        if ((string) $request->status === '1') {
            NotificationHelper::sendConfiguredBroadcast('new_votes', ['[name]' => (string) $request->name], 'votes');
        }

        return ResponseHelper::sendResponse(['id' => $v->_id], 'Survey created.', true, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $v = Voting::find($id);
        if (!$v) {
            return ResponseHelper::sendResponse(null, 'Survey not found', false, 404);
        }
        $this->fillVoting($v, $request);
        $v->save();

        return ResponseHelper::sendResponse(['id' => $v->_id], 'Survey updated.');
    }

    public function destroy($id)
    {
        $v = Voting::find($id);
        if (!$v) {
            return ResponseHelper::sendResponse(null, 'Survey not found', false, 404);
        }

        $bunny = new BunnyCDNService();
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

        foreach (['banner', 'view_banner', 'audio'] as $field) {
            if (!empty($v->{$field})) {
                $bunny->delete($this->cdnPath((string) $v->{$field}, $cdnBase));
            }
        }
        if (is_array($v->options)) {
            foreach ($v->options as $opt) {
                if (!empty($opt['image'])) {
                    $bunny->delete($this->cdnPath((string) $opt['image'], $cdnBase));
                }
            }
        }

        VotingReaction::where('voting_id', $v->_id)->delete();
        VotingViews::where('voting_id', $v->_id)->delete();
        $v->delete();

        return ResponseHelper::sendResponse(['id' => $id], 'Survey deleted.');
    }

    public function statistics($id)
    {
        $v = Voting::find($id);
        if (!$v) {
            return ResponseHelper::sendResponse(null, 'Survey not found', false, 404);
        }

        $reactions = VotingReaction::where('voting_id', $id)->get();
        $userIds = $reactions->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $options = is_array($v->options) ? $v->options : [];

        // Per-option counts
        $byType = $reactions->groupBy('type')->map(fn($g) => $g->count());
        $optionCounts = array_map(function ($opt, $idx) use ($byType) {
            $type = $opt['type'] ?? ($idx + 1);
            return [
                'title' => $opt['title'] ?? 'Option ' . ($idx + 1),
                'type'  => (int) $type,
                'image' => $opt['image'] ?? null,
                'count' => (int) ($byType->get((string) $type, $byType->get($type, 0))),
            ];
        }, $options, array_keys($options));

        // Per-gender breakdown
        $genderBreakdown = ['male' => [], 'female' => []];
        foreach (['male', 'female'] as $g) {
            $genderBreakdown[$g] = array_map(fn() => 0, $optionCounts);
        }

        // Age ranges
        $ageRanges = [
            ['key' => '18-24', 'min' => 18, 'max' => 24],
            ['key' => '25-30', 'min' => 25, 'max' => 30],
            ['key' => '31-35', 'min' => 31, 'max' => 35],
            ['key' => '36-40', 'min' => 36, 'max' => 40],
        ];
        $ageBreakdown = [];
        foreach ($ageRanges as $r) {
            $ageBreakdown[$r['key']] = ['male' => 0, 'female' => 0];
        }

        // Province breakdown
        $provinceCounts = [];

        foreach ($reactions as $r) {
            $u = $users->get($r->user_id);
            if (!$u) continue;

            $gender = strtolower((string) ($u->gender ?? ''));
            if ($gender === 'male' || $gender === 'female') {
                $typeStr = (string) $r->type;
                $optIdx = array_search((int) $typeStr, array_column($optionCounts, 'type'));
                if ($optIdx !== false) {
                    $genderBreakdown[$gender][$optIdx]++;
                }
            }

            if (!empty($u->dob)) {
                try {
                    $age = Carbon::parse($u->dob)->age;
                    foreach ($ageRanges as $range) {
                        if ($age >= $range['min'] && $age <= $range['max']) {
                            if ($gender === 'male' || $gender === 'female') {
                                $ageBreakdown[$range['key']][$gender]++;
                            }
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore bad dob
                }
            }

            $province = $u->province ?? null;
            if ($province) {
                $key = (string) $province;
                $provinceCounts[$key] = ($provinceCounts[$key] ?? 0) + 1;
            }
        }

        $provincesOut = [];
        foreach ($provinceCounts as $name => $count) {
            $provincesOut[] = ['name' => $name, 'votes' => $count];
        }
        usort($provincesOut, fn($a, $b) => $b['votes'] <=> $a['votes']);

        return ResponseHelper::sendResponse([
            'total'   => $reactions->count(),
            'options' => $optionCounts,
            'genders' => [
                'male'   => $genderBreakdown['male'],
                'female' => $genderBreakdown['female'],
            ],
            'ages'      => $ageBreakdown,
            'provinces' => $provincesOut,
        ], 'Statistics fetched.');
    }

    private function fillVoting(Voting $v, Request $request): void
    {
        $v->name        = $request->input('name');
        $v->description = $request->input('description', '');
        $v->status      = (string) $request->input('status');
        $v->vote_type   = $request->input('vote_type', 'single');

        if ($request->has('banner'))      $v->banner = $request->input('banner');
        if ($request->has('view_banner')) $v->view_banner = $request->input('view_banner');
        if ($request->has('audio'))       $v->audio = $request->input('audio');

        // options: array of {title, type, image}
        if ($request->has('options')) {
            $opts = $request->input('options');
            if (is_array($opts)) {
                $v->options = array_values(array_map(function ($o, $idx) {
                    return [
                        'title' => (string) ($o['title'] ?? "Option " . ($idx + 1)),
                        'type'  => (int) ($o['type'] ?? ($idx + 1)),
                        'image' => $o['image'] ?? null,
                    ];
                }, $opts, array_keys($opts)));
            }
        }
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }
}

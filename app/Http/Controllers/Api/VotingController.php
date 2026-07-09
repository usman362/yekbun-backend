<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voting;
use App\Models\VotingReaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VotingController extends Controller
{
    public function index()
    {
        $votings = Voting::where('status', '1')->with('reactions')->get();
        return ResponseHelper::sendResponse($votings, 'Votings Fetch Successfully!');
    }

    public function waitingVote()
    {
        $userId = Auth::id();
        $votings = Voting::where('status', '1')
            ->where(function ($q) use ($userId) {
                $q->whereHas('reactions', fn($r) => $r->where('user_id', '!=', $userId))
                    ->orWhereDoesntHave('reactions');
            })->with('reactions')->get();
        return ResponseHelper::sendResponse($votings, 'Votings Fetch Successfully!');
    }

    public function mostViews()
    {
        $votings = Voting::where('status', '1')->with('reactions')->orderBy('views', 'desc')->get();
        return ResponseHelper::sendResponse($votings, 'Votings Fetch Successfully!');
    }

    public function alreadyVoted()
    {
        $votings = Voting::where('status', '1')->whereHas('reactions', fn($r) => $r->where('user_id', Auth::id()))->with('reactions')->get();
        return ResponseHelper::sendResponse($votings, 'Votings Fetch Successfully!');
    }

    public function latestVotes()
    {
        // Return a collection (always an array) for consistency with previousVotes / alreadyVoted /
        // mostViews / waitingVote. Empty case => `data: []`, single-record case => `data: [{...}]`.
        // Earlier this used `->first()` (object) and returned `[]` on empty, which caused mobile
        // clients to crash because they wrapped single objects in an array and ended up with `[[]]`.
        $votings = Voting::where('status', '1')
            ->with('reactions')
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->get();

        return ResponseHelper::sendResponse($votings, 'Votings Fetch Successfully!');
    }

    public function previousVotes()
    {
        $userId = Auth::id();
        $voting = Voting::where('status', '1')->with('reactions')->orderBy('created_at', 'desc')->first();

        $query = Voting::where('status', '1')
            ->where(function ($q) use ($userId) {
                $q->whereHas('reactions', fn($r) => $r->where('user_id', '!=', $userId))
                    ->orWhereDoesntHave('reactions');
            })->with('reactions')->orderBy('created_at', 'desc');

        if ($voting) $query->where('_id', '!=', $voting->_id);

        return ResponseHelper::sendResponse($query->get(), 'Votings Fetch Successfully!');
    }

    public function votingPublic()
    {
        $votings = Voting::where('status', '1')->with('reactions')->get();
        return ResponseHelper::sendResponse($votings, 'Votings Fetch Successfully!');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'image' => 'required', 'category_id' => 'required', 'description' => 'required']);

        $imagePath = $request->audio;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('/images/voting/', 'public');
        }
        $voting = Voting::create([
            'name' => $request->name, 'image' => $imagePath,
            'category_id' => $request->category_id, 'description' => $request->description,
        ]);
        return response()->json(["success" => true, "message" => "Voting successfully created.", "data" => $voting], 200);
    }

    public function show($id)
    {
        $votings = Voting::where('status', '1')->with('reactions')->find($id);
        if (!$votings) return ResponseHelper::sendResponse([], 'Voting Fetch Successfully!');
        return ResponseHelper::sendResponse($votings, 'Voting Fetch Successfully!');
    }

    public function update(Request $request, $id)
    {
        $vote = Voting::find($id);
        $vote->name = $request->name ?? $vote->name;
        $vote->category_id = $request->category_id ?? $vote->category_id;
        $vote->description = $request->description ?? $vote->description;
        if ($request->hasFile('image')) {
            if (isset($vote->banner)) {
                $image_path = public_path('storage/' . $vote->banner);
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
                $vote->banner = $request->file('image')->store('/images/voting', 'public');
            }
        }
        if ($vote->update()) return response()->json('Voting Updated Successfully', 200);
        return response()->json('Failed to updated voting', 400);
    }

    public function destroy($id)
    {
        $vote = Voting::find($id);
        if ($vote->banner) {
            $image_path = public_path('storage/' . $vote->banner);
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        if ($vote->delete()) return response()->json('Voting Deleted Successfully', 200);
        return response()->json('Failed to delete vote', 400);
    }

    public function get_cover($id = null)
    {
        $voting = Voting::select('id', 'name', 'category_id', 'description', 'status')->with('gallery')->orderBy('created_at', 'desc')->take(1)->get();
        if (sizeof($voting) > 0) {
            $voting_reaction = VotingReaction::where('user_id', $id)->where('vote_id', $voting[0]->id)->first();
            $voting[0]->user_reaction = $voting_reaction;
        }
        return response()->json(['success' => true, 'data' => $voting]);
    }

    public function fetch($id = null)
    {
        $voting = Voting::select('id', 'name', 'category_id', 'description', 'status')->orderBy('created_at', 'desc')->take(5)->with(['voting_category', 'gallery'])->get();
        foreach ($voting as $item) {
            $item->user_reaction = VotingReaction::where('user_id', $id)->where('vote_id', $item->id)->first();
        }
        return response()->json(['success' => true, 'data' => $voting]);
    }

    public function fetch_all($id = null)
    {
        $voting = Voting::select('id', 'description', 'name', 'category_id', 'status')->orderBy('created_at', 'desc')->with(['voting_category', 'gallery'])->get();
        foreach ($voting as $item) {
            $item->user_reaction = VotingReaction::where('user_id', $id)->where('vote_id', $item->id)->first();
        }
        return response()->json(['success' => true, 'data' => $voting]);
    }

    public function get_details($id, $user_id = null)
    {
        $voting = Voting::with(['voting_category', 'gallery'])->find($id);
        if ($voting) {
            $voting->user_reaction = VotingReaction::where('user_id', $user_id)->where('vote_id', $voting->id)->first();
        }
        return response()->json(['success' => true, 'data' => $voting]);
    }

    public function store_reaction(Request $request)
    {
        $credentials = $request->validate(['user_id' => 'required', 'vote_id' => 'required', 'type' => 'required']);

        $existing = VotingReaction::where('user_id', $request->user_id)->where('vote_id', $request->vote_id)->first();
        if ($existing) {
            if ($existing->type == $request->type) {
                $existing->delete();
                return response()->json(['success' => true, 'message' => 'Vote removed.']);
            }
            $existing->type = $request->type;
            $existing->save();
            return response()->json(['success' => true, 'message' => 'Vote updated.']);
        }
        VotingReaction::create($credentials);
        return response()->json(['success' => true, 'message' => 'Vote saved.']);
    }

    public function get_statistics($id)
    {
        $vote = Voting::select('options')->find($id);
        if (empty($vote)) return response()->json(['message' => 'Vote Not Found!', 'success' => false], 404);

        $ageGroups = ['18-24' => [18, 24], '25-30' => [25, 30], '31-35' => [31, 35], '36-40' => [36, 40]];
        $statistics = [];

        foreach ($ageGroups as $ageRange => [$minAge, $maxAge]) {
            $users = User::all()->filter(function ($user) use ($minAge, $maxAge) {
                if (!isset($user->dob)) return false;
                $age = Carbon::parse($user->dob)->age;
                return $age >= $minAge && $age <= $maxAge;
            });

            $userIds = $users->pluck('_id')->map(fn($id) => (string)$id)->toArray();
            $reactions = VotingReaction::whereIn('user_id', $userIds)->where('voting_id', $id)->get();

            $maleStats = ['reviews' => 0, 'likes' => 0, 'neutrals' => 0, 'dislikes' => 0];
            $femaleStats = ['reviews' => 0, 'likes' => 0, 'neutrals' => 0, 'dislikes' => 0];

            foreach ($reactions as $reaction) {
                $user = $users->where('_id', $reaction->user_id)->first();
                $gender = $user->gender ?? 'male';
                $stats = &${$gender . 'Stats'};
                if ($reaction->type == 1) $stats['likes']++;
                elseif ($reaction->type == 2) $stats['neutrals']++;
                elseif ($reaction->type == 3) $stats['dislikes']++;
                $stats['reviews']++;
            }

            $max = max($maleStats['reviews'], $femaleStats['reviews'], 1);
            $statistics[] = ['age' => $ageRange, 'max' => $max, 'male' => $maleStats, 'female' => $femaleStats];
        }

        $total_reviews = $total_likes = $total_dislikes = $total_neutrals = 0;
        foreach ($statistics as $stat) {
            $total_reviews += $stat['male']['reviews'] + $stat['female']['reviews'];
            $total_likes += $stat['male']['likes'] + $stat['female']['likes'];
            $total_dislikes += $stat['male']['dislikes'] + $stat['female']['dislikes'];
            $total_neutrals += $stat['male']['neutrals'] + $stat['female']['neutrals'];
        }

        $users = User::where('origin', 'kurdish')->select('_id', 'province')->get();
        $usersByProvince = $users->groupBy('province');
        $province_statistics = [];

        foreach ($usersByProvince as $province => $provinceUsers) {
            $userIds = $provinceUsers->pluck('_id')->toArray();
            $province_statistics[] = [
                'province' => $province,
                'total_votes' => VotingReaction::whereIn('user_id', $userIds)->where('voting_id', $id)->count()
            ];
        }

        // Include voters list by default (who already voted) — mobile expects this without
        // needing an extra query param. Keep it bounded to avoid heavy payloads.
        $limit = min((int) request()->get('limit', 50), 200);
        $offset = max((int) request()->get('offset', 0), 0);

        $voters_total = VotingReaction::where('voting_id', (string) $id)->count();
        $voterIds = VotingReaction::where('voting_id', (string) $id)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->pluck('user_id')
            ->map(fn($uid) => (string) $uid)
            ->unique()
            ->values()
            ->toArray();

        $voters = [];
        if (!empty($voterIds)) {
            $users = User::whereIn('_id', $voterIds)->get()->keyBy('_id');
            $voters = collect($voterIds)->map(function ($uid) use ($users) {
                $u = $users->get($uid);
                if (!$u) return null;
                return [
                    '_id'      => (string) $u->_id,
                    'username' => $u->username ?? null,
                    'name'     => $u->name ?? null,
                    'image'    => Helpers::mediaUrl($u->image ?? null),
                    'gender'   => $u->gender ?? null,
                    'province' => $u->province ?? null,
                ];
            })->filter()->values()->toArray();
        }

        return ResponseHelper::sendResponse([
            'vote' => $vote, 'statistics' => $statistics, 'province_statistics' => $province_statistics,
            'totals' => ['reviews' => $total_reviews, 'likes' => $total_likes, 'neutrals' => $total_neutrals, 'dislikes' => $total_dislikes],
            'voters_total' => $voters_total,
            'voters' => $voters,
        ], 'Voting Statistics!');
    }

    public function get_province_statistics($id)
    {
        $vote = Voting::select('options')->find($id);
        if (empty($vote)) return response()->json(['message' => 'Vote Not Found!', 'success' => false], 404);

        $users = User::where('origin', 'kurdish')->select('_id', 'province')->get();
        $usersByProvince = $users->groupBy('province');
        $statistics = [];

        foreach ($usersByProvince as $province => $provinceUsers) {
            $userIds = $provinceUsers->pluck('_id')->toArray();
            $statistics[] = [
                'province' => $province,
                'total_votes' => VotingReaction::whereIn('user_id', $userIds)->where('voting_id', $id)->count()
            ];
        }

        return ResponseHelper::sendResponse(['vote' => $vote, 'statistics' => $statistics], 'Voting Statistics by Province!');
    }
}

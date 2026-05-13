<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\PopFeeds;
use App\Models\SosPopup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminActivityController extends Controller
{
    public function getSystemInfo()
    {
        $popfeeds = PopFeeds::where('type', 'System')->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($popfeeds, 'System Feeds');
    }

    public function getDonations()
    {
        $popfeeds = PopFeeds::where('type', 'Donation')->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($popfeeds, 'Donation Feeds');
    }

    public function getSurveys()
    {
        $popfeeds = PopFeeds::with('user')->where('type', 'Surveys')->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($popfeeds, 'Survey Feeds');
    }

    public function getGreetings()
    {
        $popfeeds = PopFeeds::with('user')->where('type', 'Greetings')->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($popfeeds, 'Greetings Feeds');
    }

    public function getpopFeeds(Request $request)
    {
        $types = ['System', 'Donation', 'Surveys', 'Greetings', 'Event', 'SOS'];
        $userProvince = auth()->user()->province;
        if ($userProvince == 'Bakûr') $userProvince = 'Bakur';
        if ($userProvince == 'Başûr') $userProvince = 'Basur';

        $feeds = PopFeeds::with(['user', 'sosPopups'])
            ->whereIn('share_option', ['all-users', Auth::user()->user_type])
            ->where(function ($q) use ($userProvince) {
                $q->whereNull('allowed_provinces')
                    ->orWhere('allowed_provinces', $userProvince);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('type');

        $data = [];
        foreach ($types as $type) {
            $data[$type] = $feeds->get($type, collect());
        }

        return ResponseHelper::sendResponse($data, 'All Admin Activity Feeds');
    }

    public function deactivateSOS($id)
    {
        $popup = SosPopup::where('user_id', Auth::id())->where('sos_id', $id)->first();
        if ($popup) $popup->delete();
        return ResponseHelper::sendResponse([], 'Popup Deactivated Successfully!');
    }

    public function getpublicpopFeeds(Request $request)
    {
        $popfeeds = PopFeeds::with('user')->where('share_option', 'all-users')->orderBy('created_at', 'desc')->first();
        return ResponseHelper::sendResponse($popfeeds, 'Public Admin Activity Feed');
    }

    public function store_systemInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'title' => $request->title, 'date_start' => $request->start_date, 'date_ends' => $request->end_date,
            'share_option' => $request->option ?? 'all-users', 'is_comments' => $request->comments ?? 0,
            'is_share' => $request->share ?? 0, 'is_emoji' => $request->emoji ?? 0,
            'txt1' => $request->txt1, 'txt2' => $request->txt2, 'txt3' => $request->txt3, 'type' => 'System',
        ];

        foreach (['image' => 'images', 'icon1' => 'images/icons', 'icon2' => 'images/icons', 'icon3' => 'images/icons', 'audio' => 'audio'] as $fileKey => $path) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $data[$fileKey] = $file->storeAs("/{$path}", time() . rand() . '-' . $fileKey . '.' . $file->getClientOriginalExtension(), 'public');
            }
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'Popup Feed updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'Popup Feed not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'Popup Feed added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function store_donation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255', 'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'limited' => $request->limit, 'is_paypal' => $request->is_paypal ?? 0,
            'is_gpay' => $request->is_gpay ?? 0, 'is_pay_office' => $request->is_payoffice ?? 0,
            'is_pay_other' => $request->is_other ?? 0, 'title' => $request->title,
            'date_start' => $request->start_date, 'date_ends' => $request->end_date,
            'share_option' => $request->option ?? 'all-users', 'is_comments' => $request->comments ?? 0,
            'is_share' => $request->share ?? 0, 'is_emoji' => $request->emoji ?? 0, 'type' => 'Donation',
        ];

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $data['image'] = $image->storeAs('/images', time() . '-post.' . $image->getClientOriginalExtension(), 'public');
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'Donation updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'Donation not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'Donation added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function store_surveys(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255', 'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'title' => $request->title, 'date_start' => $request->start_date, 'date_ends' => $request->end_date,
            'share_option' => $request->option ?? 'all-users', 'is_comments' => $request->comments ?? 0,
            'is_share' => $request->share ?? 0, 'is_emoji' => $request->emoji ?? 0,
            'txt1' => $request->txt1, 'txt2' => $request->txt2, 'txt3' => $request->txt3, 'type' => 'Surveys',
        ];

        foreach (['image' => 'images', 'icon1' => 'images/icons', 'icon2' => 'images/icons', 'icon3' => 'images/icons'] as $fileKey => $path) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $data[$fileKey] = $file->storeAs("/{$path}", time() . rand() . '-' . $fileKey . '.' . $file->getClientOriginalExtension(), 'public');
            }
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'Survey updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'Survey not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'Survey added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function store_greetings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255', 'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'title' => $request->title, 'date_start' => $request->start_date, 'date_ends' => $request->end_date,
            'share_option' => $request->option ?? 'all-users', 'is_comments' => $request->comments ?? 0,
            'is_share' => $request->share ?? 0, 'is_emoji' => $request->emoji ?? 0,
            'txt1' => $request->txt1, 'txt2' => $request->txt2, 'txt3' => $request->txt3, 'type' => 'Greetings',
        ];

        foreach (['image' => 'images', 'icon1' => 'images/icons', 'icon2' => 'images/icons', 'icon3' => 'images/icons', 'audio' => 'audio'] as $fileKey => $path) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $data[$fileKey] = $file->storeAs("/{$path}", time() . rand() . '-' . $fileKey . '.' . $file->getClientOriginalExtension(), 'public');
            }
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'Greeting updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'Greeting not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'Greeting added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function store_userSos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'title' => $request->title, 'date_start' => $request->start_date, 'date_ends' => $request->end_date,
            'share_option' => $request->option ?? 'all-users', 'is_comments' => $request->comments ?? 0,
            'is_share' => $request->share ?? 0, 'is_emoji' => $request->emoji ?? 0, 'type' => 'SOS',
        ];

        foreach (['image' => 'images', 'audio' => 'audio'] as $fileKey => $path) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $data[$fileKey] = $file->storeAs("/{$path}", time() . rand() . '-' . $fileKey . '.' . $file->getClientOriginalExtension(), 'public');
            }
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'SOS updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'SOS not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'SOS added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function store_goLive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'title' => $request->title,
            'date_start' => $request->start_date,
            'date_ends' => $request->end_date,
            'share_option' => $request->option ?? $request->user_type ?? 'all-users',
            'is_comments' => $request->comments ?? 1,
            'type' => 'Event',
            'txt1' => $request->description,
            'txt2' => $request->start_time,
            'txt3' => $request->end_time,
        ];

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $data['image'] = $image->storeAs('/images', time() . rand() . '-golive.' . $image->getClientOriginalExtension(), 'public');
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'Event updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'Event not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'Event added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function store_agentFeed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = [
            'title' => $request->title, 'date_start' => $request->start_date, 'date_ends' => $request->end_date,
            'share_option' => $request->option ?? 'all-users', 'is_comments' => $request->comments ?? 0,
            'is_share' => $request->share ?? 0, 'is_emoji' => $request->emoji ?? 0,
            'txt1' => $request->txt1, 'txt2' => $request->txt2, 'txt3' => $request->txt3, 'type' => 'AgentFeed',
        ];

        foreach (['image' => 'images', 'icon1' => 'images/icons', 'icon2' => 'images/icons', 'icon3' => 'images/icons', 'audio' => 'audio'] as $fileKey => $path) {
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $data[$fileKey] = $file->storeAs("/{$path}", time() . rand() . '-' . $fileKey . '.' . $file->getClientOriginalExtension(), 'public');
            }
        }

        if ($request->id > 0) {
            $postpop = PopFeeds::find($request->id);
            if ($postpop) { $postpop->update($data); return response()->json(['message' => 'Agent Feed updated successfully.', 'data' => $postpop], 200); }
            return response()->json(['message' => 'Agent Feed not found.'], 404);
        }
        $data['user_id'] = 0; $data['status'] = 1;
        return response()->json(['message' => 'Agent Feed added successfully.', 'data' => PopFeeds::create($data)], 201);
    }

    public function delete_pops(Request $request)
    {
        $popfeed = PopFeeds::where('_id', $request->id)->first();
        if ($popfeed) { $popfeed->delete(); return response()->json(['message' => 'Popup Feed deleted successfully.'], 200); }
        return response()->json(['message' => 'Popup Feed Not Found!'], 404);
    }

    public function destroyById(string $id)
    {
        $popfeed = PopFeeds::where('_id', $id)->first();
        if (!$popfeed) {
            return ResponseHelper::sendResponse(null, 'Popup Feed Not Found!', false, 404);
        }
        $popfeed->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Popup Feed deleted successfully.');
    }
}

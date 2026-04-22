<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\UserSuggestionService;

class UserSuggestionController extends Controller
{
    protected $suggestionService;

    public function __construct(UserSuggestionService $suggestionService)
    {
        $this->suggestionService = $suggestionService;
    }

    public function index()
    {
        $currentUser = Auth::user();

        if (!$currentUser) {
            return ResponseHelper::sendResponse([], 'Unauthorized', false, 401);
        }

        $suggestions = $this->suggestionService->getSuggestions($currentUser, 10);

        return response()->json([
            'status' => true,
            'suggestions' => $suggestions
        ]);
    }
}

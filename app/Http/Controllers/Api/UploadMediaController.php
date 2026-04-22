<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\UploadMedia;

class UploadMediaController extends Controller
{
    use UploadMedia;

    public function index(Request $request)
    {
        $request->validate([
            'file' => 'required'
        ]);

        $path = UploadMedia::index($request->file('file'));

        if ($path == "")
            return response()->json(['success' => false, 'message' => 'Something went wrong.']);

        return response()->json(['success' => true, 'data' => $path, 'message' => 'File successfully uploaded.']);
    }
}

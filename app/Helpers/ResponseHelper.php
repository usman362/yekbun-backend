<?php

namespace App\Helpers;

class ResponseHelper
{
    public static function sendResponse($data = null, $message = '', $success = true, $statusCode = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }
}

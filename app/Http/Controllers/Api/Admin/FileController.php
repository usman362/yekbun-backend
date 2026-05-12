<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Services\BunnyCDNService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        if (!$request->hasFile('file')) {
            return ResponseHelper::sendResponse(null, 'file required', false, 400);
        }

        $f = $request->file('file');
        $ext = strtolower($f->getClientOriginalExtension());

        $videoExt = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $audioExt = ['mp3', 'wav', 'aac', 'm4a', 'flac'];
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

        $base = in_array($ext, $videoExt) ? 'videos'
            : (in_array($ext, $audioExt) ? 'audios'
            : (in_array($ext, $imageExt) ? 'images' : 'files'));

        $sub = trim((string) $request->input('folder', ''), '/');
        $folder = $sub ? "$base/$sub" : $base;

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', $f->getClientOriginalName());
        $filename = Str::lower(Str::random(8)) . '_' . $safeOriginal;

        $bunny = new BunnyCDNService();
        $cdnUrl = $bunny->upload(
            $folder,
            $filename,
            file_get_contents($f->getRealPath()),
            $f->getMimeType()
        );

        $duration = 0;
        if (in_array($ext, $videoExt) || in_array($ext, $audioExt)) {
            $duration = $this->probeDuration($f->getRealPath());
        }

        $sizeMB = round($f->getSize() / (1024 * 1024), 2);

        return ResponseHelper::sendResponse([
            'path'     => $cdnUrl,
            'size'     => $sizeMB . ' MB',
            'duration' => $duration,
        ], 'Uploaded');
    }

    private function probeDuration(string $absPath): int
    {
        $ffprobe = trim((string) @shell_exec('which ffprobe'));
        if ($ffprobe === '') {
            return 0;
        }
        $cmd = $ffprobe . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($absPath);
        $out = @shell_exec($cmd);
        return (int) round((float) trim((string) $out));
    }
}

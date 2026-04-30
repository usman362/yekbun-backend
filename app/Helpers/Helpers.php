<?php

namespace App\Helpers;

use App\Models\Media;
use App\Services\BunnyCDNService;
use Exception;
use Illuminate\Support\Str;

class Helpers
{
    public static function fileUpload($uploadedFile, $folder = null)
    {
        $uniqueName = $uploadedFile->getClientOriginalName();
        $folder = $folder ?? 'files';
        $filePath = $uploadedFile->storeAs("/{$folder}", $uniqueName, "public");
        return $filePath;
    }

    public static function fileCDNUpload($uploadedFile, $folder = 'files')
    {
        $bunny = new BunnyCDNService();
        $folder = trim($folder, '/');

        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $tempLocalPath = storage_path('app/uploads/' . uniqid() . '.' . $ext);
        $uploadedFile->move(dirname($tempLocalPath), basename($tempLocalPath));

        $finalLocalFile = $tempLocalPath;

        if ($ext === 'mp3') {
            $convertedPath = str_replace('.mp3', '.m4a', $tempLocalPath);
            if (static::convertToM4A($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
            }
        } elseif ($ext === 'mp4') {
            $convertedPath = str_replace('.mp4', '_h265.mp4', $tempLocalPath);
            if (static::convertToH265($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
            }
        }

        $uniqueName = $uploadedFile->getClientOriginalName();

        $content = file_get_contents($finalLocalFile);
        $mime    = mime_content_type($finalLocalFile);

        $cdnPath = $bunny->upload(
            $folder,
            $uniqueName,
            $content,
            $mime
        );

        if (file_exists($finalLocalFile)) {
            unlink($finalLocalFile);
        }

        $cleanedcdnPath = Str::after($cdnPath, env('BUNNY_CDN_URL'));
        return $cleanedcdnPath;
    }

    public static function fileCDNUpload2($uploadedFile, $folder = 'files')
    {
        $bunny = new BunnyCDNService();
        $folder = trim($folder, '/');

        $ext = strtolower($uploadedFile->extension());
        $tempLocalPath = storage_path('app/uploads/' . uniqid() . '.' . $ext);

        $uploadedFile->move(dirname($tempLocalPath), basename($tempLocalPath));

        $finalLocalFile = $tempLocalPath;

        if ($ext === 'mp3') {
            $convertedPath = str_replace('.mp3', '.m4a', $tempLocalPath);
            if (static::convertToM4A($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
            }
        } elseif ($ext === 'mp4') {
            $convertedPath = str_replace('.mp4', '_h265.mp4', $tempLocalPath);
            if (static::convertToH265($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
            }
        }

        $uniqueName = basename($uploadedFile);

        $content = file_get_contents($finalLocalFile);
        $mime    = mime_content_type($finalLocalFile);

        $cdnPath = $bunny->upload(
            $folder,
            $uniqueName,
            $content,
            $mime
        );

        if (file_exists($finalLocalFile)) {
            unlink($finalLocalFile);
        }

        $cleanedcdnPath = Str::after($cdnPath, env('BUNNY_CDN_URL'));
        return $cleanedcdnPath;
    }

    public static function formatDuration($durationInSeconds)
    {
        $seconds = (int) round($durationInSeconds);
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf("%02d:%02d", $minutes, $remainingSeconds);
    }

    public static function array_in($needle, $haystack)
    {
        return is_array($haystack) && in_array($needle, $haystack);
    }

    public static function convertToM4A($inputPath, $outputPath)
    {
        $cmd = "ffmpeg -i \"$inputPath\" -vn -c:a aac -b:a 64k -movflags +faststart \"$outputPath\" -y";
        exec($cmd, $output, $returnCode);
        return $returnCode === 0;
    }

    public static function convertToH265($inputPath, $outputPath)
    {
        $cmd = "ffmpeg -y -i {$inputPath} \
        -map 0:v:0 -map 0:a? \
        -c:v libx264 -pix_fmt yuv420p -crf 32 -preset fast -movflags +faststart \
        -c:a aac -b:a 64k -ar 44100 -ac 2 \
        -f mp4 {$outputPath} 2>&1";
        exec($cmd, $output, $returnCode);
        return $returnCode === 0;
    }

    public static function userMedia(
        $media_id,
        $uri,
        $commentCount,
        $voiceCount,
        $emojisCount,
        $seenCount,
        $user_id,
        $text,
        $text_properties,
        $type
    ) {
        $media = Media::where('media_id', $media_id)->first();
        if (!$media) {
            $media = new Media();
        }
        $media->media_id = $media_id;
        $media->uri = $uri == 'exists' ? $media->uri : $uri;
        $media->commentCount = $commentCount;
        $media->voiceCount = $voiceCount;
        $media->emojisCount = $emojisCount;
        $media->seenCount = $seenCount;
        $media->user_id = $user_id;
        $media->text = $text;
        $media->text_properties = $text_properties;
        $media->type = $type;
        $media->save();
    }
}

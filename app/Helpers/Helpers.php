<?php

namespace App\Helpers;

use App\Models\Media;
use App\Services\BunnyCDNService;
use Exception;
use Illuminate\Support\Str;

class Helpers
{
    /**
     * Build a fully-qualified URL for a stored media path.
     *
     * Rules:
     * - empty / null → null
     * - already absolute (http/https) → return as-is (encoded path)
     * - else prefix BUNNY CDN base (config-cached safe)
     * - never return a bare relative path
     */
    public static function mediaUrl($path)
    {
        if ($path === null || $path === '') {
            return null;
        }
        // Arrays occasionally show up from legacy gallery-style fields.
        if (is_array($path)) {
            $path = $path['path'] ?? $path['url'] ?? reset($path) ?: null;
            if ($path === null || $path === '') {
                return null;
            }
        }
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return preg_replace_callback('#^(https?://[^/]+)(/.*)?$#', function ($m) {
                if (empty($m[2])) {
                    return $m[1];
                }
                $segments = array_map('rawurlencode', explode('/', ltrim($m[2], '/')));
                return $m[1] . '/' . implode('/', $segments);
            }, $path);
        }

        $relative = ltrim($path, '/');
        // Strip accidental "storage/" prefix from older local uploads.
        if (Str::startsWith($relative, 'storage/')) {
            $relative = Str::after($relative, 'storage/');
        }
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));

        $cdn = rtrim((string) (config('services.bunny.cdn_url') ?: env('BUNNY_CDN_URL') ?: 'https://yekbun.b-cdn.net'), '/');
        if ($cdn !== '') {
            return $cdn . '/' . $encoded;
        }

        // Last resort: app storage URL (still absolute).
        $url = asset('storage/' . $encoded);
        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
        }
        return $url;
    }

    /**
     * Strip CDN / app base URL so only a relative storage path is returned / persisted.
     * Mobile clients prepend the CDN base themselves — do not put full URLs in API payloads.
     */
    public static function cdnRelativePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $candidates = array_values(array_filter([
            rtrim((string) (config('services.bunny.cdn_url') ?: env('BUNNY_CDN_URL') ?: ''), '/'),
            'https://yekbun.b-cdn.net',
            'http://yekbun.b-cdn.net',
            rtrim((string) config('app.url'), '/') . '/storage',
            rtrim((string) asset('storage'), '/'),
        ]));

        foreach ($candidates as $base) {
            if ($base === '') {
                continue;
            }
            if (Str::startsWith($path, $base . '/')) {
                return ltrim(Str::after($path, $base . '/'), '/');
            }
            if ($path === $base) {
                return '';
            }
        }

        // Any other b-cdn.net absolute URL → path after host
        if (preg_match('#^https?://[^/]*b-cdn\.net/(.+)$#i', $path, $m)) {
            return ltrim($m[1], '/');
        }

        // Already relative (or unknown absolute we leave alone for external assets)
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return ltrim($path, '/');
    }

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
        // Track whether the file was converted so we can rewrite the stored filename's
        // extension to match the actual codec — otherwise mobile clients see a `.mp3`
        // URL serving m4a/AAC content and refuse to trim it natively.
        $convertedExt = null;

        if ($ext === 'mp3') {
            $convertedPath = str_replace('.mp3', '.m4a', $tempLocalPath);
            if (static::convertToM4A($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
                $convertedExt = 'm4a';
            }
        } elseif ($ext === 'mp4') {
            $convertedPath = str_replace('.mp4', '_h265.mp4', $tempLocalPath);
            if (static::convertToH265($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
            }
        }

        // Prefix a unique token so uploads NEVER collide on the CDN. Mobile clients often send
        // every recording/image with the same client filename (e.g. "audio.m4a"), which would
        // otherwise overwrite each other — so all voice comments ended up pointing at one file.
        $uniqueName = uniqid() . '_' . $uploadedFile->getClientOriginalName();
        // Swap the extension on the stored filename when the content was transcoded.
        // This keeps the CDN URL truthful (e.g. `song.mp3` → `song.m4a`) so mobile can
        // pick the right native decoder/trimmer based on the filename alone.
        if ($convertedExt !== null) {
            $uniqueName = preg_replace('/\.[^.]+$/', '.' . $convertedExt, $uniqueName);
        }

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

        // BunnyCDNService::upload already returns a relative path — never persist a full CDN URL.
        return self::cdnRelativePath($cdnPath) ?: $cdnPath;
    }

    public static function fileCDNUpload2($uploadedFile, $folder = 'files')
    {
        $bunny = new BunnyCDNService();
        $folder = trim($folder, '/');

        $ext = strtolower($uploadedFile->extension());
        $tempLocalPath = storage_path('app/uploads/' . uniqid() . '.' . $ext);

        $uploadedFile->move(dirname($tempLocalPath), basename($tempLocalPath));

        $finalLocalFile = $tempLocalPath;
        $convertedExt = null;

        if ($ext === 'mp3') {
            $convertedPath = str_replace('.mp3', '.m4a', $tempLocalPath);
            if (static::convertToM4A($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
                $convertedExt = 'm4a';
            }
        } elseif ($ext === 'mp4') {
            $convertedPath = str_replace('.mp4', '_h265.mp4', $tempLocalPath);
            if (static::convertToH265($tempLocalPath, $convertedPath)) {
                unlink($tempLocalPath);
                $finalLocalFile = $convertedPath;
            }
        }

        $uniqueName = basename($uploadedFile);
        // Match the stored filename's extension to the transcoded codec so the CDN URL
        // tells mobile clients the truth about the content (see fileCDNUpload above).
        if ($convertedExt !== null) {
            $uniqueName = preg_replace('/\.[^.]+$/', '.' . $convertedExt, $uniqueName);
        }

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

        return self::cdnRelativePath($cdnPath) ?: $cdnPath;
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

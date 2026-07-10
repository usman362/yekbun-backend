<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BunnyCDNService
{
    protected $zone;
    protected $key;
    protected $region;
    protected $cdnUrl;

    public function __construct()
    {
        $this->cdnUrl = config('services.bunny.cdn_url') ?: env('BUNNY_CDN_URL');
        $this->zone = config('services.bunny.storage_zone') ?: env('BUNNY_STORAGE_ZONE');
        $this->key = config('services.bunny.storage_key') ?: env('BUNNY_STORAGE_KEY');
        $this->region = config('services.bunny.region') ?: env('BUNNY_REGION', 'de');
    }

    private function apiBase()
    {
        return "https://storage.bunnycdn.com/{$this->zone}";
    }

    private function headers()
    {
        return ['AccessKey' => $this->key];
    }

    public function folderExists($folder)
    {
        $folder = trim($folder, '/');
        $response = Http::withHeaders($this->headers())->get($this->apiBase() . '/' . $folder);
        return $response->successful();
    }

    public function createFolder($folder)
    {
        $folder = trim($folder, '/') . '/';
        $response = Http::withHeaders($this->headers())->put($this->apiBase() . '/' . $folder);
        if ($response->failed()) {
            throw new \Exception("Unable to create folder: " . $response->body());
        }
        return true;
    }

    public function upload($folder, $filename, $content, $contentType)
    {
        $path = trim($folder, '/') . '/' . $filename;

        $response = Http::withHeaders([
            'AccessKey' => $this->key,
            'Content-Type' => $contentType
        ])
            ->withBody($content, $contentType)
            ->put($this->apiBase() . '/' . $path);

        if ($response->failed()) {
            throw new \Exception("Upload failed: " . $response->body());
        }

        // Store and return only the relative path. Client apps may have different base URL
        // strategies (some prepend their own CDN base), so returning absolute URLs here
        // causes double-prefix bugs. Use Helpers::mediaUrl($path) when a full URL is needed.
        return $path;
    }

    public function delete($filePath)
    {
        $url = "https://storage.bunnycdn.com/{$this->zone}/{$filePath}";
        $response = Http::withHeaders(['AccessKey' => $this->key])->delete($url);
        return $response->successful();
    }

    public function url($filePath)
    {
        return $this->cdnUrl . '/' . $filePath;
    }
}

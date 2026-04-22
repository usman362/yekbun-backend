<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BackgroundFeed;
use App\Traits\UploadMedia;
use Illuminate\Support\Facades\Log;
use Imagick;

class FeedBackgroundImageController extends Controller
{
    use UploadMedia;

    public function upload(Request $request)
    {

        $feed = new BackgroundFeed();
        $feed->title = $request->title;

        if ($request->hasFile('image'))
            $path = UploadMedia::index($request->file('image'));

        $feed->image = $path ?? '';

        if ($feed->save()) {
            return response()->json(['success' => true, 'data' => $feed]);
        } else {
            return response()->json(['success' => false, 'data' => $feed]);
        }
    }

    public function get()
    {
        $feed = BackgroundFeed::get()->map(function ($item) {
            // if (pathinfo($item->image, PATHINFO_EXTENSION) === 'svg') {
            //     $item->image = $this->convertSvgToJpeg($item->image);
            // }
            return $item;
        });

        return response()->json(['success' => true, 'data' => $feed]);
    }

    /**
     * Convert an SVG image to a JPEG and return the new image path.
     */
    private function convertSvgToJpeg($svgPath)
    {
        $svgFullPath = public_path('storage/'.$svgPath);
        // dd($svgFullPath);
        if (!file_exists($svgFullPath)) {
            return asset('images/default.jpeg');
        }

        $jpegPath = str_replace('.svg', '.jpg', $svgPath);
        $jpegFullPath = public_path('storage/'.$jpegPath);

        try {
            $imagick = new Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($svgFullPath);
            $imagick->setImageFormat("jpeg");
            $imagick->writeImage($jpegFullPath);
            $imagick->clear();
            $imagick->destroy();

            return asset('storage/'.$jpegPath);
        } catch (\Exception $e) {
            Log::error("SVG to JPEG conversion failed: " . $e->getMessage());
            return asset('images/default.jpeg'); // Return fallback image on error
        }
    }
}

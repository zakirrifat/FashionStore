<?php

namespace App\Http\Controllers;

use App\Support\Img;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    /** Creates a resized WebP copy on first request, then serves it. */
    public function show(int $width, string $path): BinaryFileResponse
    {
        $resized = Img::make($path, $width);
        abort_if($resized === null, 404);

        return response()->file(Storage::disk('public')->path($resized), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}

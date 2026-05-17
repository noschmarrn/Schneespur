<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StorageFallbackController extends Controller
{
    public function __invoke(string $path): Response
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $lastModified = $disk->lastModified($path);

        return response($disk->get($path), 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=604800',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ]);
    }
}

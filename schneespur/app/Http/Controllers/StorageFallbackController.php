<?php

namespace App\Http\Controllers;

use App\Services\Storage\StorageBackendRegistry;
use Symfony\Component\HttpFoundation\Response;

class StorageFallbackController extends Controller
{
    public function __invoke(string $path, StorageBackendRegistry $registry): Response
    {
        $contents = $registry->retrieveWithFallback($path);

        if ($contents === null) {
            abort(404);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents) ?: 'application/octet-stream';

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}

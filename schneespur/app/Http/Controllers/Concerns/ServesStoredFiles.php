<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\Response;

/**
 * Turns stored bytes into a response that cannot execute in our own origin.
 *
 * Both controllers that hand out stored files derive the content type with
 * finfo, which reads the *bytes* — so the uploader, not the application, picks
 * that value. An SVG or an HTML file would be announced as such and rendered as
 * first-party code. Upload validation cannot be the only guard: modules store
 * arbitrary file types through the storage registry (M018/S04).
 */
trait ServesStoredFiles
{
    /** Types a browser may render inline. No SVG, no HTML — both carry script. */
    private const INLINE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'application/pdf',
    ];

    private function fileResponse(string $contents, string $path, string $cacheControl): Response
    {
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        $headers = [
            'Content-Type' => $detected,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (! in_array($detected, self::INLINE_TYPES, true)) {
            // Anything outside the list is downloaded, never displayed.
            $filename = str_replace(['"', "\r", "\n"], '', basename($path));

            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        }

        return response($contents, 200, $headers);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesStoredFiles;
use App\Services\Storage\MediaAccessRegistry;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    use ServesStoredFiles;

    public function __invoke(
        Request $request,
        string $path,
        MediaAccessRegistry $access,
        StorageBackendRegistry $storage,
    ): Response {
        if (str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            abort(403);
        }

        // hasValidSignature() is hasCorrectSignature() && signatureHasNotExpired().
        // Splitting it separates "your link aged out" from "you made this up":
        // the placeholder below admits that something exists under this path, so
        // only a holder of a once-valid signature may ever see it.
        if (! URL::hasCorrectSignature($request)) {
            abort(403);
        }

        if (! URL::signatureHasNotExpired($request)) {
            // Expected failure on a driver page served from the 24h
            // service-worker cache. Say so legibly instead of rendering a dead
            // image (M018/S04).
            return $this->expiredPlaceholder();
        }

        if (! $access->allows($path, $request->user() ?? $request->user('customer'))) {
            abort(403);
        }

        $contents = $storage->retrieveWithFallback($path);

        if ($contents === null) {
            abort(404);
        }

        // Private: a shared cache must never hold an authorised response.
        return $this->fileResponse($contents, $path, 'private, max-age=86400');
    }

    private function expiredPlaceholder(): Response
    {
        $label = e(__('media.link_expired'));

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180" role="img" aria-label="{$label}">
              <rect width="320" height="180" fill="#e5e7eb"/>
              <text x="160" y="94" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#374151">{$label}</text>
            </svg>
            SVG;

        return response($svg, 410, ['Content-Type' => 'image/svg+xml']);
    }
}

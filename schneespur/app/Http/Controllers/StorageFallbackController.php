<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesStoredFiles;
use App\Services\Storage\MediaAccessRegistry;
use App\Services\Storage\StorageBackendRegistry;
use Symfony\Component\HttpFoundation\Response;

class StorageFallbackController extends Controller
{
    use ServesStoredFiles;

    public function __invoke(string $path, StorageBackendRegistry $registry, MediaAccessRegistry $access): Response
    {
        // This controller exists for hosts where the storage symlink could not
        // be created. It must not become the way around the media gate: it has
        // no signature check and no authorisation, so anything MediaController
        // protects has to be refused here outright (M018/S04).
        foreach ($access->prefixes() as $protected) {
            if (str_starts_with($path, $protected)) {
                abort(404);
            }
        }

        $contents = $registry->retrieveWithFallback($path);

        if ($contents === null) {
            abort(404);
        }

        return $this->fileResponse($contents, $path, 'public, max-age=604800');
    }
}

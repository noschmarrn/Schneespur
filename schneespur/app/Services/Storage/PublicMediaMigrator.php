<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves files written before M018/S04 from the public disk to the private one.
 *
 * Runs in bounded batches and is safe to repeat: the copy is written before the
 * source is removed, and an existing private copy is never overwritten. That
 * matters because there is no CLI on the target hosting — this runs through the
 * scheduler, and it must survive being cut off mid-batch.
 *
 * Scope is exactly MediaAccessRegistry::prefixes(). A module that never
 * registers its prefix keeps its legacy files sitting on the public disk.
 */
class PublicMediaMigrator
{
    public function __construct(private readonly MediaAccessRegistry $access) {}

    /** @return array{moved:int, remaining:int} */
    public function migrateBatch(int $limit = 50): array
    {
        $pending = $this->pendingFiles();
        $batch = array_slice($pending, 0, $limit);
        $moved = 0;

        foreach ($batch as $path) {
            try {
                if (! Storage::disk('local')->exists($path)) {
                    $contents = Storage::disk('public')->get($path);

                    if ($contents === null) {
                        continue;
                    }

                    Storage::disk('local')->put($path, $contents);
                }

                // Only ever delete once the private copy is in place. An abort
                // between the two leaves a duplicate, which the next run
                // resolves — never a gap, which nothing could resolve.
                Storage::disk('public')->delete($path);
                $moved++;
            } catch (\Throwable $e) {
                Log::warning('PublicMediaMigrator: could not move file', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'moved' => $moved,
            'remaining' => max(0, count($pending) - $moved),
        ];
    }

    /** @return array<int, string> */
    private function pendingFiles(): array
    {
        $files = [];

        foreach ($this->access->prefixes() as $prefix) {
            $directory = rtrim($prefix, '/');

            // allFiles() recurses, so date-nested photo folders are included.
            foreach (Storage::disk('public')->allFiles($directory) as $path) {
                $files[] = $path;
            }
        }

        return $files;
    }
}

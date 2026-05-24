<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\JobLifecycleService;
use App\Services\PhotoService;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverPhotoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,heic,webp', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $job = app(JobLifecycleService::class)->findActiveJob($request->user());

        if (! $job) {
            return response()->json(['message' => __('job.no_active_job')], 422);
        }

        if (! PhotoService::canAddPhoto($job)) {
            return response()->json([
                'message' => __('job.photo_limit_reached', ['max' => PhotoService::MAX_PHOTOS_PER_JOB]),
            ], 422);
        }

        $photo = app(PhotoService::class)->store($validated['photo'], $job);

        if (! empty($validated['caption'])) {
            $photo->update(['caption' => $validated['caption']]);
        }

        $registry = app(StorageBackendRegistry::class);

        return response()->json([
            'id' => $photo->id,
            'thumbnail_url' => $registry->resolve()->url($photo->thumbnail_path),
            'photos_remaining' => PhotoService::MAX_PHOTOS_PER_JOB - $job->jobPhotos()->count(),
        ], 201);
    }
}

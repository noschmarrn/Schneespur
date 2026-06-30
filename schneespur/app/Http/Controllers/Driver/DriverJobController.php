<?php

namespace App\Http\Controllers\Driver;

use App\Enums\LifecyclePoint;
use App\Exceptions\JobLifecycleException;
use App\Http\Controllers\Controller;
use App\Models\CustomerObject;
use App\Models\Vehicle;
use App\Services\Extension\JobTypeRegistry;
use App\Services\Extension\LifecycleFieldRegistry;
use App\Services\JobLifecycleService;
use App\Services\PhotoService;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class DriverJobController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $registry = app(LifecycleFieldRegistry::class);
        $validated = $request->validate(array_merge([
            'customer_object_id' => ['required', 'exists:customer_objects,id'],
            'type' => ['required', Rule::in(app(JobTypeRegistry::class)->values())],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ], $registry->rules(LifecyclePoint::JobStart, $request->user())));

        $customerObject = CustomerObject::findOrFail($validated['customer_object_id']);
        $vehicle = isset($validated['vehicle_id']) ? Vehicle::find($validated['vehicle_id']) : null;
        $extra = Arr::only($validated, $registry->fieldKeys(LifecyclePoint::JobStart));

        try {
            app(JobLifecycleService::class)->startJob(
                $request->user(),
                $customerObject,
                $validated['type'],
                $vehicle,
                $extra,
            );
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('job.started'));
    }

    public function end(Request $request): RedirectResponse
    {
        $registry = app(LifecycleFieldRegistry::class);
        $validated = $request->validate(array_merge([
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $registry->rules(LifecyclePoint::JobEnd, $request->user())));

        $extra = Arr::only($validated, $registry->fieldKeys(LifecyclePoint::JobEnd));

        try {
            app(JobLifecycleService::class)->endJob(
                $request->user(),
                $validated['notes'] ?? null,
                $extra,
            );
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('job.ended'));
    }

    public function active(Request $request): JsonResponse
    {
        $service = app(JobLifecycleService::class);
        $user = $request->user();

        $shift = $service->findActiveShift($user);
        $job = $shift ? $service->findActiveJob($user) : null;

        if ($job) {
            $job->loadCount('gpsPoints')->load(['customerObject.customer', 'vehicle', 'jobPhotos']);
        }

        return response()->json([
            'shift' => $shift ? [
                'id' => $shift->id,
                'started_at' => $shift->started_at->toIso8601String(),
            ] : null,
            'job' => $job ? [
                'id' => $job->id,
                'customer_name' => $job->customerObject?->customer?->name ?? '–',
                'object_name' => $job->customerObject?->name,
                'type_label' => $job->type->label(),
                'vehicle_label' => $job->vehicle?->displayLabel(),
                'started_at' => $job->started_at->toIso8601String(),
                'gps_points_count' => $job->gps_points_count,
                'photos_remaining' => PhotoService::MAX_PHOTOS_PER_JOB - $job->jobPhotos->count(),
                'photos' => $job->jobPhotos->map(fn ($p) => [
                    'id' => $p->id,
                    'thumbnail_url' => app(StorageBackendRegistry::class)->urlWithFallback($p->thumbnail_path),
                    'full_url' => app(StorageBackendRegistry::class)->urlWithFallback($p->file_path),
                    'caption' => $p->caption,
                ]),
            ] : null,
        ]);
    }
}

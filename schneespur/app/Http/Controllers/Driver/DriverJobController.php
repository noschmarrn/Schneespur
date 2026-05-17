<?php

namespace App\Http\Controllers\Driver;

use App\Enums\JobType;
use App\Exceptions\JobLifecycleException;
use App\Http\Controllers\Controller;
use App\Models\CustomerObject;
use App\Models\Vehicle;
use App\Services\JobLifecycleService;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverJobController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_object_id' => ['required', 'exists:customer_objects,id'],
            'type' => ['required', Rule::enum(JobType::class)],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);

        $customerObject = CustomerObject::findOrFail($validated['customer_object_id']);
        $vehicle = isset($validated['vehicle_id']) ? Vehicle::find($validated['vehicle_id']) : null;

        try {
            app(JobLifecycleService::class)->startJob(
                $request->user(),
                $customerObject,
                JobType::from($validated['type']),
                $vehicle,
            );
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('job.started'));
    }

    public function end(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(JobLifecycleService::class)->endJob(
                $request->user(),
                $validated['notes'] ?? null,
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
                    'thumbnail_url' => Storage::disk('public')->url($p->thumbnail_path),
                    'full_url' => Storage::disk('public')->url($p->file_path),
                    'caption' => $p->caption,
                ]),
            ] : null,
        ]);
    }
}

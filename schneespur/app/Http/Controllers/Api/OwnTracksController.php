<?php

namespace App\Http\Controllers\Api;

use App\Events\GpsPointReceived;
use App\Http\Controllers\Controller;
use App\Models\GpsPoint;
use App\Services\JobLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnTracksController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if ($request->input('_type') !== 'location') {
            return response()->json([], 200);
        }

        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'tst' => 'required|integer',
            'alt' => 'nullable|numeric',
            'batt' => 'nullable|integer',
            'vel' => 'nullable|integer',
            'acc' => 'nullable|integer',
        ]);

        $activeJob = app(JobLifecycleService::class)->findActiveJob($request->user());

        // Notify modules (e.g. geofencing) of every ping, including idle ones
        // where no job is active yet — this is the only point at which a module
        // can observe driver position before a job exists.
        GpsPointReceived::dispatch(
            $request->user(),
            (float) $validated['lat'],
            (float) $validated['lon'],
            (int) $validated['tst'],
            isset($validated['acc']) ? (int) $validated['acc'] : null,
            $activeJob,
        );

        if ($activeJob === null) {
            return response()->json([], 200);
        }

        GpsPoint::create([
            'user_id' => $request->user()->id,
            'job_id' => $activeJob->id,
            'lat' => $validated['lat'],
            'lon' => $validated['lon'],
            'timestamp' => $validated['tst'],
            'altitude' => $validated['alt'] ?? null,
            'battery' => $validated['batt'] ?? null,
            'velocity' => $validated['vel'] ?? null,
            'accuracy' => $validated['acc'] ?? null,
        ]);

        return response()->json([], 200);
    }
}

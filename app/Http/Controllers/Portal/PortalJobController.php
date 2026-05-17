<?php

namespace App\Http\Controllers\Portal;

use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\GpsSmoothingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalJobController extends Controller
{
    public function index(Request $request): View
    {
        $customer = auth('customer')->user();

        $jobs = Job::where('customer_id', $customer->id)
            ->whereNotNull('ended_at')
            ->with(['customerObject'])
            ->when($customer->portal_show_driver_name, fn ($q) => $q->with('user'))
            ->when($request->customer_object_id, fn ($q, $id) => $q->where('customer_object_id', $id))
            ->when($request->started_after, fn ($q, $date) => $q->where('started_at', '>=', $date))
            ->when($request->started_before, fn ($q, $date) => $q->where('started_at', '<=', $date))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('started_at')
            ->paginate(25)
            ->withQueryString();

        $objects = $customer->objects()->orderBy('name')->get();
        $jobTypes = JobType::cases();

        return view('portal.jobs.index', compact('jobs', 'objects', 'jobTypes', 'customer'));
    }

    public function show(Job $serviceJob, GpsSmoothingService $gpsSmoother): View
    {
        $customer = auth('customer')->user();
        abort_unless($serviceJob->customer_id === $customer->id, 404);

        $relations = ['customerObject', 'weatherSnapshots'];

        if ($customer->portal_show_photos) {
            $relations['jobPhotos'] = fn ($q) => $q->orderBy('sort_order')->orderBy('created_at');
        }
        if ($customer->portal_show_gps) {
            $relations['gpsPoints'] = fn ($q) => $q->orderBy('timestamp');
        }
        if ($customer->portal_show_driver_name) {
            $relations[] = 'user';
        }

        $serviceJob->load($relations);

        $smoothedGps = collect();
        if ($customer->portal_show_gps && $serviceJob->gpsPoints->isNotEmpty()) {
            $smoothedGps = $gpsSmoother->smooth($serviceJob->gpsPoints)
                ->map(fn ($p) => ['lat' => $p->lat, 'lon' => $p->lon]);
        }

        $driverLastName = null;
        if ($customer->portal_show_driver_name && $serviceJob->user) {
            $parts = explode(' ', trim($serviceJob->user->name));
            $driverLastName = end($parts);
        }

        return view('portal.jobs.show', [
            'job' => $serviceJob,
            'customer' => $customer,
            'smoothedGps' => $smoothedGps,
            'driverLastName' => $driverLastName,
        ]);
    }
}

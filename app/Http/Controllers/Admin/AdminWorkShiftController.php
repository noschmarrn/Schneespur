<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWorkShiftController extends Controller
{
    public function index(Request $request): View
    {
        $shifts = WorkShift::query()
            ->with('user')
            ->withCount('jobs')
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->started_after, fn ($q, $date) => $q->where('started_at', '>=', $date))
            ->when($request->started_before, fn ($q, $date) => $q->where('started_at', '<=', $date))
            ->orderByDesc('started_at')
            ->paginate(25)
            ->withQueryString();

        $drivers = User::drivers()->orderBy('name')->get();

        return view('admin.workshifts.index', compact('shifts', 'drivers'));
    }

    public function show(WorkShift $workShift): View
    {
        $workShift->load(['user', 'jobs.customer']);

        $duration = null;
        if ($workShift->started_at && $workShift->ended_at) {
            $duration = $workShift->started_at->diffForHumans($workShift->ended_at, true);
        }

        return view('admin.workshifts.show', compact('workShift', 'duration'));
    }
}

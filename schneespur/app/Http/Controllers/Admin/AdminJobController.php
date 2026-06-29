<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Services\GpsSmoothingService;
use App\Services\JobAuditService;
use App\Services\PdfReportService;
use App\Services\RetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminJobController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('jobs.view');

        $jobs = Job::query()
            ->with(['customer', 'customerObject.customer', 'user'])
            ->withCount('gpsPoints')
            ->when($request->started_after, fn ($q, $date) => $q->where('started_at', '>=', $date))
            ->when($request->started_before, fn ($q, $date) => $q->where('started_at', '<=', $date))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->customer_id, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->customer_object_id, fn ($q, $id) => $q->where('customer_object_id', $id))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('started_at')
            ->paginate(25)
            ->withQueryString();

        $drivers = User::drivers()->orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        $jobTypes = app(\App\Services\Extension\JobTypeRegistry::class)->types();
        $objects = $request->customer_id
            ? CustomerObject::where('customer_id', $request->customer_id)->orderBy('name')->get()
            : collect();

        return view('admin.jobs.index', compact('jobs', 'drivers', 'customers', 'jobTypes', 'objects'));
    }

    public function show(Job $serviceJob, GpsSmoothingService $gpsSmoother): View
    {
        Gate::authorize('jobs.view');

        $serviceJob->load([
            'customer',
            'customerObject.customer',
            'user',
            'vehicle',
            'gpsPoints' => fn ($q) => $q->orderBy('timestamp'),
            'weatherSnapshots',
            'jobPhotos' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
            'audits.user',
        ]);

        $smoothedGps = $gpsSmoother->smooth($serviceJob->gpsPoints)
            ->map(fn ($p) => ['lat' => $p->lat, 'lon' => $p->lon]);

        return view('admin.jobs.show', ['job' => $serviceJob, 'smoothedGps' => $smoothedGps]);
    }

    public function edit(Job $serviceJob): View
    {
        Gate::authorize('jobs.edit');
        $this->authorize('update', $serviceJob);

        $serviceJob->load(['customer', 'customerObject.customer', 'user']);

        return view('admin.jobs.edit', ['job' => $serviceJob]);
    }

    public function update(Request $request, Job $serviceJob, JobAuditService $auditService): RedirectResponse
    {
        Gate::authorize('jobs.edit');
        $this->authorize('update', $serviceJob);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldValues = ['notes' => $serviceJob->notes];
        $serviceJob->update($validated);
        $newValues = ['notes' => $serviceJob->notes];

        $auditService->logChange($serviceJob, 'updated', $oldValues, $newValues);

        return redirect()->route('admin.jobs.show', $serviceJob)
            ->with('success', __('job.edit_success'));
    }

    public function destroy(Request $request, Job $serviceJob, JobAuditService $auditService, RetentionService $retentionService): RedirectResponse
    {
        Gate::authorize('jobs.delete');
        $this->authorize('delete', $serviceJob);

        $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if ($request->input('confirmation') !== __('job.delete_confirmation_word')) {
            return back()->withErrors(['confirmation' => __('job.delete_confirm_mismatch')]);
        }

        $auditService->logDeletion($serviceJob);
        $retentionService->deleteJob($serviceJob);

        return redirect()->route('admin.jobs.index')
            ->with('success', __('job.delete_success'));
    }

    public function pdf(Job $serviceJob, PdfReportService $pdfService): Response
    {
        Gate::authorize('jobs.view');

        abort_if(is_null($serviceJob->ended_at), 422, __('job.pdf_active_blocked'));

        $pdfContent = $pdfService->generateJobReport($serviceJob);
        $filename = $pdfService->jobReportFilename($serviceJob);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}

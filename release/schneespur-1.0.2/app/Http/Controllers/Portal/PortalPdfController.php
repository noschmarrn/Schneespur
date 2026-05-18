<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PortalPdfController extends Controller
{
    public function __construct(
        private readonly PdfReportService $pdfReportService,
    ) {}

    public function jobPdf(Job $serviceJob): Response
    {
        $customer = auth('customer')->user();
        abort_unless($serviceJob->customer_id === $customer->id, 404);
        abort_unless($serviceJob->ended_at !== null, 422, __('portal.reports_job_not_completed'));

        $pdf = $this->pdfReportService->generateJobReport($serviceJob);
        $filename = $this->pdfReportService->jobReportFilename($serviceJob);

        return $pdf->download($filename);
    }

    public function index(): View
    {
        $customer = auth('customer')->user();
        $objects = $customer->objects()->orderBy('name')->get();

        return view('portal.reports.index', [
            'objects' => $objects,
            'defaultFrom' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'defaultTo' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function generate(Request $request): Response
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'customer_object_id' => ['nullable', 'integer'],
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $objectId = $validated['customer_object_id'] ?? null;

        if ($objectId) {
            $object = CustomerObject::where('id', $objectId)
                ->where('customer_id', $customer->id)
                ->first();
            abort_unless($object !== null, 404);

            $jobCount = Job::where('customer_object_id', $object->id)
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', $from)
                ->where('started_at', '<=', $to->copy()->endOfDay())
                ->count();

            if ($jobCount === 0) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('portal.reports_no_jobs'));
            }

            $pdf = $this->pdfReportService->generateObjectReport($object, $from, $to);
            $filename = $this->pdfReportService->objectReportFilename($object, $from, $to);
        } else {
            $jobCount = Job::where('customer_id', $customer->id)
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', $from)
                ->where('started_at', '<=', $to->copy()->endOfDay())
                ->count();

            if ($jobCount === 0) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('portal.reports_no_jobs'));
            }

            $pdf = $this->pdfReportService->generateCustomerReport($customer, $from, $to);
            $filename = $this->pdfReportService->customerReportFilename($customer, $from, $to);
        }

        return $pdf->download($filename);
    }
}

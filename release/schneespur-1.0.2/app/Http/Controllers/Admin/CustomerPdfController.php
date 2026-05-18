<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Job;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CustomerPdfController extends Controller
{
    public function __construct(
        private readonly PdfReportService $pdfReportService,
    ) {}

    public function index(Request $request): View
    {
        $customers = Customer::orderBy('name')->get();

        return view('admin.exports.customer-pdf', [
            'customers' => $customers,
            'selectedCustomer' => $request->query('customer'),
            'defaultFrom' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'defaultTo' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function generate(Request $request): Response
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'include_active' => ['sometimes', 'boolean'],
            'confirmed' => ['sometimes', 'boolean'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $includeActive = (bool) ($validated['include_active'] ?? false);

        $jobCount = Job::where('customer_id', $customer->id)
            ->where('started_at', '>=', $from)
            ->where('started_at', '<=', $to->copy()->endOfDay())
            ->when(! $includeActive, fn ($q) => $q->whereNotNull('ended_at'))
            ->count();

        if ($jobCount === 0) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('export.pdf_no_jobs'));
        }

        if ($jobCount > 50 && ! ($validated['confirmed'] ?? false)) {
            return redirect()->back()
                ->withInput()
                ->with('warning', __('export.pdf_warning_many_jobs', ['count' => $jobCount]));
        }

        try {
            $pdf = $this->pdfReportService->generateCustomerReport($customer, $from, $to, $includeActive);
            $filename = $this->pdfReportService->customerReportFilename($customer, $from, $to);

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()
                ->withInput()
                ->with('error', __('export.pdf_no_jobs'));
        }
    }
}

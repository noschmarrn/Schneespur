<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\CsvExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CsvExportController extends Controller
{
    public function __construct(
        private readonly CsvExportService $csvExportService,
    ) {}

    public function index(): View
    {
        $drivers = User::withAnonymized()->drivers()->orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();

        return view('admin.exports.csv', [
            'drivers' => $drivers,
            'customers' => $customers,
            'defaultFrom' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'defaultTo' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function download(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'variant' => ['required', 'in:all,driver,customer'],
            'user_id' => ['required_if:variant,driver', 'nullable', 'exists:users,id'],
            'customer_id' => ['required_if:variant,customer', 'nullable', 'exists:customers,id'],
        ]);

        $csv = $this->csvExportService->buildCsv(
            variant: $validated['variant'],
            from: $validated['from'],
            to: $validated['to'],
            userId: $validated['user_id'] ?? null,
            customerId: $validated['customer_id'] ?? null,
        );

        $filename = $this->csvExportService->generateFilename(
            variant: $validated['variant'],
            from: $validated['from'],
            to: $validated['to'],
        );

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}

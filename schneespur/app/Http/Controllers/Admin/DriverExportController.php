<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DriverExportService;
use Illuminate\Support\Facades\Gate;

class DriverExportController extends Controller
{
    public function __construct(
        private readonly DriverExportService $exportService,
    ) {}

    public function exportSingle(User $driver)
    {
        Gate::authorize('drivers.view');

        $path = $this->exportService->exportSingle($driver);

        return response()->download($path, "fahrer-{$driver->id}-export.zip")->deleteFileAfterSend(true);
    }

    public function exportAll()
    {
        Gate::authorize('drivers.view');

        $path = $this->exportService->exportAll();

        return response()->download($path, 'alle-fahrer-export.zip')->deleteFileAfterSend(true);
    }
}

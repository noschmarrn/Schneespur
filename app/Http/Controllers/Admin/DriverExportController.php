<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DriverExportService;

class DriverExportController extends Controller
{
    public function __construct(
        private readonly DriverExportService $exportService,
    ) {}

    public function exportSingle(User $driver)
    {
        $path = $this->exportService->exportSingle($driver);

        return response()->download($path, "fahrer-{$driver->id}-export.zip")->deleteFileAfterSend(true);
    }

    public function exportAll()
    {
        $path = $this->exportService->exportAll();

        return response()->download($path, 'alle-fahrer-export.zip')->deleteFileAfterSend(true);
    }
}

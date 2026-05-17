<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnonymizeDriverRequest;
use App\Models\User;
use App\Services\DriverAnonymizationService;
use Illuminate\Http\RedirectResponse;

class DriverAnonymizationController extends Controller
{
    public function __invoke(
        AnonymizeDriverRequest $request,
        User $driver,
        DriverAnonymizationService $service
    ): RedirectResponse {
        $service->anonymize($driver, $request->validated('reason'));

        return redirect()
            ->route('admin.drivers.index')
            ->with('success', __('driver.flash_anonymized'));
    }
}

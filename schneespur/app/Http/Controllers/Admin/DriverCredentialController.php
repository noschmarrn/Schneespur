<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OwntracksCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DriverCredentialController extends Controller
{
    public function show(Request $request, User $driver): View|RedirectResponse
    {
        Gate::authorize('drivers.view');

        $credentials = session('owntracks_credentials');

        if (! $credentials) {
            return redirect()->route('admin.drivers.edit', $driver);
        }

        $driver->owntracks_password_revealed_at = now();
        $driver->save();

        return view('admin.drivers.credentials', compact('driver', 'credentials'));
    }

    public function rotate(Request $request, User $driver, OwntracksCredentialService $credentialService): RedirectResponse
    {
        Gate::authorize('drivers.edit');

        $credentials = $credentialService->generateCredentials($driver, $request->user());

        session()->flash('owntracks_credentials', $credentials);

        return redirect()
            ->route('admin.drivers.credentials', $driver)
            ->with('success', __('driver.flash_rotated'));
    }
}

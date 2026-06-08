<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\CustomerEmailChangedMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortalProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.profile.edit', [
            'customer' => auth('customer')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        $throttleKey = 'portal-profile|' . $customer->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
            'locale' => ['required', \Illuminate\Validation\Rule::in(app(\App\Services\Extension\LocaleRegistry::class)->codes())],
            'current_password' => ['nullable', 'required_with:password', 'current_password:customer'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $customer->email = $validated['email'];
        $customer->locale = $validated['locale'];

        $emailChanged = $customer->isDirty('email');
        $oldEmail = $customer->getOriginal('email');

        if ($request->filled('password')) {
            $customer->password = $validated['password'];
        }

        $customer->save();

        RateLimiter::clear($throttleKey);

        if ($emailChanged) {
            $admin = User::first();
            if ($admin) {
                Mail::to($admin)->send(new CustomerEmailChangedMail($customer, $oldEmail, $validated['email']));
            }
        }

        App::setLocale($customer->locale);
        session(['locale' => $customer->locale]);

        return redirect()->back()->with('success', __('portal.profile_saved'));
    }
}

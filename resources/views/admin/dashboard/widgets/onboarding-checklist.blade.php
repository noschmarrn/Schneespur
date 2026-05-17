@php
    $onboarding = $widget['data']['onboarding'];
    $onboardingDone = count(array_filter($onboarding));
    $onboardingTotal = count($onboarding);
    $onboardingItems = [
        'company'  => ['route' => route('admin.settings.company'), 'label' => __('dashboard.onboarding_company'), 'link' => __('dashboard.onboarding_company_link')],
        'driver'   => ['route' => route('admin.drivers.create'), 'label' => __('dashboard.onboarding_driver'), 'link' => __('dashboard.onboarding_driver_link')],
        'customer' => ['route' => route('admin.customers.create'), 'label' => __('dashboard.onboarding_customer'), 'link' => __('dashboard.onboarding_customer_link')],
        'vehicle'  => ['route' => route('admin.vehicles.create'), 'label' => __('dashboard.onboarding_vehicle'), 'link' => __('dashboard.onboarding_vehicle_link')],
        'email'    => ['route' => route('admin.settings.email'), 'label' => __('dashboard.onboarding_email'), 'link' => __('dashboard.onboarding_email_link')],
        'cron'     => ['route' => '#cron-setup', 'label' => __('dashboard.onboarding_cron'), 'link' => __('dashboard.onboarding_cron_link')],
    ];
@endphp
<div class="mb-6 bg-white shadow-sm rounded-lg p-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold text-gray-900">{{ __('dashboard.onboarding_title') }}</h3>
        <form method="POST" action="{{ route('admin.dashboard.dismiss-onboarding') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 underline">{{ __('dashboard.onboarding_dismiss') }}</button>
        </form>
    </div>
    <p class="text-sm text-gray-500 mb-4">{{ __('dashboard.onboarding_intro', ['app_name' => brand()]) }}</p>

    <div class="mb-3">
        <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
            <span>{{ __('dashboard.onboarding_progress', ['done' => $onboardingDone, 'total' => $onboardingTotal]) }}</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-green-500 h-2 rounded-full transition-all" style="width: {{ $onboardingTotal > 0 ? round(($onboardingDone / $onboardingTotal) * 100) : 0 }}%"></div>
        </div>
    </div>

    <ul class="space-y-2">
        @foreach ($onboardingItems as $key => $item)
            <li class="flex items-center justify-between py-1">
                <div class="flex items-center gap-2">
                    @if ($onboarding[$key])
                        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="text-sm text-gray-500 line-through">{{ $item['label'] }}</span>
                    @else
                        <svg class="w-5 h-5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2" /></svg>
                        <span class="text-sm text-gray-900">{{ $item['label'] }}</span>
                    @endif
                </div>
                @unless ($onboarding[$key])
                    <a href="{{ $item['route'] }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">{{ $item['link'] }} &rarr;</a>
                @endunless
            </li>
        @endforeach
    </ul>
</div>

{{-- Beträge eines Zeitraums. Erwartet $revenue (RevenueSummary|null). --}}
@if ($revenue && ($revenue->hasAnything() || $revenue->jobsWithoutAmount > 0))
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-base font-semibold text-gray-900">{{ __('overview.revenue_title') }}</h3>
        @if ($revenue->hasAnything())
            <dl class="mt-4 divide-y divide-gray-100 text-sm">
                @if ($revenue->pricedJobCount > 0)
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-600">{{ trans_choice('overview.revenue_jobs', $revenue->pricedJobCount, ['count' => $revenue->pricedJobCount]) }}</dt>
                        <dd class="font-medium text-gray-900">{{ format_money($revenue->jobTotalCents) }}</dd>
                    </div>
                @endif
                @foreach ($revenue->flatRateLines as $line)
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-600">
                            {{ $line->objectName }}@if ($line->jobTypeLabel) · {{ $line->jobTypeLabel }}@endif
                            — {{ __('customer_object.price_unit_'.$line->unit) }}
                            ({{ $line->count }} × {{ format_money($line->rateCents) }})
                        </dt>
                        <dd class="font-medium text-gray-900">{{ format_money($line->totalCents()) }}</dd>
                    </div>
                @endforeach
                <div class="flex justify-between py-2">
                    <dt class="font-semibold text-gray-900">{{ __('overview.revenue_total') }}</dt>
                    <dd class="font-semibold text-gray-900">{{ format_money($revenue->totalCents()) }}</dd>
                </div>
            </dl>
        @endif
        @if ($revenue->jobsWithoutAmount > 0)
            <p class="mt-3 text-sm text-amber-700">
                {{ trans_choice('overview.revenue_jobs_without_amount', $revenue->jobsWithoutAmount, ['count' => $revenue->jobsWithoutAmount]) }}
            </p>
        @endif
        @if ($revenue->flatRateLines !== [])
            <p class="mt-2 text-xs text-gray-500">{{ __('overview.revenue_flat_rate_note') }}</p>
        @endif
    </div>
@endif

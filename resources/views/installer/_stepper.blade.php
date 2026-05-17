@php
    $steps = [
        1 => __('install.stepper_step_1'),
        2 => __('install.stepper_step_2'),
        3 => __('install.stepper_step_3'),
        4 => __('install.stepper_step_4'),
        5 => __('install.stepper_step_5'),
        6 => __('install.stepper_step_6'),
        7 => __('install.stepper_step_7'),
        8 => __('install.stepper_step_8'),
        9 => __('install.stepper_step_9'),
    ];
    $current = $currentStep ?? 1;
@endphp

{{-- Mobile: compact text --}}
<div class="block sm:hidden w-full px-4 text-center text-sm text-gray-600 font-medium">
    @if($current <= 9)
        {{ __('install.stepper_mobile', ['current' => $current, 'title' => $steps[$current] ?? '']) }}
    @endif
</div>

{{-- Desktop: horizontal stepper --}}
<div class="hidden sm:flex w-full sm:max-w-2xl items-center justify-between px-2">
    @foreach($steps as $num => $title)
        <div class="flex flex-col items-center flex-1">
            <div @class([
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold border-2',
                'bg-blue-600 border-blue-600 text-white' => $num === $current,
                'bg-green-500 border-green-500 text-white' => $num < $current,
                'bg-white border-gray-300 text-gray-400' => $num > $current,
            ])>
                @if($num < $current)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                @else
                    {{ $num }}
                @endif
            </div>
            <span @class([
                'mt-1 text-xs text-center leading-tight',
                'text-blue-600 font-semibold' => $num === $current,
                'text-green-600' => $num < $current,
                'text-gray-400' => $num > $current,
            ])>{{ $title }}</span>
        </div>
        @if(!$loop->last)
            <div @class([
                'flex-1 h-0.5 mx-1 mt-[-1rem]',
                'bg-green-500' => $num < $current,
                'bg-gray-300' => $num >= $current,
            ])></div>
        @endif
    @endforeach
</div>

@php
    $textClass = ($size ?? 'sm') === 'xs' ? 'text-xs text-gray-600' : 'text-sm text-gray-600';
    $limit = 150;
    $isLong = \Illuminate\Support\Str::length($description) > $limit;
@endphp

@if($isLong)
    <div class="mt-1" x-data="{ showFull: false }">
        <p class="{{ $textClass }}">
            <span x-show="!showFull">{{ \Illuminate\Support\Str::limit($description, $limit) }}</span>
            <span x-show="showFull" x-cloak>{{ $description }}</span>
            <button type="button" class="ml-1 font-medium text-blue-600 hover:text-blue-700" x-on:click="showFull = !showFull">
                <span x-show="!showFull">{{ __('modules.desc_more') }}</span>
                <span x-show="showFull" x-cloak>{{ __('modules.desc_less') }}</span>
            </button>
        </p>
    </div>
@else
    <p class="mt-1 {{ $textClass }}">{{ $description }}</p>
@endif

@props(['title'])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <h2 class="text-2xl font-semibold text-gray-900">{{ $title }}</h2>

    @isset($action)
        <div class="shrink-0">
            {{ $action }}
        </div>
    @endisset
</div>

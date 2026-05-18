@props(['heading', 'body' => null])

<div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-12 text-center">
    <h3 class="text-sm font-semibold text-gray-900">{{ $heading }}</h3>

    @if ($body)
        <p class="mt-1 text-sm text-gray-500">{{ $body }}</p>
    @endif

    @isset($action)
        <div class="mt-6">
            {{ $action }}
        </div>
    @endisset
</div>

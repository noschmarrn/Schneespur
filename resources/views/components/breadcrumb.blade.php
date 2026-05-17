@props(['items' => []])

<nav aria-label="{{ __('ui.breadcrumb_aria') }}">
    <ol class="flex items-center space-x-2 text-sm">
        @foreach ($items as $i => $item)
            @if ($i > 0)
                <li class="text-gray-400" aria-hidden="true">&gt;</li>
            @endif

            @if (isset($item['url']) && $i < count($items) - 1)
                <li>
                    <a href="{{ $item['url'] }}" class="text-blue-600 hover:underline">{{ $item['label'] }}</a>
                </li>
            @else
                <li class="text-gray-500" aria-current="page">{{ $item['label'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>

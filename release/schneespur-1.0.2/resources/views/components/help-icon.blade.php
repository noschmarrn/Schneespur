@props(['topic', 'tooltip' => null])

<a href="{{ route('admin.help.show', $topic) }}"
   @if($tooltip) title="{{ $tooltip }}" @endif
   {{ $attributes->merge(['class' => 'inline-flex items-center text-gray-400 hover:text-blue-500 transition-colors']) }}>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M12 18.75h.007v.008H12v-.008z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
</a>

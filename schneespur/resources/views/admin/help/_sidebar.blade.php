<nav class="space-y-1">
    @foreach($topics as $slug => $meta)
        <a href="{{ route('admin.help.show', $slug) }}"
           class="flex items-center gap-2 px-3 py-2 text-sm rounded-md transition-colors {{ ($topic ?? '') === $slug ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}" />
            </svg>
            {{ __($meta['lang']) }}
        </a>
    @endforeach
</nav>

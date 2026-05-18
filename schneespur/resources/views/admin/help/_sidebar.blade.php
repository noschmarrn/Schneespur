<nav class="space-y-1">
    @foreach($topics as $slug => $langKey)
        <a href="{{ route('admin.help.show', $slug) }}"
           class="block px-3 py-2 text-sm rounded-md transition-colors {{ ($topic ?? '') === $slug ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
            {{ __($langKey) }}
        </a>
    @endforeach
</nav>

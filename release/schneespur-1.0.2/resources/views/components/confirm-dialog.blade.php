@props(['name', 'title', 'message'])

<x-modal :name="$name" maxWidth="lg" focusable>
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900">{{ $title }}</h2>

        <p class="mt-2 text-sm text-gray-600 break-words">{!! $message !!}</p>

        <div class="mt-6 flex justify-end space-x-3">
            <x-secondary-button x-on:click="$dispatch('close-modal', '{{ $name }}')">
                {{ __('ui.button_cancel') }}
            </x-secondary-button>

            {{ $action }}
        </div>
    </div>
</x-modal>

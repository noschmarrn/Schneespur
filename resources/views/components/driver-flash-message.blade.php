@if (session('success') || session('error'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-4 rounded-lg p-4 {{ session('success') ? 'bg-green-900/50 border border-green-700' : 'bg-red-900/50 border border-red-700' }}"
         role="alert">
        <div class="flex items-center">
            <div class="shrink-0">
                @if (session('success'))
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                @else
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                @endif
            </div>
            <div class="ml-3 flex-1">
                <p class="text-sm font-medium {{ session('success') ? 'text-green-300' : 'text-red-300' }}">
                    {{ session('success') ?? session('error') }}
                </p>
            </div>
            <div class="ml-auto pl-3">
                <button @click="show = false" type="button"
                        class="-mx-1.5 -my-1.5 inline-flex rounded-md p-1.5 min-h-[44px] min-w-[44px] items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900 {{ session('success') ? 'text-green-400 hover:bg-green-900 focus:ring-green-500' : 'text-red-400 hover:bg-red-900 focus:ring-red-500' }}">
                    <span class="sr-only">{{ __('ui.button_close') }}</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endif

@foreach (['success', 'warning', 'error'] as $type)
    @if (session($type))
        @php
            $styles = match ($type) {
                'success' => [
                    'bg' => 'bg-green-50 border-green-200',
                    'icon' => 'text-green-400',
                    'text' => 'text-green-800',
                    'btn' => 'text-green-500 hover:bg-green-100 focus:ring-green-600 focus:ring-offset-green-50',
                ],
                'warning' => [
                    'bg' => 'bg-amber-50 border-amber-200',
                    'icon' => 'text-amber-400',
                    'text' => 'text-amber-800',
                    'btn' => 'text-amber-500 hover:bg-amber-100 focus:ring-amber-600 focus:ring-offset-amber-50',
                ],
                'error' => [
                    'bg' => 'bg-red-50 border-red-200',
                    'icon' => 'text-red-400',
                    'text' => 'text-red-800',
                    'btn' => 'text-red-500 hover:bg-red-100 focus:ring-red-600 focus:ring-offset-red-50',
                ],
            };
        @endphp
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 5000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="mb-4 rounded-md border p-4 {{ $styles['bg'] }}"
             role="alert">
            <div class="flex items-center">
                <div class="shrink-0">
                    @if ($type === 'success')
                        <svg class="h-5 w-5 {{ $styles['icon'] }}" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                        </svg>
                    @elseif ($type === 'warning')
                        <svg class="h-5 w-5 {{ $styles['icon'] }}" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg class="h-5 w-5 {{ $styles['icon'] }}" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    @endif
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium {{ $styles['text'] }}">
                        {{ session($type) }}
                    </p>
                </div>
                <div class="ml-auto pl-3">
                    <button @click="show = false" type="button"
                            class="-mx-1.5 -my-1.5 inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $styles['btn'] }}">
                        <span class="sr-only">{{ __('ui.button_close') }}</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
@endforeach

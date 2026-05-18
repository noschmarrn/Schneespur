<x-admin-layout>
    <x-slot name="header">{{ __('dsgvo.admin_page_title') }} <x-help-icon topic="dsgvo" /></x-slot>

    <div class="mb-4">
        <p class="text-sm text-gray-600">
            {{ __('dsgvo.admin_current_version_sub', ['version' => $version, 'count' => $confirmationCount]) }}
        </p>
    </div>

    <div x-data="dsgvoEditor()" class="bg-white overflow-hidden shadow-sm rounded-lg">
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-1 px-4 py-2 border-b border-gray-200 bg-gray-50">
            <button type="button" @click="insertSyntax('**', '**')" class="px-2 py-1 text-sm font-bold rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_bold') }}">B</button>
            <button type="button" @click="insertSyntax('*', '*')" class="px-2 py-1 text-sm italic rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_italic') }}">I</button>
            <span class="w-px h-5 bg-gray-300 mx-1"></span>
            <button type="button" @click="insertPrefix('## ')" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_h2') }}">H2</button>
            <button type="button" @click="insertPrefix('### ')" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_h3') }}">H3</button>
            <span class="w-px h-5 bg-gray-300 mx-1"></span>
            <button type="button" @click="insertPrefix('- ')" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_ul') }}">&#8226; Liste</button>
            <button type="button" @click="insertPrefix('1. ')" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_ol') }}">1. Liste</button>
            <span class="w-px h-5 bg-gray-300 mx-1"></span>
            <button type="button" @click="insertLink()" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="{{ __('dsgvo.admin_toolbar_link') }}">&#128279; Link</button>
        </div>

        {{-- Editor + Preview side by side --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-gray-200">
            {{-- Editor --}}
            <div class="p-4">
                <label for="markdown-editor" class="block text-sm font-medium text-gray-700 mb-2">{{ __('dsgvo.admin_editor_tab') }}</label>
                <textarea
                    id="markdown-editor"
                    x-ref="editor"
                    x-model="markdown"
                    @input.debounce.500ms="fetchPreview()"
                    rows="20"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                ></textarea>
            </div>

            {{-- Preview --}}
            <div class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">{{ __('dsgvo.admin_preview_tab') }}</span>
                    <span x-show="loading" x-transition class="text-xs text-gray-400">…</span>
                </div>
                <div class="prose prose-sm max-w-none border border-gray-200 rounded-md p-4 min-h-[20rem] overflow-y-auto" x-html="previewHtml"></div>
            </div>
        </div>

        {{-- Save form --}}
        <div class="border-t border-gray-200 p-4 bg-gray-50">
            <form method="POST" action="{{ route('admin.dsgvo.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="markdown" :value="markdown">

                <div class="flex items-start gap-4 mb-4">
                    <div class="flex items-center h-5">
                        <input id="substantial_change" name="substantial_change" type="checkbox" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="substantial_change" class="text-sm font-medium text-gray-700">{{ __('dsgvo.admin_substantial_label') }}</label>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('dsgvo.admin_substantial_helper') }}</p>
                    </div>
                </div>

                @error('markdown')
                    <p class="text-sm text-red-600 mb-3">{{ $message }}</p>
                @enderror

                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('dsgvo.admin_save') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Confirmation history link --}}
    @if (Route::has('admin.dsgvo.confirmations'))
    <div class="mt-4">
        <a href="{{ route('admin.dsgvo.confirmations') }}" class="text-sm text-blue-600 hover:text-blue-800 underline">
            {{ __('dsgvo.admin_confirmations_link') }} &rarr;
        </a>
    </div>
    @endif

    <script>
    function dsgvoEditor() {
        return {
            markdown: @json($markdown),
            previewHtml: @json($previewHtml),
            loading: false,
            debounceTimer: null,

            fetchPreview() {
                this.loading = true;
                fetch('{{ route('admin.dsgvo.preview') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'text/html',
                    },
                    body: JSON.stringify({ markdown: this.markdown }),
                })
                .then(r => r.text())
                .then(html => { this.previewHtml = html; })
                .finally(() => { this.loading = false; });
            },

            insertSyntax(before, after) {
                const el = this.$refs.editor;
                const start = el.selectionStart;
                const end = el.selectionEnd;
                const selected = this.markdown.substring(start, end);
                const replacement = before + (selected || 'text') + after;
                this.markdown = this.markdown.substring(0, start) + replacement + this.markdown.substring(end);
                this.$nextTick(() => {
                    el.focus();
                    if (selected) {
                        el.setSelectionRange(start + before.length, start + before.length + selected.length);
                    } else {
                        el.setSelectionRange(start + before.length, start + before.length + 4);
                    }
                    this.fetchPreview();
                });
            },

            insertPrefix(prefix) {
                const el = this.$refs.editor;
                const start = el.selectionStart;
                const lineStart = this.markdown.lastIndexOf('\n', start - 1) + 1;
                this.markdown = this.markdown.substring(0, lineStart) + prefix + this.markdown.substring(lineStart);
                this.$nextTick(() => {
                    el.focus();
                    el.setSelectionRange(start + prefix.length, start + prefix.length);
                    this.fetchPreview();
                });
            },

            insertLink() {
                const el = this.$refs.editor;
                const start = el.selectionStart;
                const end = el.selectionEnd;
                const selected = this.markdown.substring(start, end);
                const linkText = selected || 'Link-Text';
                const replacement = '[' + linkText + '](url)';
                this.markdown = this.markdown.substring(0, start) + replacement + this.markdown.substring(end);
                this.$nextTick(() => {
                    el.focus();
                    const urlStart = start + linkText.length + 3;
                    el.setSelectionRange(urlStart, urlStart + 3);
                    this.fetchPreview();
                });
            },
        };
    }
    </script>
</x-admin-layout>

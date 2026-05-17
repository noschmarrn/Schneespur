<x-admin-layout>
    <x-slot name="header">{{ __('dsgvo.admin_snapshot_title') }}: {{ $confirmation->driver?->displayName() ?? '—' }} <x-help-icon topic="dsgvo" /></x-slot>

    {{-- Breadcrumb --}}
    <nav class="mb-4 text-sm text-gray-500" data-print-hide>
        <a href="{{ route('admin.dsgvo.index') }}" class="hover:text-gray-700">DSGVO</a>
        <span class="mx-1">&rsaquo;</span>
        <a href="{{ route('admin.dsgvo.confirmations') }}" class="hover:text-gray-700">{{ __('dsgvo.admin_confirmations_heading') }}</a>
        <span class="mx-1">&rsaquo;</span>
        <span class="text-gray-700">{{ __('dsgvo.admin_snapshot_title') }}</span>
    </nav>

    {{-- Metadata card --}}
    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 mb-6" id="confirmation-meta">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="font-medium text-gray-500">{{ __('dsgvo.admin_snapshot_meta_driver') }}</dt>
                <dd class="mt-1 text-gray-900">{{ $confirmation->driver?->displayName() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">{{ __('dsgvo.admin_snapshot_meta_date') }}</dt>
                <dd class="mt-1 text-gray-900">{{ $confirmation->confirmed_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i:s') }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">{{ __('dsgvo.admin_snapshot_meta_version') }}</dt>
                <dd class="mt-1 text-gray-900">{{ $confirmation->template_version }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">{{ __('dsgvo.admin_snapshot_meta_ip') }}</dt>
                <dd class="mt-1 text-gray-900">{{ $confirmation->ip_address ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="font-medium text-gray-500">{{ __('dsgvo.admin_snapshot_meta_useragent') }}</dt>
                <dd class="mt-1 text-gray-900 break-all">{{ $confirmation->user_agent ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Divider --}}
    <hr class="my-6 border-gray-200">

    {{-- Rendered snapshot --}}
    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6" id="confirmation-snapshot">
        <div class="prose prose-sm max-w-none">
            {!! $snapshotHtml !!}
        </div>
    </div>

    {{-- Print button --}}
    <div class="mt-4" data-print-hide>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('dsgvo.admin_print') }}
        </button>
    </div>

    <style>
        @media print {
            nav[data-print-hide],
            [data-print-hide],
            aside,
            .sidebar,
            header nav,
            [x-data] nav {
                display: none !important;
            }

            #confirmation-meta,
            #confirmation-snapshot {
                box-shadow: none !important;
                border: none !important;
            }

            #confirmation-snapshot .prose {
                font-family: Georgia, 'Times New Roman', serif;
            }

            body {
                background: white !important;
            }
        }
    </style>
</x-admin-layout>

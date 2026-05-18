<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $moduleName }} — Settings
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">Module Information</h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $moduleName }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Version</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $version }}</dd>
                        </div>
                    </dl>
                    <p class="mt-6 text-sm text-gray-600">
                        This is a reference module demonstrating all Schneespur extension points:
                        navigation, dashboard widget, event listener, settings page, and route registration.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

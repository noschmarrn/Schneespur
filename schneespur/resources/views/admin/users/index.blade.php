<x-admin-layout>
    <x-slot name="header">{{ __('user.heading_index') }}</x-slot>

    <div class="flex items-center justify-end">
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('user.button_create') }}
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-4">
        <x-text-input name="search" :value="request('search')" :placeholder="__('ui.search_placeholder')" class="w-full sm:w-64" />
    </form>

    <div class="mt-6">
        @if ($users->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('user.col_name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('user.col_email') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('user.col_roles') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('user.col_created') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('user.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @foreach ($user->roles as $role)
                                            <span class="inline-flex items-center bg-blue-100 text-blue-800 rounded-full px-2 py-1 text-xs mr-1">{{ $role->name }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->created_at->format('d.m.Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('ui.button_edit') }}</a>

                                        <button x-data x-on:click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')" type="button" class="ml-3 text-red-600 hover:text-red-900">
                                            {{ __('ui.button_delete') }}
                                        </button>

                                        <x-confirm-dialog
                                            :name="'delete-user-' . $user->id"
                                            :title="__('user.confirm_delete_title')"
                                            :message="__('user.confirm_delete_body', ['name' => $user->name])"
                                        >
                                            <x-slot name="action">
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-danger-button>{{ __('ui.button_delete') }}</x-danger-button>
                                                </form>
                                            </x-slot>
                                        </x-confirm-dialog>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        @else
            <x-empty-state :heading="__('user.empty_title')" :body="__('user.empty_body')">
                <x-slot name="action">
                    <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('user.button_create') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @endif
    </div>
</x-admin-layout>

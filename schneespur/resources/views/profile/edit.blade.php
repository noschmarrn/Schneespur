<x-admin-layout>
    <x-slot name="header">{{ __('profile.page_title') }}</x-slot>

    <div class="max-w-2xl space-y-6">
        <div class="bg-white shadow-sm rounded-lg p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6">
            @include('profile.partials.update-password-form')
        </div>

        @php $isOnlyAdmin = auth()->user()->isAdmin() && \App\Models\User::admins()->count() <= 1; @endphp
        @unless($isOnlyAdmin)
        <div class="bg-white shadow-sm rounded-lg p-6">
            @include('profile.partials.delete-user-form')
        </div>
        @endunless
    </div>
</x-admin-layout>

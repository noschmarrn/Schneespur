<x-mail::message>
{{ __('portal.email_changed_admin_line1', ['name' => $customer->name]) }}

{{ __('portal.email_changed_admin_line2', ['old' => $oldEmail, 'new' => $newEmail]) }}

{{ __('notification.regards') }}
</x-mail::message>

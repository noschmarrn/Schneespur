<x-mail::message>
{{ __('notification.greeting', ['name' => $customer->contact_name ?? $customer->name]) }}

@if($isReset)
{{ __('notification.portal_credentials_reset_body') }}
@else
{{ __('notification.portal_credentials_body') }}
@endif

**{{ __('notification.portal_credentials_email_label') }}** {{ $customer->email }}
**{{ __('notification.portal_credentials_password_label') }}** {{ $password }}

{{ __('notification.portal_credentials_change_hint') }}

{{ __('notification.regards') }}
</x-mail::message>

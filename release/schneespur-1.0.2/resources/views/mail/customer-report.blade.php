<x-mail::message>
@php $mailContact = $customerObject ?? $customer; @endphp
{{ __('notification.greeting', ['name' => $mailContact->contact_name ?? $mailContact->name ?? $customer->name]) }}

@if($customerObject)
{{ __('notification.customer_report_object_body', [
    'object' => $customerObject->name,
    'from' => $from->format(__('notification.date_format')),
    'to' => $to->format(__('notification.date_format')),
]) }}
@else
{{ __('notification.customer_report_body', [
    'from' => $from->format(__('notification.date_format')),
    'to' => $to->format(__('notification.date_format')),
]) }}
@endif

@if($pdfAttached)
{{ __('notification.customer_report_pdf_attached') }}
@endif

{{ __('notification.regards') }}
</x-mail::message>

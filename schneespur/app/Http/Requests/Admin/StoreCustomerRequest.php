<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'auto_notify_email' => ['boolean'],
            'notification_email' => ['nullable', 'required_if:auto_notify_email,1', 'email', 'max:200'],
            'locale' => ['sometimes', \Illuminate\Validation\Rule::in(app(\App\Services\Extension\LocaleRegistry::class)->codes())],
        ];
    }

    public function messages(): array
    {
        return [
            'notification_email.required_if' => __('customer.validation_notification_email_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('customer.field_name'),
            'contact_name' => __('customer.field_contact_name'),
            'email' => __('customer.field_email'),
            'phone' => __('customer.field_phone'),
            'auto_notify_email' => __('customer.field_auto_notify'),
            'notification_email' => __('customer.field_notification_email'),
            'locale' => __('customer.field_locale'),
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('price_amount') && is_string($this->price_amount)) {
            $this->merge([
                'price_amount' => str_replace(',', '.', $this->price_amount),
            ]);
        }

        // notify_recipients is a mode enum consumed as customer|object|both
        // (see SendJobCompletedNotification / SendCustomerReportEmail).
        // Fall back to the DB default when the field is missing or empty
        // so the NOT NULL column never receives a null value.
        if (! $this->filled('notify_recipients')) {
            $this->merge(['notify_recipients' => 'customer']);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'street' => ['nullable', 'string', 'max:200'],
            'zip' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['nullable', 'string', 'in:per_job,monthly,seasonal'],
            'site_notes' => ['nullable', 'string'],
            'plow_threshold_cm' => ['nullable', 'integer', 'min:0', 'max:255'],
            'salt_enabled' => ['boolean'],
            'access_notes' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'auto_notify_email' => ['boolean'],
            'notification_email' => ['nullable', 'required_if:auto_notify_email,1', 'email', 'max:200'],
            'notify_recipients' => ['required', 'in:customer,object,both'],
        ];
    }

    public function messages(): array
    {
        return [
            'notification_email.required_if' => __('customer_object.validation_notification_email_required'),
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key === null && isset($data['price_amount'])) {
            $data['price_amount_cents'] = (int) round((float) $data['price_amount'] * 100);
            unset($data['price_amount']);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('customer_object.field_name'),
            'street' => __('customer_object.field_street'),
            'zip' => __('customer_object.field_zip'),
            'city' => __('customer_object.field_city'),
            'contact_name' => __('customer_object.field_contact_name'),
            'contact_email' => __('customer_object.field_contact_email'),
            'contact_phone' => __('customer_object.field_contact_phone'),
            'price_amount' => __('customer_object.field_price_amount'),
            'price_unit' => __('customer_object.field_price_unit'),
            'site_notes' => __('customer_object.field_site_notes'),
            'plow_threshold_cm' => __('customer_object.field_plow_threshold'),
            'salt_enabled' => __('customer_object.field_salt_enabled'),
            'access_notes' => __('customer_object.field_access_notes'),
            'lat' => __('customer_object.field_lat'),
            'lon' => __('customer_object.field_lon'),
            'auto_notify_email' => __('customer_object.field_auto_notify'),
            'notification_email' => __('customer_object.field_notification_email'),
            'notify_recipients' => __('customer_object.field_notify_recipients'),
        ];
    }
}

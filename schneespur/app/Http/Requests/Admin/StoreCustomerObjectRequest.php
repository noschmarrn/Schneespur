<?php

namespace App\Http\Requests\Admin;

use App\Services\Format\Formatter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
            'state' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'price_amount' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $parsed = app(Formatter::class)->parseDecimal((string) $value);

                    if ($parsed === null || $parsed < 0) {
                        $fail(__('customer_object.validation_price_amount_invalid'));
                    }
                },
            ],
            'price_unit' => ['nullable', 'string', Rule::in(\App\Services\Pricing\JobPricing::UNITS)],
            'prices' => ['sometimes', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                $allowed = $this->allowedPriceJobTypes();

                foreach (array_keys((array) $value) as $jobType) {
                    if (! in_array((string) $jobType, $allowed, true)) {
                        $fail(__('customer_object.validation_price_job_type_unknown', ['type' => (string) $jobType]));
                    }
                }
            }],
            'prices.*.amount' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $parsed = app(Formatter::class)->parseDecimal((string) $value);

                    if ($parsed === null || $parsed < 0) {
                        $fail(__('customer_object.validation_price_amount_invalid'));
                    }
                },
            ],
            'prices.*.unit' => ['nullable', 'string', Rule::in(\App\Services\Pricing\JobPricing::UNITS)],
            'site_notes' => ['nullable', 'string'],
            // Textfeld, nicht 'numeric': parseDecimal liest "5,1" im deutschen
            // und "5.1" im englischen Zahlenformat. Genau wie price_amount
            // zwanzig Zeilen weiter oben.
            'plow_threshold' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $parsed = app(Formatter::class)->parseDecimal((string) $value);

                    if ($parsed === null || $parsed < 0 || $parsed > 999.99) {
                        $fail(__('customer_object.validation_plow_threshold_invalid'));
                    }
                },
            ],
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

    /**
     * Registrierte Einsatzarten, dazu die, für die dieses Objekt schon eine
     * Zeile hat — eine verwaiste Zeile (Modul entfernt) muß speicherbar
     * bleiben, sonst wäre sie über das Formular nicht einmal löschbar.
     *
     * @return string[]
     */
    private function allowedPriceJobTypes(): array
    {
        $registered = app(\App\Services\Extension\JobTypeRegistry::class)->values();
        $object = $this->route('object');

        $existing = $object instanceof \App\Models\CustomerObject
            ? $object->prices()->pluck('job_type')->all()
            : [];

        return array_values(array_unique(array_merge($registered, $existing)));
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            foreach ((array) $this->input('prices', []) as $jobType => $row) {
                $amount = trim((string) ($row['amount'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));

                if ($amount !== '' && $unit === '') {
                    $validator->errors()->add(
                        "prices.$jobType.unit",
                        __('customer_object.validation_price_unit_required'),
                    );
                }
            }
        });
    }

    /**
     * Die Preiszeilen, fertig für CustomerObject::syncPrices(). Ein leerer
     * Betrag wird zu null ("Grundpreis gilt" → Zeile löschen).
     *
     * @return array<string, array{amount_cents:int, unit:string}|null>
     */
    public function priceRows(): array
    {
        $rows = [];

        foreach ((array) $this->input('prices', []) as $jobType => $row) {
            $amount = trim((string) ($row['amount'] ?? ''));

            $rows[(string) $jobType] = $amount === ''
                ? null
                : [
                    'amount_cents' => (int) app(Formatter::class)->parseMoneyToCents($amount),
                    'unit' => (string) $row['unit'],
                ];
        }

        return $rows;
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key === null) {
            // Eigene Tabelle, nicht Spalten des Objekts — siehe priceRows().
            unset($data['prices']);
        }

        if ($key === null && array_key_exists('price_amount', $data)) {
            $data['price_amount_cents'] = app(Formatter::class)
                ->parseMoneyToCents((string) $data['price_amount']);
            unset($data['price_amount']);
        }

        if ($key === null && array_key_exists('plow_threshold', $data)) {
            $parsed = app(Formatter::class)->parseDecimal((string) $data['plow_threshold']);

            $data['plow_threshold_value'] = $parsed;
            // Keine Einheit ohne Wert: 'cm' bei leerem Feld stünde für eine
            // Angabe, die niemand gemacht hat.
            $data['plow_threshold_unit'] = $parsed === null ? null : app(Formatter::class)->snowUnit();
            unset($data['plow_threshold']);
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
            'state' => __('customer_object.field_state'),
            'contact_name' => __('customer_object.field_contact_name'),
            'contact_email' => __('customer_object.field_contact_email'),
            'contact_phone' => __('customer_object.field_contact_phone'),
            'price_amount' => __('customer_object.field_price_amount'),
            'price_unit' => __('customer_object.field_price_unit'),
            'site_notes' => __('customer_object.field_site_notes'),
            'plow_threshold' => __('customer_object.field_plow_threshold', ['unit' => app(Formatter::class)->snowUnitLabel()]),
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

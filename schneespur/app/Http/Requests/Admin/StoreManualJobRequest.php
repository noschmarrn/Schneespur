<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A datetime-local field sends "2026-01-15T08:30" with no zone at all.
     * Carbon::parse() would put config('app.timezone') on that — UTC — so an
     * operator in Berlin who typed 08:30 got 08:30 UTC stored and 09:30 back.
     *
     * Converting here rather than in the controller is the point: this runs
     * before the rules, so 'before_or_equal:now' compares two values that are
     * genuinely in the same zone. Left naive, that boundary moved with the
     * offset — in Chicago it accepted jobs six hours into the future.
     */
    protected function prepareForValidation(): void
    {
        $zone = (string) config('app.display_timezone', 'UTC');

        $converted = [];

        foreach (['started_at', 'ended_at'] as $field) {
            $value = $this->input($field);

            if (! is_string($value) || $value === '') {
                continue;
            }

            try {
                $converted[$field] = \Carbon\CarbonImmutable::parse($value, $zone)
                    ->utc()
                    ->toIso8601String();
            } catch (\Throwable) {
                // Leave it untouched — the 'date' rule then reports it properly,
                // which is a far better answer than an exception page.
            }
        }

        if ($converted !== []) {
            $this->merge($converted);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Check the many-to-many role pivot, not the legacy users.role column.
            // The driver dropdown beside this field is filled from User::drivers(),
            // which reads the pivot — and users.role defaults to 'driver' for every
            // row created since M014 moved roles out of it, so the old rule waved
            // through any user id at all (vuln-0002).
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->whereIn('id', User::drivers()->select('id')),
            ],
            'customer_object_id' => ['required', 'exists:customer_objects,id'],
            'type' => ['required', Rule::in(app(\App\Services\Extension\JobTypeRegistry::class)->values())],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'ended_at' => ['required', 'date', 'after:started_at', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('job.field_driver'),
            'customer_object_id' => __('job.field_object'),
            'type' => __('job.field_type'),
            'started_at' => __('job.field_started_at'),
            'ended_at' => __('job.field_ended_at'),
            'notes' => __('job.field_notes'),
            'internal_notes' => __('job.field_internal_notes'),
            'vehicle_id' => __('job.field_vehicle'),
        ];
    }
}

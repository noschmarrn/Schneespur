<?php

namespace App\Http\Requests\Driver;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_object_id' => ['required', 'exists:customer_objects,id'],
            'type' => ['required', Rule::in(app(\App\Services\Extension\JobTypeRegistry::class)->values())],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'ended_at' => ['required', 'date', 'after:started_at', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_object_id' => __('job.field_object'),
            'type' => __('job.field_type'),
            'started_at' => __('job.field_started_at'),
            'ended_at' => __('job.field_ended_at'),
            'notes' => __('job.field_notes'),
            'vehicle_id' => __('job.field_vehicle'),
        ];
    }
}

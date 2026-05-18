<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
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
            'license_plate' => ['nullable', 'string', 'max:32'],
            'owntracks_device_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('vehicles', 'owntracks_device_id')->ignore($this->route('vehicle')),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('vehicle.field_name'),
            'license_plate' => __('vehicle.field_license_plate'),
            'owntracks_device_id' => __('vehicle.field_owntracks_device_id'),
            'notes' => __('vehicle.field_notes'),
        ];
    }
}

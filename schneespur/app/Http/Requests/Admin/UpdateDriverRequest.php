<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:200', Rule::unique('users')->ignore($this->route('driver'))],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'default_vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('driver.field_name'),
            'email' => __('driver.field_email'),
            'password' => __('driver.field_password'),
            'phone' => __('driver.field_phone'),
            'notes' => __('driver.field_notes'),
            'default_vehicle_id' => __('driver.field_default_vehicle'),
        ];
    }
}

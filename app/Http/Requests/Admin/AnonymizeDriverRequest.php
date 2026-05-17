<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AnonymizeDriverRequest extends FormRequest
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
            'confirmation_name' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $driver = $this->route('driver');

            if ($driver && $this->input('confirmation_name') !== $driver->name) {
                $validator->errors()->add(
                    'confirmation_name',
                    __('validation.same', ['attribute' => 'confirmation_name', 'other' => __('driver.field_name')])
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'confirmation_name' => __('driver.modal_anonymize_confirm_label'),
            'reason' => __('driver.modal_anonymize_reason_label'),
        ];
    }
}

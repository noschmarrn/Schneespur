<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmDsgvoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accepted' => 'required|accepted',
            'signed_by' => 'required|string|max:200',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $signed = mb_strtolower($this->input('signed_by'));
            $expected = mb_strtolower($this->user()->name);

            if ($signed !== $expected) {
                $validator->errors()->add(
                    'signed_by',
                    __('dsgvo.validation_name_mismatch'),
                );
            }
        });
    }
}

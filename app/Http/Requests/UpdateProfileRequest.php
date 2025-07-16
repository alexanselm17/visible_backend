<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fullname' => 'sometimes|string',
            'username' => [
                'sometimes',
                'string',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],
            'phone' => [
                'sometimes',
                'regex:/^\+254\d{9}$/',
                Rule::unique('users', 'phone')->ignore($this->user()->id),
            ],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'national_id' => [
                'sometimes',
                'string',
                Rule::unique('users', 'national_id')->ignore($this->user()->id),
            ],
            'password' => 'sometimes|confirmed|min:8',
        ];
    }
}

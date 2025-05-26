<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignUp extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'fullname' => 'required|string',
            'username' => 'required|unique:users,username',
            'phone' => [
                'required',
                'regex:/^\+254\d{9}$/',
                Rule::unique('users', 'phone'),
            ],
            'email' => 'required|email|unique:users,email',
            'national_id' => 'required|unique:users,national_id',
            'password' => 'required|confirmed|min:8',
            'occupation'=>'required|string',
            'location'=>'required|string',
            'gender' => 'required|in:Male,Female',
        ];
    }
}

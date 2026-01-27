<?php

namespace App\Http\Requests\CampaignOwner;

use Illuminate\Foundation\Http\FormRequest;

class CreateCampaignOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:20', 'unique:users,phone'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'subcounty_id' => ['nullable', 'uuid', 'exists:sub_counties,id'],
            'password' => ['required', 'string', 'min:6'],

            'business_name' => ['required', 'string', 'max:255'],
            'business_category' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}

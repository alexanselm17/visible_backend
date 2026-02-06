<?php

namespace App\Http\Requests\CampaignOwner;

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'set_primary' => ['nullable', 'boolean'],
        ];
    }
}

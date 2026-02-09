<?php

namespace App\Http\Requests\CampaignOwner;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAdvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'capital_invested' => ['required', 'numeric', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'target_audience' => ['nullable', 'json'],


            // Multiple originals:
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:10240',

            'videos' => 'nullable|array',
            'videos.*' => 'file|mimes:mp4,mov,avi,webm|max:51200',
        ];
    }
}

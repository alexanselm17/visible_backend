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
            'image' => ['required', 'image', 'max:5120'],
            'video' => ['nullable', 'file', 'max:20480'],
        ];
    }
}

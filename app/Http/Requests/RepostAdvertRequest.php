<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RepostAdvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'advert_id' => ['required', 'uuid', 'exists:advert_images,id'],

            'name' => ['nullable', 'string', 'max:255'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'badge' => ['nullable', 'array'],
            'target_audience' => ['nullable', 'array'],
            'campaign_id' => ['nullable', 'uuid'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }
}

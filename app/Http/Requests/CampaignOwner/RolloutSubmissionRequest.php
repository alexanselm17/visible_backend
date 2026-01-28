<?php

namespace App\Http\Requests\CampaignOwner;

use Illuminate\Foundation\Http\FormRequest;

class RolloutSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'        => 'nullable|string|max:100',
            'badge'           => 'nullable|string|max:100',
            'valid_until'     => 'nullable|date',
            'capacity'        => 'nullable|integer|min:0',
            'reward'          => 'nullable|numeric|min:0',
            'capital_invested' => 'nullable|numeric|min:0',
        ];
    }
}

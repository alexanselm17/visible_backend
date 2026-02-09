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
            'badge' => 'required|array',
            'badge.*' => 'string',
            'valid_until'     => 'required|date',
            'capacity'        => 'required|integer|min:0',
            'reward'          => 'required|numeric|min:0',
            'capital_invested' => 'required|numeric|min:0',
            'description'     => 'required|string',
        ];
    }
}

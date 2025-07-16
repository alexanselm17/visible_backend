<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StartCampaignRequest extends FormRequest
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
    public function rules()
    {
        // Calculate minimum datetime (24 hours from now in Africa/Nairobi timezone)
        $minDateTime = Carbon::now('Africa/Nairobi')->addDay()->format('Y-m-d H:i:s');

        return [
            'name' => 'required|string|max:255',
            'capital_invested' => 'required|numeric|min:0',
            'valid_until' => ['required', 'date', "before_or_equal:$minDateTime"],
            'reward' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
        ];
    }
}

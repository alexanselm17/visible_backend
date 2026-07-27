<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ProductAdvertRequest extends FormRequest
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
        // Calculate minimum datetime (24 hours from now in Africa/Nairobi timezone)
        $minDateTime = Carbon::now('Africa/Nairobi')->addDay()->format('Y-m-d H:i:s');

        return [
            'image' => 'required|file|mimes:jpeg,png,jpg|max:20480',
            'name' => 'required|string',
            'category' => 'required|string',
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:20480',
            'badge' => 'required|array',
            'badge.*' => 'string',
            'capital_invested' => 'required|numeric|min:0',
            'valid_until' => ['required', 'date', "before_or_equal:$minDateTime"],
            'reward' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'type' => ['required', 'string', 'in:ads,image'],
        ];
    }
}

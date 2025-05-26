<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftDiscount extends FormRequest
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
        return [
            "amount"=>"required",
            'customer_id' => 'required|string|exists:customers,id',
            "posted_by"=>'required|exists:App\Models\User,id',
            'petrol_id' => 'nullable|exists:petrol_station,id',
            'total_purchased'=>'required|numeric'
        ];
    }
}

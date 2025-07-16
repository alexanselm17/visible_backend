<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartPumpSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'pump_id' => 'required|exists:App\Models\Pump,id',
            "processed_by"=> 'exists:App\Models\User,id',
            "assigned_to"=> 'exists:App\Models\User,id',
            "ended_volume"=>"numeric",
            "ended_cash"=>"numeric",
        ];
    }
}

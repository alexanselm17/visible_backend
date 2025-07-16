<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StationSessionDetails extends FormRequest
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
            'station_id' => 'required|exists:stations,id',
            'user_id' => 'required|exists:App\Models\User,id',
            'shift_id' => 'required|exists:shifts,id',
            "petrol_id" => "required|exists:App\Models\PetrolStation,id",
        ];
    }
}

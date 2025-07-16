<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignStation extends FormRequest
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
            'assigned_to' => 'required|exists:App\Models\User,id',
            'assigned_by' => 'required|exists:App\Models\User,id',
            'station_id' => 'required|exists:App\Models\Stations,id',
        ];
    }
}

<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockReport extends FormRequest
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
            'drum_id' => 'nullable|exists:drums,id', 
            'station_id' => 'nullable|exists:stations,id',
            'petrol_id' => 'exists:petrol_station,id',
         ];
    }

    /**
     * Add custom validation logic to ensure either drum_id or station_id is present.
     *
     * @return array<string, mixed>
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->filled('drum_id') && !$this->filled('station_id')) {
                $validator->errors()->add('drum_id', 'Either drum_id or station_id must be provided.');
                $validator->errors()->add('station_id', 'Either drum_id or station_id must be provided.');
            }
        });
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, mixed>
     */
    public function messages()
    {
        return [
            'drum_id.exists' => 'The selected drum does not exist.',
            'station_id.exists' => 'The selected station does not exist.',
        ];
    }
}

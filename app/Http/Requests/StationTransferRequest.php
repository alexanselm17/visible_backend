<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StationTransferRequest extends FormRequest
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
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:App\Models\ProductsModel,id',
            'products.*.quantity' => 'required|numeric',
            'processed_by' => 'required|exists:App\Models\User,id',
            'to_station' => 'required|exists:App\Models\Stations,id',
            'from_station' => 'required|exists:App\Models\Stations,id',
        ];
    }
}

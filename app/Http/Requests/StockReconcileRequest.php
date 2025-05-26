<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockReconcileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'quantity' => 'required|numeric',
            'product_id' => 'required|exists:products,id',
        ];
    }


}

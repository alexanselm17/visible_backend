<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditSalesRequest extends FormRequest
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
            "posted_by"=> 'required|exists:App\Models\User,id',
            "customer_id"=> 'required|exists:App\Models\Customers,id',
            "transaction"=> 'required|exists:App\Models\Transaction,id',
            "invoice_note"=>"required|string",
            "amount"=>"required|numeric",
        ];
    }
}

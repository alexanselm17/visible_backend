<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesRequest extends FormRequest
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
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.name' => 'required|string',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.discount' => 'required|numeric',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.total' => 'required|numeric|min:0',

            // Payment array validation
            'payment' => 'required|array|min:1',
            'payment.*.id' => 'required|exists:sys_metas,id',
            'payment.*.name' => [
                'required',
                'string',
                'exists:sys_metas,meta_value'
            ],
            'payment.*.amount' => 'required|numeric|min:0',
            'payment.*.invoice_note' => 'required_if:payment.*.name,Invoice|string',
            'payment.*.reference' => 'required_unless:payment.*.name,Cash,Invoice|string',

            // Other required fields
            'user_id' => 'required|exists:users,id',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $paymentMethods = $this->input('payment', []);

            // Check if any payment method is 'Invoice'
            $hasInvoice = collect($paymentMethods)->contains(function ($payment) {
                return $payment['name'] === 'Invoice';
            });

            // If there's an 'Invoice' payment method, ensure customer_id is present
            if ($hasInvoice && !$this->has('customer_id')) {
                $validator->errors()->add('customer_id', 'The customer_id field is required when payment method is Invoice.');
            }
        });
    }

    /**
     * Custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'products.required' => 'At least one product is required.',
            'products.*.id.required' => 'Each product must have an ID.',
            'products.*.name.required' => 'Each product must have a name.',
            'products.*.quantity.required' => 'Each product must have a quantity.',
            'products.*.price.required' => 'Each product must have a price.',
            'products.*.total.required' => 'Each product must have a total.',
            'payment.required' => 'At least one payment method is required.',
            'payment.*.name.required' => 'Each payment must have a name.',
            'payment.*.amount.required' => 'Each payment must have an amount.',
            'payment.*.invoice_note.required_if' => 'Invoice note is required when payment is Invoice.',
            'payment.*.reference.required_unless' => 'A reference is required unless the payment is Cash.',
            'customer_id.required_if' => 'Customer ID is required for invoice payments.',
        ];
    }
}

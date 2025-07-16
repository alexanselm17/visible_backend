<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetBankingRequest extends FormRequest
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
             'shift_id' => 'required|exists:shifts,id',
             'banking_id' => 'required|exists:bankings,id'
        ];
    }
}

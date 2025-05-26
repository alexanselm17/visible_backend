<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTransfer extends FormRequest
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
            'processed_by' => 'required|exists:App\Models\User,id',
            'transfer_id' => 'required|exists:App\Models\Transfer,id',
          'transfer_status' => 'required|in:1,2',

        ];
    }
}

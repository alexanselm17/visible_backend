<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductAdvertRequest extends FormRequest
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
            'image' => 'required|file|mimes:jpeg,png,jpg|max:20480', 
            'name' => 'required|string',
            'category' => 'required|string',
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:20480',
            'badge' => 'required|array',
            'badge.*' => 'string'
        ];
    }
}

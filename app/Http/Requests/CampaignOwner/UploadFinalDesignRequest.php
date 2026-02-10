<?php

namespace App\Http\Requests\CampaignOwner;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinalDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'final_image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',

            'final_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:51200',

            'thumbnail_image' => 'required_with:final_video|file|mimes:jpg,jpeg,png,webp|max:10240',

            'designer_id' => 'required|uuid|exists:users,id',
        ];
    }
}

<?php

namespace App\Http\Requests\CampaignOwner;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAdvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('type')) {
            $this->merge([
                'type' => strtoupper((string) $this->input('type')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'target_audience' => ['nullable', 'json'],
            'target_reach' => ['required', 'integer', 'min:1', 'max:1000000'],
            'type' => ['required', 'string', 'in:VIDEO,IMAGE,TEXT'],
            'video_duration_seconds' => ['required_if:type,VIDEO', 'nullable', 'integer', 'min:1'],

            'images' => ['required_if:type,IMAGE', 'nullable', 'array', 'min:1', 'max:1'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],

            'videos' => ['required_if:type,VIDEO', 'nullable', 'array', 'min:1', 'max:1'],
            'videos.*' => ['file', 'mimes:mp4,mov,avi,webm', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_reach.required' => 'Enter the number of people you want this advert to reach.',
            'video_duration_seconds.required_if' => 'Video duration is required to calculate Gold token usage.',
            'images.required_if' => 'Upload an image for an image campaign.',
            'images.max' => 'An image advert currently supports one image per submission.',
            'videos.required_if' => 'Upload a video for a video campaign.',
            'videos.max' => 'A video advert currently supports one video per submission.',
        ];
    }
}

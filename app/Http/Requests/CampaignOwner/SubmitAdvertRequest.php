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
            'credits' => ['required', 'numeric', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'target_audience' => ['nullable', 'json'],
            'type' => ['required', 'string', 'in:VIDEO,IMAGE,TEXT'],
            'video_duration_seconds' => ['required_if:type,VIDEO', 'nullable', 'integer', 'min:1'],
            'images' => ['required_if:type,IMAGE', 'nullable', 'array', 'min:1'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'videos' => ['required_if:type,VIDEO', 'nullable', 'array', 'min:1'],
            'videos.*' => ['file', 'mimes:mp4,mov,avi,webm', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'video_duration_seconds.required_if' => 'Video duration is required for Gold token calculation.',
            'images.required_if' => 'Upload at least one image for an image campaign.',
            'videos.required_if' => 'Upload at least one video for a video campaign.',
        ];
    }
}

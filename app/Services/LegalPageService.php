<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class LegalPageService
{
    public function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)->withMaxInputLength(1000000)->allowRelativeLinks()->allowLinkSchemes(['https', 'mailto', 'tel']);
        foreach (['p', 'h2', 'h3', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'blockquote', 'br'] as $tag) {
            $config = $config->allowElement($tag);
        }
        $config = $config->allowElement('a', ['href'])->forceAttribute('a', 'rel', 'noopener noreferrer');

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    public function saveSettings(array $data): SiteSetting
    {
        abort_unless(AdminAccess::allows('settings'), 403);
        $data = Validator::make($data, [
            'app_name' => 'required|string|min:2|max:100',
            'operator_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email:rfc|max:254',
            'privacy_email' => 'nullable|email:rfc|max:254',
            'support_phone' => 'nullable|string|max:40',
            'postal_address' => 'nullable|string|max:1000',
            'logo_path' => ['nullable', 'regex:#^branding/[a-zA-Z0-9_-]+\.(png|jpe?g|webp)$#i'],
            'accessibility_mode' => ['required', Rule::in(['unconfirmed', 'none', 'whatsapp_status', 'other'])],
            'accessibility_disclosure' => 'nullable|string|max:6000',
            'retention_policy' => 'nullable|string|max:10000',
        ])->validate();
        $settings = SiteSetting::current();
        $settings->fill($data);
        if ($settings->logo_path && ! $settings->logoLocalPath()) {
            throw ValidationException::withMessages(['logo_path' => 'Upload a valid branding image before saving.']);
        }
        if ($path = $settings->logoLocalPath()) {
            $image = @getimagesize($path);
            if (! $image || ! in_array($image['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
                || max($image[0], $image[1]) > 4096 || filesize($path) > 2 * 1024 * 1024) {
                throw ValidationException::withMessages(['logo_path' => 'The logo must be a PNG, JPEG or WebP image up to 2 MB and 4096 px.']);
            }
        }
        // Changing the identity/disclosures after publication must not blank required facts.
        if (LegalDocument::whereNotNull('published_at')->exists()) {
            $this->validateIdentity($settings);
        }
        $settings->updated_by = auth()->id();
        $settings->save();

        return $settings;
    }

    public function publish(LegalDocument $document, ?string $effectiveOn = null): void
    {
        abort_unless(AdminAccess::allows('settings'), 403);
        DB::transaction(function () use ($document, $effectiveOn) {
            $document = LegalDocument::lockForUpdate()->findOrFail($document->id);
            $settings = SiteSetting::current();
            $this->validateIdentity($settings);
            $data = $document->toArray();
            // This is a calendar date, not the UTC timestamp produced by toArray().
            $data['effective_on'] = $effectiveOn ?? $document->effective_on?->toDateString();
            $validated = Validator::make($data, [
                'title' => 'required|string|max:255',
                'effective_on' => 'required|date|before_or_equal:today',
                'draft_content' => 'required|string|max:150000',
            ], [
                'effective_on.required' => 'Choose an effective date before publishing this policy.',
                'effective_on.date' => 'Choose a valid effective date before publishing this policy.',
                'effective_on.before_or_equal' => 'The effective date cannot be in the future.',
            ])->validate();
            // Save the selected date in the same transaction as its published snapshot.
            $document->effective_on = $validated['effective_on'];
            if ($effectiveOn !== null) {
                $document->updated_by = auth()->id();
            }
            $content = $this->render($document->draft_content, $settings);
            if (mb_strlen(trim(strip_tags($content))) < 250 || preg_match('/\[insert\b|\[your\b|\[app\/company|\{\{/i', $content)) {
                throw ValidationException::withMessages(['draft_content' => 'Replace placeholders and provide the complete policy before publishing.']);
            }
            $version = $document->version + 1;
            $document->revisions()->create([
                'version' => $version, 'title' => $document->title, 'content' => $content,
                'effective_on' => $document->effective_on, 'identity_snapshot' => $settings->only([
                    'app_name', 'operator_name', 'support_email', 'privacy_email', 'support_phone', 'postal_address',
                    'accessibility_mode', 'accessibility_disclosure', 'retention_policy',
                ]), 'published_by' => auth()->id(), 'created_at' => now(),
            ]);
            $document->update([
                'published_title' => $document->title, 'published_content' => $content,
                'published_effective_on' => $document->effective_on, 'version' => $version,
                'published_at' => now(), 'published_by' => auth()->id(),
            ]);
        });
    }

    public function render(string $content, SiteSetting $settings): string
    {
        $accessibility = $settings->accessibility_mode === 'none'
            ? 'The Android release covered by this policy does not use Android Accessibility Services. Screenshots are submitted by you for verification.'
            : ($settings->accessibility_disclosure ?: 'Android permission details must be confirmed before publication.');

        return $this->sanitize(strtr($content, [
            '[APP_NAME]' => e($settings->app_name),
            '[OPERATOR_NAME]' => e($settings->operator_name ?? ''),
            '[ACCESSIBILITY_DISCLOSURE]' => nl2br(e($accessibility)),
            '[RETENTION_POLICY]' => nl2br(e($settings->retention_policy ?? 'Retention details must be confirmed before publication.')),
        ]));
    }

    private function validateIdentity(SiteSetting $settings): void
    {
        Validator::make($settings->toArray(), [
            'app_name' => 'required|string|min:2',
            'operator_name' => 'required|string|min:2',
            'support_email' => 'required|email:rfc',
            'accessibility_mode' => ['required', Rule::in(['none', 'whatsapp_status', 'other'])],
            'accessibility_disclosure' => [Rule::requiredIf($settings->accessibility_mode !== 'none'), 'nullable', 'string', 'min:40'],
            'retention_policy' => 'required|string|min:100',
        ], [
            'operator_name.required' => 'Set the legal operator/developer name in Branding & legal settings first.',
            'support_email.required' => 'Set a working support email in Branding & legal settings first.',
            'accessibility_mode.in' => 'Confirm Android Accessibility Services use in Branding & legal settings first.',
            'retention_policy.required' => 'Set the actual retention and deletion schedule in Branding & legal settings first.',
        ])->validate();
    }
}

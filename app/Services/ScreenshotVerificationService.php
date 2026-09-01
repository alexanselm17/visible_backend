<?php

namespace App\Services;

use App\Exceptions\ScreenshotVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ScreenshotVerificationService
{
    public function verify(string $advertPath, string $screenshotPath): array
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $advertDataUrl = $this->imageDataUrl($advertPath);
        $screenshotDataUrl = $this->imageDataUrl($screenshotPath);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.verification_model', 'gpt-4o'),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $this->prompt(),
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Original advert image:',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $advertDataUrl,
                                        'detail' => 'high',
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Submitted WhatsApp screenshot:',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $screenshotDataUrl,
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'response_format' => $this->responseFormat(),
                    'max_tokens' => 300,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Could not connect to OpenAI for screenshot verification.', [
                'error' => $exception->getMessage(),
            ]);

            throw new ScreenshotVerificationException(
                'Could not connect to OpenAI. Check the server network and try again.'
            );
        }

        if ($response->failed()) {
            $this->throwUpstreamFailure($response);
        }

        $output = $response->json('choices.0.message.content');
        $result = is_string($output) ? json_decode($output, true) : null;

        if (! is_array($result) || ! isset($result['status'])) {
            Log::warning('OpenAI returned an invalid screenshot verification response.', [
                'request_id' => $response->header('x-request-id'),
                'response_id' => $response->json('id'),
                'finish_reason' => $response->json('choices.0.finish_reason'),
                'refusal' => $response->json('choices.0.message.refusal'),
            ]);

            throw new ScreenshotVerificationException(
                'The screenshot verification model did not return a valid result.',
                $response->status(),
                $response->header('x-request-id')
            );
        }

        return $result;
    }

    private function imageDataUrl(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Verification image is missing or unreadable: {$path}");
        }

        $mimeType = mime_content_type($path);
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Verification images must be JPEG or PNG files.');
        }

        return 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path));
    }

    private function throwUpstreamFailure(Response $response): never
    {
        $status = $response->status();
        $requestId = $response->header('x-request-id');

        Log::warning('OpenAI screenshot verification request failed.', [
            'status' => $status,
            'request_id' => $requestId,
            'error_type' => $response->json('error.type'),
            'error_code' => $response->json('error.code'),
            'error_message' => $response->json('error.message'),
        ]);

        $message = match ($status) {
            400 => 'OpenAI rejected the screenshot verification request.',
            401, 403 => 'OpenAI authentication failed. Check OPENAI_API_KEY.',
            429 => 'OpenAI rate limit or quota was reached. Check API billing and usage limits.',
            500, 502, 503, 504 => 'OpenAI is temporarily unavailable. Please try again.',
            default => 'The screenshot verification service returned an unexpected error.',
        };

        throw new ScreenshotVerificationException($message, $status, $requestId);
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Compare the original advert with the media displayed in the submitted WhatsApp Status screenshot.

Verify all of the following:
1. The screenshot is from WhatsApp Status and visibly contains "My status" and a timestamp.
2. The advert shown in the screenshot matches the original advert image. Allow the personalized disclaimer and QR header added above the advert.
3. A numeric view count is clearly visible.

Return a successful status only when every requirement passes. Otherwise return the failed status and a short reason.
PROMPT;
    }

    private function responseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'whatsapp_screenshot_verification',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => [
                                'Screenshot Successfully Verified.',
                                'Screenshot Not Verified.Please confirm your screenshot and try again',
                            ],
                        ],
                        'reason' => [
                            'type' => ['string', 'null'],
                        ],
                        'views' => [
                            'type' => ['integer', 'null'],
                            'minimum' => 0,
                        ],
                        'timestamp' => [
                            'type' => ['string', 'null'],
                        ],
                    ],
                    'required' => ['status', 'reason', 'views', 'timestamp'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }
}

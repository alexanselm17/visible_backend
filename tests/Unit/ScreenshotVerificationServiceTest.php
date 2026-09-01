<?php

namespace Tests\Unit;

use App\Exceptions\ScreenshotVerificationException;
use App\Services\ScreenshotVerificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class ScreenshotVerificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'test-api-key');
        config()->set('services.openai.verification_model', 'gpt-4o');
    }

    public function test_it_sends_both_images_and_returns_the_structured_result(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-test',
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => json_encode([
                            'status' => 'Screenshot Successfully Verified.',
                            'reason' => null,
                            'views' => 91,
                            'timestamp' => 'Today, 1:06 PM',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ], 200, ['x-request-id' => 'req_success']),
        ]);

        [$advert, $screenshot] = $this->images();
        $result = app(ScreenshotVerificationService::class)->verify(
            $advert->getPathname(),
            $screenshot->getPathname()
        );

        $this->assertSame(91, $result['views']);
        $this->assertSame('Screenshot Successfully Verified.', $result['status']);

        Http::assertSent(function (Request $request): bool {
            $content = $request->data()['messages'][0]['content'];
            $images = array_values(array_filter(
                $content,
                fn (array $part): bool => $part['type'] === 'image_url'
            ));

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && count($images) === 2
                && str_starts_with($images[0]['image_url']['url'], 'data:image/png;base64,')
                && str_starts_with($images[1]['image_url']['url'], 'data:image/png;base64,')
                && $request->data()['response_format']['type'] === 'json_schema'
                && $request->data()['response_format']['json_schema']['strict'] === true;
        });
    }

    public function test_it_preserves_useful_information_when_openai_rejects_the_request(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                    'message' => 'Quota exceeded.',
                ],
            ], 429, ['x-request-id' => 'req_quota']),
        ]);

        [$advert, $screenshot] = $this->images();

        try {
            app(ScreenshotVerificationService::class)->verify(
                $advert->getPathname(),
                $screenshot->getPathname()
            );
            $this->fail('An upstream OpenAI error should fail screenshot verification.');
        } catch (ScreenshotVerificationException $exception) {
            $this->assertSame(429, $exception->upstreamStatus);
            $this->assertSame('req_quota', $exception->requestId);
            $this->assertSame(
                'OpenAI rate limit or quota was reached. Check API billing and usage limits.',
                $exception->getMessage()
            );
        }
    }

    private function images(): array
    {
        return [
            UploadedFile::fake()->image('advert.png', 20, 20),
            UploadedFile::fake()->image('screenshot.png', 20, 20),
        ];
    }
}

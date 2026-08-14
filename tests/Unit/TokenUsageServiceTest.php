<?php

namespace Tests\Unit;

use App\Models\TokenType;
use App\Services\TokenUsageService;
use PHPUnit\Framework\TestCase;

class TokenUsageServiceTest extends TestCase
{
    /**
     * @dataProvider tokenTypeProvider
     */
    public function test_quote_adds_submission_tokens_and_reach_tokens(
        string $code,
        string $mediaType,
        int $submissionTokens
    ): void {
        $tokenType = new TokenType([
            'code' => $code,
            'media_type' => $mediaType,
            'people_per_token' => 10,
            'seconds_per_token' => $mediaType === TokenType::VIDEO ? 30 : null,
        ]);

        $quote = (new TokenUsageService)->quote(
            $tokenType,
            21,
            $mediaType === TokenType::VIDEO ? 90 : null
        );

        $this->assertSame($submissionTokens, $quote['submission_tokens']);
        $this->assertSame(3, $quote['reach_tokens']);
        $this->assertSame($submissionTokens, $quote['media_units']);
        $this->assertSame(3, $quote['reach_units']);
        $this->assertSame($submissionTokens + 3, $quote['tokens_required']);
    }

    public static function tokenTypeProvider(): array
    {
        return [
            'video' => [TokenType::GOLD, TokenType::VIDEO, 5],
            'image' => [TokenType::PLATINUM, TokenType::IMAGE, 3],
            'text' => [TokenType::SILVER, TokenType::TEXT, 1],
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\TokenType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class CampaignOwnerWalletEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('token_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->string('name');
            $table->string('media_type');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('KES');
            $table->unsignedInteger('seconds_per_token')->nullable();
            $table->unsignedInteger('max_video_duration_seconds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('token_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('token_type_id');
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('locked_balance')->default(0);
            $table->timestamps();
        });
    }

    public function test_campaign_owner_can_get_all_remaining_token_balances(): void
    {
        $userId = '73c1ce9d-cfe4-4d34-94d8-08d5e0f7de17';

        DB::table('token_types')->insert([
            $this->tokenType('11111111-1111-4111-8111-111111111111', 'GOLD', 'Gold', 'VIDEO', 1),
            $this->tokenType('22222222-2222-4222-8222-222222222222', 'PLATINUM', 'Platinum', 'IMAGE', 2),
            $this->tokenType('33333333-3333-4333-8333-333333333333', 'SILVER', 'Silver', 'TEXT', 3),
        ]);

        DB::table('token_wallets')->insert([
            'id' => '44444444-4444-4444-8444-444444444444',
            'user_id' => $userId,
            'token_type_id' => '11111111-1111-4111-8111-111111111111',
            'balance' => 12,
            'locked_balance' => 4,
        ]);

        $user = new User;
        $user->id = $userId;

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'sanctum')
            ->getJson('/api/v1/campaign-owner/wallet');

        $response
            ->assertOk()
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonCount(3, 'data.balances')
            ->assertJsonPath('data.balances.0.token_type', 'GOLD')
            ->assertJsonPath('data.balances.0.remaining_tokens', 12)
            ->assertJsonPath('data.balances.0.reserved_tokens', 4)
            ->assertJsonPath('data.balances.0.total_tokens', 16)
            ->assertJsonPath('data.balances.1.remaining_tokens', 0)
            ->assertJsonPath('data.balances.2.remaining_tokens', 0);
    }

    private function tokenType(
        string $id,
        string $code,
        string $name,
        string $mediaType,
        int $sortOrder
    ): array {
        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'media_type' => $mediaType,
            'unit_price' => null,
            'currency' => 'KES',
            'seconds_per_token' => $mediaType === TokenType::VIDEO ? 30 : null,
            'max_video_duration_seconds' => null,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ];
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManualScreenshotSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fullname')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('advert_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('image_path')->nullable();
            $table->integer('capacity')->default(1);
            $table->uuid('campaign_id');
            $table->timestamps();
        });

        Schema::create('screenshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('screenshot');
            $table->string('timestamp');
            $table->integer('views');
            $table->uuid('advert_id')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->integer('number');
            $table->timestamps();
        });

        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('storage/screenshots/manual_*.png')) ?: [] as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_manual_screenshot_submission_records_known_values_without_image_verification(): void
    {
        $userId = '11111111-1111-4111-8111-111111111111';
        $campaignId = '22222222-2222-4222-8222-222222222222';
        $advertId = '33333333-3333-4333-8333-333333333333';

        DB::table('users')->insert([
            'id' => $userId,
            'fullname' => 'Manual User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('campaigns')->insert([
            'id' => $campaignId,
            'name' => 'Manual Campaign',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('advert_images')->insert([
            'id' => $advertId,
            'name' => 'Manual Advert',
            'image_path' => 'uploads/manual.png',
            'capacity' => 5,
            'campaign_id' => $campaignId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->postJson("/api/v1/campaign/manual_screenshot/{$advertId}", [
            'user_id' => $userId,
            'views' => 42,
            'timestamp' => 'Today, 9:30 AM',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.advert_id', $advertId)
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.manual', true)
            ->assertJsonPath('views', 42);

        $this->assertDatabaseHas('screenshots', [
            'advert_id' => $advertId,
            'processed_by' => $userId,
            'views' => 42,
            'timestamp' => 'Today, 9:30 AM',
            'number' => 1,
        ]);

        $path = $response->json('path');
        $this->assertIsString($path);
        $this->assertFileExists(public_path('storage/'.$path));
    }
}

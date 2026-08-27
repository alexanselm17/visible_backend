<?php

namespace Tests\Feature;

use App\Models\AdvertImages;
use App\Models\RewardLedgerEntry;
use App\Models\RewardPayout;
use App\Models\RewardPeriod;
use App\Services\RewardPayoutService;
use App\Services\RewardPeriodService;
use Database\Seeders\PerformanceRewardPlanSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class PerformanceRewardLifecycleTest extends TestCase
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
            $table->string('fullname');
            $table->string('my_code')->unique();
            $table->string('referal_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('advert_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('reward', 10, 2)->default(100);
            $table->timestamps();
        });
        Schema::create('screenshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('advert_id');
            $table->uuid('processed_by');
            $table->unsignedInteger('number');
            $table->unsignedInteger('views');
            $table->string('timestamp')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_08_25_090000_create_performance_reward_system.php');
        $migration->up();
        $dropAdvertReward = require database_path('migrations/2026_08_25_100000_drop_reward_from_advert_images_table.php');
        $dropAdvertReward->up();
        (new PerformanceRewardPlanSeeder)->run();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_adverts_no_longer_store_a_per_advert_reward(): void
    {
        $this->assertFalse(Schema::hasColumn('advert_images', 'reward'));
        $this->assertNotContains('reward', (new AdvertImages)->getFillable());
    }

    public function test_period_is_calculated_locked_paid_and_the_next_period_starts_at_zero(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $userId = '11111111-1111-4111-8111-111111111111';
        $adminId = '22222222-2222-4222-8222-222222222222';
        $referredId = '33333333-3333-4333-8333-333333333333';
        $this->insertUser($userId, 'POSTER', null, 'Poster');
        $this->insertUser($adminId, 'ADMIN', null, 'Admin');
        $this->insertUser($referredId, 'REFERRED', 'POSTER', 'Referred user');

        $advertOne = '44444444-4444-4444-8444-444444444444';
        $advertTwo = '55555555-5555-4555-8555-555555555555';
        $this->insertAdvert($advertOne, '2026-08-02 08:00:00');
        $this->insertAdvert($advertTwo, '2026-08-03 08:00:00');
        $this->insertSecondScreenshot($userId, $advertOne, 500, '2026-08-04 08:00:00');
        $this->insertSecondScreenshot($userId, $advertTwo, 500, '2026-08-05 08:00:00');

        DB::table('reward_referral_qualifications')->insert([
            'id' => '66666666-6666-4666-8666-666666666666',
            'referrer_user_id' => $userId,
            'referred_user_id' => $referredId,
            'qualifying_advert_id' => $advertOne,
            'qualified_at' => '2026-08-06 08:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periods = app(RewardPeriodService::class);
        $period = $periods->calculate($periods->currentOrCreate($userId));
        $metrics = $period->metrics->keyBy(fn ($metric) => $metric->metric->code);

        $this->assertSame(2000, $metrics['views']->multiplier_basis_points);
        $this->assertSame(10000, $metrics['consistency']->multiplier_basis_points);
        $this->assertSame(1000, $metrics['conversion']->multiplier_basis_points);
        $this->assertSame(10000, $period->calculated_amount_minor);

        $locked = $periods->lock($period);
        $this->assertSame(RewardPeriod::PAYMENT_PENDING, $locked->status);
        $this->assertSame(10000, RewardLedgerEntry::where('user_id', $userId)->sum('amount_minor'));

        $payout = app(RewardPayoutService::class)->confirm(
            $locked->payout->id,
            'mpesa',
            'TEST-RECEIPT-001',
            $adminId
        );

        $this->assertSame(RewardPayout::PAID, $payout->status);
        $this->assertSame(RewardPeriod::PAID, $payout->period->status);
        $this->assertSame(0, RewardLedgerEntry::where('user_id', $userId)->sum('amount_minor'));

        Carbon::setTestNow('2026-09-02 12:00:00');
        $newPeriod = $periods->calculate($periods->currentOrCreate($userId));

        $this->assertSame(0, $newPeriod->calculated_amount_minor);
        $this->assertSame(0, $newPeriod->combined_multiplier_basis_points);
        $this->assertNotSame($period->id, $newPeriod->id);

        $paidHistory = RewardPeriod::find($period->id);
        $this->assertSame(10000, $paidHistory->calculated_amount_minor);
        $this->assertSame(1000, (int) data_get($paidHistory->calculation_snapshot, 'metrics.0.measured_value'));
    }

    public function test_confirming_the_same_payout_is_idempotent(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $userId = '77777777-7777-4777-8777-777777777777';
        $adminId = '88888888-8888-4888-8888-888888888888';
        $this->insertUser($userId, 'USER-2', null, 'Second poster');
        $this->insertUser($adminId, 'ADMIN-2', null, 'Second admin');

        $period = app(RewardPeriodService::class)->currentOrCreate($userId);
        $period->calculated_amount_minor = 5000;
        $period->calculation_snapshot = ['test' => true];
        $period->save();
        $locked = app(RewardPeriodService::class)->lock($period);

        // lock() recalculates real metrics to zero, so create a payable period fixture explicitly.
        if (! $locked->payout) {
            $locked->status = RewardPeriod::PAYMENT_PENDING;
            $locked->calculated_amount_minor = 5000;
            $locked->save();
            $payout = RewardPayout::create([
                'reward_period_id' => $locked->id,
                'user_id' => $userId,
                'amount_minor' => 5000,
                'currency' => 'KES',
                'status' => RewardPayout::PENDING,
                'idempotency_key' => '99999999-9999-4999-8999-999999999999',
            ]);
            RewardLedgerEntry::create([
                'user_id' => $userId,
                'reward_period_id' => $locked->id,
                'type' => RewardLedgerEntry::EARNING,
                'amount_minor' => 5000,
                'currency' => 'KES',
                'idempotency_key' => "reward-period:{$locked->id}:earning-fixture",
            ]);
        } else {
            $payout = $locked->payout;
        }

        $service = app(RewardPayoutService::class);
        $first = $service->confirm($payout->id, 'mpesa', 'SAME-RECEIPT', $adminId);
        $second = $service->confirm($payout->id, 'mpesa', 'SAME-RECEIPT', $adminId);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RewardLedgerEntry::where('type', RewardLedgerEntry::PAYMENT)->count());
    }

    public function test_weekly_period_allocations_add_up_to_the_monthly_maximum(): void
    {
        $userId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $this->insertUser($userId, 'WEEKLY', null, 'Weekly poster');
        Carbon::setTestNow('2026-08-01 12:00:00');

        $service = app(RewardPeriodService::class);
        $service->setPayoutFrequency($userId, 'weekly');
        $maximums = [];

        foreach ([1, 3, 10, 17, 24, 31] as $day) {
            Carbon::setTestNow("2026-08-{$day} 12:00:00");
            $maximums[] = $service->currentOrCreate($userId)->maximum_amount_minor;
        }

        $this->assertSame(500000, array_sum($maximums));
        $this->assertSame(6, RewardPeriod::where('user_id', $userId)->count());
    }

    public function test_performance_reward_migration_rolls_back_cleanly(): void
    {
        $dropAdvertReward = require database_path('migrations/2026_08_25_100000_drop_reward_from_advert_images_table.php');
        $dropAdvertReward->down();
        $migration = require database_path('migrations/2026_08_25_090000_create_performance_reward_system.php');
        $migration->down();

        $this->assertTrue(Schema::hasColumn('advert_images', 'reward'));
        $this->assertFalse(Schema::hasTable('reward_plans'));
        $this->assertFalse(Schema::hasTable('reward_periods'));
        $this->assertFalse(Schema::hasTable('reward_payouts'));
        $this->assertFalse(Schema::hasTable('reward_ledger_entries'));
    }

    private function insertUser(string $id, string $myCode, ?string $referralCode, string $name): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'fullname' => $name,
            'my_code' => $myCode,
            'referal_code' => $referralCode,
            'created_at' => '2026-08-01 00:00:00',
            'updated_at' => '2026-08-01 00:00:00',
        ]);
    }

    private function insertAdvert(string $id, string $createdAt): void
    {
        DB::table('advert_images')->insert([
            'id' => $id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertSecondScreenshot(string $userId, string $advertId, int $views, string $createdAt): void
    {
        DB::table('screenshots')->insert([
            'id' => (string) Str::uuid(),
            'advert_id' => $advertId,
            'processed_by' => $userId,
            'number' => 2,
            'views' => $views,
            'timestamp' => 'Yesterday',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

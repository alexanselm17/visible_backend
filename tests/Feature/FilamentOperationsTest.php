<?php

namespace Tests\Feature;

use App\Filament\Resources;
use App\Models\AdvertImages;
use App\Models\AdvertSubmission;
use App\Models\Campaign;
use App\Models\TokenPurchase;
use App\Models\TokenType;
use App\Models\TokenWallet;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class FilamentOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Storage::fake('admin_media');
        AdminPanelDatabase::build();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function test_every_admin_screen_renders_and_secret_fields_are_not_displayed(): void
    {
        $admin = AdminPanelDatabase::user();
        $this->actingAs($admin);
        \App\Models\PhoneVerificationOtp::create([
            'user_id' => $admin->id, 'phone' => $admin->phone, 'purpose' => 'signup',
            'code_hash' => 'NEVER-EXPOSE-OTP-HASH', 'expires_at' => now()->addMinutes(10),
        ]);
        $panel = \Filament\Facades\Filament::getPanel('admin');
        foreach ($panel->getResources() as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }
        $this->get('/admin')->assertOk()->assertSee('Operations overview');
        $this->get('/admin/reports')->assertOk();
        $this->get('/admin/fraud-review-queue')->assertOk();
        $this->get('/admin/users/create')->assertOk();
        $this->get('/admin/campaigns/create')->assertOk();
        $this->get('/admin/advert-images/create')->assertOk();
        $this->get('/admin/phone-verification-otps')->assertOk()->assertDontSee('NEVER-EXPOSE-OTP-HASH');
    }

    public function test_managers_and_inactive_accounts_cannot_access_financial_or_staff_settings(): void
    {
        $manager = AdminPanelDatabase::user('manager');
        $this->actingAs($manager);
        $this->get('/admin/advert-submissions')->assertOk();
        $this->get('/admin/token-wallets')->assertForbidden();
        $this->get('/admin/reward-payouts')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->assertFalse(AdminAccess::allows('finance', $manager));
        $manager->is_active = false;
        $this->assertFalse($manager->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }

    public function test_purchase_payment_action_credits_tokens_once(): void
    {
        $admin = AdminPanelDatabase::user();
        $this->actingAs($admin);
        $purchase = app(\App\Services\TokenService::class)->createPurchase($admin->id, 'GOLD', 10);
        Livewire::test(Resources\TokenPurchaseResource\Pages\ManageTokenPurchases::class)
            ->callTableAction('confirm', $purchase, ['payment_reference' => 'PANEL-PAY-001'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(TokenPurchase::PAID, $purchase->fresh()->status);
        $this->assertSame(10, TokenWallet::where('user_id', $admin->id)->value('balance'));
    }

    public function test_submission_approval_and_rejection_use_the_token_workflow(): void
    {
        $admin = AdminPanelDatabase::user();
        $this->actingAs($admin);
        $owner = AdminPanelDatabase::user('campaign_owner');
        $campaign = Campaign::create(['name' => 'Panel campaign', 'owner_id' => $owner->id]);
        $type = TokenType::where('code', 'PLATINUM')->firstOrFail();
        app(\App\Services\TokenService::class)->adjustBalance($owner->id, $type->code, 100, 'Fixture');
        $submission = AdvertSubmission::create([
            'name' => 'Panel brief', 'description' => 'Campaign design brief', 'campaign_id' => $campaign->id, 'submitted_by' => $owner->id,
            'capital_invested' => 0, 'type' => 'IMAGE', 'target_reach' => 100,
            'token_type_id' => $type->id, 'tokens_reserved' => 13, 'status' => 'PENDING_APPROVAL',
        ]);
        app(\App\Services\TokenService::class)->reserveForSubmission($owner->id, $submission, $type, 13);
        Livewire::test(Resources\AdvertSubmissionResource\Pages\ManageAdvertSubmissions::class)
            ->callTableAction('approve', $submission)->assertHasNoTableActionErrors();
        $this->assertSame('PENDING_DESIGN', $submission->fresh()->status);
        Livewire::test(Resources\AdvertSubmissionResource\Pages\ManageAdvertSubmissions::class)
            ->callTableAction('reject', $submission, ['reason' => 'Owner requested cancellation'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('REJECTED', $submission->fresh()->status);
        $wallet = TokenWallet::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame(100, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
    }

    public function test_forms_for_configuration_and_versioned_reward_plans_mount(): void
    {
        $this->actingAs(AdminPanelDatabase::user());
        foreach ([
            Resources\RewardPlanResource\Pages\ManageRewardPlans::class => 'new_version',
            Resources\TokenWalletResource\Pages\ManageTokenWallets::class => 'adjust',
            Resources\TokenPurchaseResource\Pages\ManageTokenPurchases::class => 'new_purchase',
            Resources\ScreenshotsResource\Pages\ListScreenshots::class => 'manual',
        ] as $page => $action) {
            Livewire::test($page)->mountTableAction($action)->assertSuccessful();
        }
    }

    public function test_plan_creation_from_the_panel_keeps_multiplication_and_stores_ranges(): void
    {
        \Filament\Forms\Components\Repeater::fake();
        $this->actingAs(AdminPanelDatabase::user());
        Livewire::test(Resources\RewardPlanResource\Pages\ManageRewardPlans::class)
            ->callTableAction('new_version', data: [
                'name' => 'Panel plan', 'version' => 2, 'calculation_method' => 'multiply',
                'monthly_maximum' => 5000, 'currency' => 'KES', 'effective_from' => now()->toDateTimeString(),
                'metrics' => [[
                    'code' => 'panel_views', 'name' => 'Panel verified views', 'evaluator_key' => 'views',
                    'weight' => 1, 'minimum_multiplier' => 0, 'maximum_multiplier' => 1,
                    'settings' => [], 'tiers' => [
                        ['minimum' => 50, 'maximum' => 500, 'multiplier' => .1],
                        ['minimum' => 501, 'maximum' => null, 'multiplier' => 1],
                    ],
                ]],
            ])->assertHasNoTableActionErrors();
        $plan = \App\Models\RewardPlan::where('name', 'Panel plan')->firstOrFail();
        $this->assertSame('multiply', $plan->calculation_method);
        $this->assertSame(500000, $plan->monthly_maximum_minor);
        $this->assertFalse($plan->is_active);
        $this->assertSame(1000, $plan->planMetrics->first()->tiers->first()->multiplier_basis_points);
    }

    public function test_panel_payout_confirmation_preserves_history_and_blocks_self_payment(): void
    {
        $admin = AdminPanelDatabase::user();
        $poster = AdminPanelDatabase::user('member');
        $this->actingAs($admin);
        $period = app(\App\Services\RewardPeriodService::class)->currentOrCreate($poster->id);
        $period->update(['status' => 'payment_pending', 'calculated_amount_minor' => 25000]);
        $payout = \App\Models\RewardPayout::create([
            'user_id' => $poster->id, 'reward_period_id' => $period->id, 'amount_minor' => 25000,
            'currency' => 'KES', 'status' => 'pending', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        Livewire::test(Resources\RewardPayoutResource\Pages\ManageRewardPayouts::class)
            ->callTableAction('fail', $payout, ['reason' => 'Provider unavailable'])->assertHasNoTableActionErrors();
        $this->assertSame('failed', $payout->fresh()->status);
        Livewire::test(Resources\RewardPayoutResource\Pages\ManageRewardPayouts::class)
            ->callTableAction('confirm', $payout, ['provider' => 'mpesa', 'payment_reference' => 'PANEL-REWARD-001'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame(25000, $period->fresh()->calculated_amount_minor);
        $this->assertSame(-25000, \App\Models\RewardLedgerEntry::where('reward_payout_id', $payout->id)->value('amount_minor'));
        $this->actingAs($poster);
        $this->assertFalse(Resources\RewardPayoutResource::canViewAny());
    }

    public function test_member_creation_and_details_use_current_schema_and_hash_password_once(): void
    {
        $this->actingAs(AdminPanelDatabase::user());
        Livewire::test(Resources\UserResource\Pages\CreateUser::class)->fillForm([
            'fullname' => 'New panel member', 'username' => 'new-panel-member',
            'email' => 'panel-member@example.test', 'phone' => '+254711222333',
            'gender' => 'Male', 'occupation' => 'Designer', 'password' => 'strong-panel-password',
            'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();
        $member = \App\Models\User::where('username', 'new-panel-member')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('strong-panel-password', $member->password));
        $this->assertMatchesRegularExpression('/^\d{10}$/', $member->my_code);
        $this->get(Resources\UserResource::getUrl('view', ['record' => $member]))->assertOk();
        $this->get(Resources\UserResource::getUrl('edit', ['record' => $member]))->assertOk();
    }

    public function test_dashboard_widgets_and_layout_studio_render(): void
    {
        $this->actingAs(AdminPanelDatabase::user());
        $this->get('/admin/image-studio')->assertOk();
        foreach ((new \App\Filament\Pages\Dashboard)->getWidgets() as $widget) {
            $this->assertContains($widget, \Filament\Facades\Filament::getPanel('admin')->getWidgets());
            Livewire::test($widget)->assertSuccessful();
        }
    }

    public function test_submission_files_and_configuration_modals_render_populated_records(): void
    {
        $admin = AdminPanelDatabase::user();
        $this->actingAs($admin);
        $campaign = Campaign::create(['name' => 'Detailed campaign', 'owner_id' => $admin->id]);
        $submission = AdvertSubmission::create([
            'name' => 'Detailed brief', 'description' => 'A useful design brief',
            'campaign_id' => $campaign->id, 'submitted_by' => $admin->id,
            'capital_invested' => 0, 'type' => 'IMAGE', 'target_reach' => 500,
            'target_audience' => [['county_id' => 'example', 'gender' => 'Male']],
            'status' => 'PENDING_APPROVAL',
        ]);
        Livewire::test(Resources\AdvertSubmissionResource\Pages\ManageAdvertSubmissions::class)
            ->mountTableAction('view', $submission)->assertSuccessful();
        $type = TokenType::where('code', 'GOLD')->firstOrFail();
        Livewire::test(Resources\TokenTypeResource\Pages\ManageTokenTypes::class)
            ->mountTableAction('edit', $type)->assertSuccessful();
        $plan = \App\Models\RewardPlan::firstOrFail();
        Livewire::test(Resources\RewardPlanResource\Pages\ManageRewardPlans::class)
            ->mountTableAction('view', $plan)->assertSuccessful()
            ->unmountTableAction()->mountTableAction('copy', $plan)->assertSuccessful();
        $this->get(Resources\CampaignResource::getUrl('view', ['record' => $campaign]))->assertOk();
        $this->get(Resources\CampaignResource::getUrl('edit', ['record' => $campaign]))->assertOk();
    }

    public function test_fraud_queue_records_a_review_against_the_actual_candidate(): void
    {
        $admin = AdminPanelDatabase::user();
        $member = AdminPanelDatabase::user('member');
        $this->actingAs($admin);
        $campaign = Campaign::create(['name' => 'Review campaign', 'owner_id' => $admin->id]);
        $advert = AdvertImages::create([
            'name' => 'Review advert', 'campaign_id' => $campaign->id, 'capital_invested' => 0,
            'selling_price' => 0, 'category' => 'General', 'description' => 'Review sample',
            'capacity' => 100, 'type' => 'IMAGE', 'valid_until' => now()->addDay(),
        ]);
        foreach ([$admin, $member] as $poster) {
            $shot = new \App\Models\Screenshots;
            $shot->advert_id = $advert->id;
            $shot->processed_by = $poster->id;
            $shot->screenshot = 'screenshots/test-evidence.png';
            $shot->views = 100;
            $shot->timestamp = 'Today, 09:00';
            $shot->number = 1;
            $shot->save();
        }
        Livewire::test(\App\Filament\Pages\FraudReviewQueue::class)
            ->set('data.campaign_id', $campaign->id)->call('scan')->assertSee('Review advert')
            ->callAction('review', ['status' => 'REJECTED', 'notes' => 'Reviewed both screenshots; not fraudulent.'], arguments: [
                'advert' => $advert->id, 'pattern' => '100_Today, 09:00',
            ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('fraud_reviews', ['advert_id' => $advert->id, 'status' => 'REJECTED', 'reviewed_by' => $admin->id]);
    }

    public function test_media_type_repair_backfills_missing_types_and_preserves_existing_classifications(): void
    {
        $member = AdminPanelDatabase::user();
        $campaign = Campaign::create(['name' => 'Media migration campaign', 'owner_id' => $member->id]);
        $advert = AdvertImages::create([
            'name' => 'Video advert', 'campaign_id' => $campaign->id, 'capital_invested' => 0,
            'selling_price' => 0, 'category' => 'General', 'description' => 'Sample',
            'capacity' => 100, 'type' => 'ads', 'video_path' => 'uploads/video.mp4', 'valid_until' => now()->addDay(),
        ]);
        $migration = require database_path('migrations/2026_10_26_090000_ensure_advert_images_media_type.php');
        $migration->up();
        $this->assertSame('ads', $advert->fresh()->type);
        \Illuminate\Support\Facades\Schema::table('advert_images', fn ($table) => $table->dropColumn('type'));
        $migration->up();
        $this->assertSame('VIDEO', $advert->fresh()->type);
        $migration->down();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('advert_images', 'type'));
    }

    public function test_final_design_upload_and_publication_settle_the_reservation(): void
    {
        $admin = AdminPanelDatabase::user();
        $this->actingAs($admin);
        $owner = AdminPanelDatabase::user('campaign_owner');
        $campaign = Campaign::create(['name' => 'Publish campaign', 'owner_id' => $owner->id]);
        $type = TokenType::where('code', 'PLATINUM')->firstOrFail();
        app(\App\Services\TokenService::class)->adjustBalance($owner->id, $type->code, 100, 'Fixture');
        $submission = AdvertSubmission::create([
            'name' => 'Publish brief', 'description' => 'Final design test',
            'campaign_id' => $campaign->id, 'submitted_by' => $owner->id,
            'capital_invested' => 0, 'type' => 'IMAGE', 'target_reach' => 100,
            'token_type_id' => $type->id, 'tokens_reserved' => 13, 'status' => 'PENDING_DESIGN',
        ]);
        app(\App\Services\TokenService::class)->reserveForSubmission($owner->id, $submission, $type, 13);
        $originalPublicPath = public_path();
        $temporaryPublicPath = sys_get_temp_dir().'/visible-filament-'.\Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\File::ensureDirectoryExists($temporaryPublicPath);
        app()->usePublicPath($temporaryPublicPath);
        Storage::fake('local');

        try {
            Livewire::test(Resources\AdvertSubmissionResource\Pages\ManageAdvertSubmissions::class)
                ->callTableAction('final_design', $submission, [
                    'designer_id' => $admin->id,
                    'final_image' => \Illuminate\Http\UploadedFile::fake()->image('design.png', 120, 120),
                ])->assertHasNoTableActionErrors();
            $this->assertSame('DESIGN_DONE', $submission->fresh()->status);
            $this->assertFileExists(public_path('storage/'.$submission->fresh()->final_image_path));
            $submission->refresh();
            Livewire::test(Resources\AdvertSubmissionResource\Pages\ManageAdvertSubmissions::class)
                ->callTableAction('publish', $submission, [
                    'category' => 'General', 'badge' => ['Sponsored'],
                    'description' => 'A finished campaign', 'valid_until' => now()->addWeek()->toDateTimeString(),
                ])->assertHasNoTableActionErrors();
            $this->assertSame('PUBLISHED', $submission->fresh()->status);
            $this->assertSame(13, $submission->fresh()->tokens_spent);
            $wallet = TokenWallet::where('user_id', $owner->id)->firstOrFail();
            $this->assertSame(87, $wallet->balance);
            $this->assertSame(0, $wallet->locked_balance);
            $this->assertDatabaseHas('advert_images', ['submission_id' => $submission->id, 'type' => 'IMAGE']);
        } finally {
            app()->usePublicPath($originalPublicPath);
            \Illuminate\Support\Facades\File::deleteDirectory($temporaryPublicPath);
        }
    }

    public function test_token_estimator_shows_the_submission_plus_reach_quote_without_charging(): void
    {
        $this->actingAs(AdminPanelDatabase::user());
        Livewire::test(\App\Filament\Pages\TokenEstimator::class)
            ->fillForm(['mode' => 'usage', 'token_type' => 'PLATINUM', 'target_reach' => 100])
            ->call('calculate')->assertHasNoFormErrors()->assertSet('quote.Total tokens required', 13);
        $this->assertSame(0, TokenWallet::count());
    }
}

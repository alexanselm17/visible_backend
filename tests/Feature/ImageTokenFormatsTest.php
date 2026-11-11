<?php

namespace Tests\Feature;

use App\Filament\Pages\ImageStudio;
use App\Filament\Pages\TokenEstimator;
use App\Filament\Resources\AdvertImagesResource\Pages\ListAdvertImages;
use App\Filament\Resources\AdvertSubmissionResource\Pages\ManageAdvertSubmissions;
use App\Filament\Resources\TokenPurchaseResource\Pages\ManageTokenPurchases;
use App\Filament\Resources\TokenTypeResource\Pages\ManageTokenTypes;
use App\Filament\Resources\TokenWalletResource\Pages\ManageTokenWallets;
use App\Models\AdvertImages;
use App\Models\AdvertSubmission;
use App\Models\Campaign;
use App\Models\TokenHold;
use App\Models\TokenPurchase;
use App\Models\TokenTransaction;
use App\Models\TokenType;
use App\Models\TokenWallet;
use App\Models\User;
use App\Services\AdvertQrCodeService;
use App\Services\InvisibleImageWatermarkService;
use App\Services\TokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class ImageTokenFormatsTest extends TestCase
{
    private User $admin;

    private User $owner;

    private User $member;

    private Campaign $campaign;

    private string $originalPublicPath;

    private string $temporaryPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('admin_media');
        AdminPanelDatabase::build();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->admin = AdminPanelDatabase::user();
        $this->owner = AdminPanelDatabase::user('campaign_owner');
        $this->member = AdminPanelDatabase::user('member');
        $this->campaign = Campaign::create(['name' => 'Image token campaign', 'owner_id' => $this->owner->id]);
        $this->originalPublicPath = public_path();
        $this->temporaryPublicPath = sys_get_temp_dir().'/visible-image-tokens-'.Str::uuid();
        File::ensureDirectoryExists($this->temporaryPublicPath);
        app()->usePublicPath($this->temporaryPublicPath);
        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        app()->usePublicPath($this->originalPublicPath);
        File::deleteDirectory($this->temporaryPublicPath);
        parent::tearDown();
    }

    public function test_migration_adds_an_unpriced_disabled_token_without_changing_existing_money_or_balances(): void
    {
        $tokens = app(TokenService::class);
        $type = $tokens->getTypeByCode('PLATINUM');
        $tokens->adjustBalance($this->owner->id, 'PLATINUM', 70, 'Existing balance');
        $purchase = $tokens->createPurchase($this->owner->id, 'PLATINUM', 5);
        $ads = $tokens->getTypeByCode('EMERALD');
        $this->assertNull($ads->unit_price);
        $this->assertFalse($ads->is_active);
        $this->assertSame('ADS_IMAGE', $ads->media_type);
        $ads->update(['unit_price' => 20, 'is_active' => true]);
        $migration = require database_path('migrations/2026_11_11_100000_add_platinum_ads_image_token.php');
        $migration->down();
        $migration->up();
        $migration->up();
        $this->assertSame(4, TokenType::count());
        $this->assertSame($type->id, $tokens->getTypeByCode('PLATINUM')->id);
        $this->assertSame('30.00', $type->fresh()->unit_price);
        $this->assertSame(70, $this->balance('PLATINUM')->balance);
        $this->assertSame('150.00', $purchase->fresh()->total_amount);
        $this->assertSame('20.00', $ads->fresh()->unit_price);
        $this->assertTrue($ads->fresh()->is_active);
    }

    public function test_emerald_rename_preserves_pricing_balances_purchases_holds_and_submission_links(): void
    {
        $this->fundBoth();
        $submissionId = $this->submit('ADS_IMAGE')->assertCreated()->json('data.submission.id');
        $tokens = app(TokenService::class);
        $purchase = $tokens->createPurchase($this->owner->id, 'EMERALD', 5);
        $type = $tokens->getTypeByCode('EMERALD');
        $type->update(['code' => 'PLATINUM_ADS', 'name' => 'Platinum Ads · Attached image']);
        $settings = $type->fresh()->only(['unit_price', 'currency', 'is_active', 'people_per_token', 'media_type', 'description']);
        $financialRecords = [];
        foreach (['token_wallets', 'token_purchases', 'token_transactions', 'token_holds', 'advert_submissions'] as $table) {
            $financialRecords[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        $migration = require database_path('migrations/2026_11_11_110000_rename_attached_image_token_to_emerald.php');
        $migration->up();
        $migration->down();
        $migration->up();

        $emerald = $tokens->getTypeByCode('EMERALD');
        $this->assertSame($type->id, $emerald->id);
        $this->assertSame('Emerald · Attached image', $emerald->name);
        $this->assertSame($settings, $emerald->only(array_keys($settings)));
        $this->assertFalse(TokenType::where('code', 'PLATINUM_ADS')->exists());
        $this->assertSame(4, TokenType::count());
        foreach ($financialRecords as $table => $records) {
            $this->assertSame($records, DB::table($table)->orderBy('id')->get()->toJson(), $table.' must not change during the rename.');
        }
        $this->assertSame($emerald->id, $purchase->fresh()->tokenType->id);
        $this->assertSame('100.00', $purchase->fresh()->total_amount);
        $this->assertSame($emerald->id, AdvertSubmission::findOrFail($submissionId)->tokenType->id);
        $this->assertSame(87, $this->balance('EMERALD')->balance);
        $this->assertSame(13, $this->balance('EMERALD')->locked_balance);
    }

    public function test_emerald_rename_preserves_a_custom_display_name(): void
    {
        $type = TokenType::where('code', 'EMERALD')->firstOrFail();
        $type->update(['code' => 'PLATINUM_ADS', 'name' => 'Custom attached-image credit']);

        (require database_path('migrations/2026_11_11_110000_rename_attached_image_token_to_emerald.php'))->up();

        $this->assertSame('EMERALD', $type->fresh()->code);
        $this->assertSame('Custom attached-image credit', $type->fresh()->name);
        $this->assertNull($type->fresh()->unit_price);
        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_emerald_rename_rejects_conflicting_codes_without_overwriting_existing_types(): void
    {
        $legacy = TokenType::where('code', 'EMERALD')->firstOrFail();
        $legacy->update(['code' => 'PLATINUM_ADS', 'name' => 'Platinum Ads · Attached image']);
        $other = TokenType::create(['code' => 'EMERALD', 'name' => 'Unrelated token', 'media_type' => 'OTHER', 'unit_price' => 12]);
        $before = DB::table('token_types')->orderBy('id')->get()->toJson();

        try {
            (require database_path('migrations/2026_11_11_110000_rename_attached_image_token_to_emerald.php'))->up();
            $this->fail('Conflicting token codes must not silently merge financial records.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conflicting token configuration', $exception->getMessage());
        }

        $this->assertSame($before, DB::table('token_types')->orderBy('id')->get()->toJson());
        $this->assertSame('PLATINUM_ADS', $legacy->fresh()->code);
        $this->assertSame('OTHER', $other->fresh()->media_type);
    }

    public function test_types_and_wallet_include_both_images_and_unconfigured_ads_cannot_be_bought(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $types = $this->getJson('/api/v1/tokens/types')->assertOk()->assertJsonCount(4, 'data.types')->json('data.types');
        $this->assertSame('Emerald · Attached image', collect($types)->firstWhere('code', 'EMERALD')['name']);
        $this->assertNull(collect($types)->firstWhere('code', 'PLATINUM_ADS'));
        $this->assertFalse(collect($types)->firstWhere('code', 'EMERALD')['purchasable']);
        $this->getJson('/api/v1/tokens/wallet')->assertOk()->assertJsonCount(4, 'data.balances');
        $this->postJson('/api/v1/tokens/purchases', ['token_type' => 'EMERALD', 'quantity' => 5])->assertStatus(422);
        $this->assertSame(0, TokenPurchase::count());
    }

    public function test_admin_can_set_a_separate_ads_price_and_invalid_prices_roll_back(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->putJson('/api/v1/tokens/types/EMERALD', ['unit_price' => 20, 'is_active' => true])->assertOk();
        $this->postJson('/api/v1/tokens/quote', ['token_type' => 'EMERALD', 'quantity' => 5])->assertOk()->assertJsonPath('data.total_amount', '100.00');
        $this->postJson('/api/v1/tokens/quote', ['token_type' => 'PLATINUM', 'quantity' => 5])->assertOk()->assertJsonPath('data.total_amount', '150.00');
        foreach ([30, 50, 5] as $invalidPrice) {
            $this->putJson('/api/v1/tokens/types/EMERALD', ['unit_price' => $invalidPrice])->assertStatus(422)->assertJsonValidationErrors('unit_price');
            $this->assertSame('20.00', TokenType::where('code', 'EMERALD')->firstOrFail()->unit_price);
        }
        $this->putJson('/api/v1/tokens/types/PLATINUM', ['unit_price' => 20])->assertStatus(422);
        $this->assertSame('30.00', TokenType::where('code', 'PLATINUM')->firstOrFail()->unit_price);
    }

    public function test_ads_activation_without_a_price_is_rejected_and_non_admins_cannot_change_pricing(): void
    {
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/v1/tokens/types/EMERALD', ['is_active' => true])->assertStatus(422);
        $this->assertFalse(TokenType::where('code', 'EMERALD')->firstOrFail()->is_active);
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/tokens/types/EMERALD', ['unit_price' => 20, 'is_active' => true])->assertForbidden();
    }

    public function test_purchase_confirmation_credits_only_the_purchased_image_wallet_once(): void
    {
        $this->enableAds();
        app(TokenService::class)->adjustBalance($this->owner->id, 'PLATINUM', 50, 'Existing full-image balance');
        $this->actingAs($this->owner, 'sanctum');
        $purchaseId = $this->postJson('/api/v1/tokens/purchases', ['token_type' => 'EMERALD', 'quantity' => 12])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/tokens/purchases/'.$purchaseId.'/confirm', ['payment_reference' => 'ADS-PRICE-001'])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum');
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/tokens/purchases/'.$purchaseId.'/confirm', ['payment_reference' => 'ADS-PRICE-001'])->assertOk();
        }
        $this->assertSame(12, $this->balance('EMERALD')->balance);
        $this->assertSame(50, $this->balance('PLATINUM')->balance);
        $this->assertSame(1, TokenTransaction::where('type', TokenTransaction::PURCHASE)->count());
        $this->assertSame('240.00', TokenPurchase::findOrFail($purchaseId)->total_amount);
    }

    public function test_usage_quotes_resolve_image_aliases_to_the_right_token(): void
    {
        $this->enableAds();
        $this->actingAs($this->owner, 'sanctum');
        foreach (['IMAGE' => 'PLATINUM', 'image' => 'PLATINUM', 'FULL_IMAGE' => 'PLATINUM', 'ADS_IMAGE' => 'EMERALD', 'ads' => 'EMERALD'] as $media => $token) {
            $this->postJson('/api/v1/tokens/usage-quote', ['type' => $media, 'target_reach' => 100])->assertOk()
                ->assertJsonPath('data.token_type', $token)->assertJsonPath('data.submission_tokens', 3)->assertJsonPath('data.tokens_required', 13);
        }
    }

    public function test_ads_submission_cannot_spend_the_existing_full_image_balance(): void
    {
        $this->enableAds();
        app(TokenService::class)->adjustBalance($this->owner->id, 'PLATINUM', 100, 'Full-image balance only');
        $this->submit('ADS_IMAGE')->assertStatus(402);
        $this->assertSame(100, $this->balance('PLATINUM')->balance);
        $this->assertSame(0, AdvertSubmission::count());
        $this->assertSame(0, TokenHold::count());
    }

    public function test_ads_images_are_required_and_legacy_ads_input_reserves_the_correct_wallet(): void
    {
        $this->fundBoth();
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/campaign-owner/campaign/submission', [
            'type' => 'ads', 'name' => 'Missing image', 'description' => 'Brief', 'target_reach' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('images');
        $response = $this->submit('ads')->assertCreated()->assertJsonPath('data.token_usage.token_type', 'EMERALD');
        $submission = AdvertSubmission::findOrFail($response->json('data.submission.id'));
        $this->assertSame('ADS_IMAGE', $submission->type);
        $this->assertSame('EMERALD', $submission->tokenType->code);
        $this->assertSame(87, $this->balance('EMERALD')->balance);
        $this->assertSame(13, $this->balance('EMERALD')->locked_balance);
        $this->assertSame(100, $this->balance('PLATINUM')->balance);
    }

    public function test_rejection_returns_reserved_ads_tokens_to_ads_wallet_only_once(): void
    {
        $this->fundBoth();
        $id = $this->submit('ADS_IMAGE')->assertCreated()->json('data.submission.id');
        $this->actingAs($this->admin, 'sanctum');
        $this->postJson('/api/v1/campaign-owner/submissions/'.$id.'/reject', ['reason' => 'Owner requested cancellation'])->assertOk();
        $this->postJson('/api/v1/campaign-owner/submissions/'.$id.'/reject', ['reason' => 'Repeated rejection'])->assertStatus(422);
        $this->assertSame(100, $this->balance('EMERALD')->balance);
        $this->assertSame(0, $this->balance('EMERALD')->locked_balance);
        $this->assertSame(100, $this->balance('PLATINUM')->balance);
    }

    public function test_admin_api_adjustments_and_cancelled_purchases_keep_image_wallets_separate(): void
    {
        $this->fundBoth();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/tokens/wallet/adjust', [
            'user_id' => $this->owner->id, 'token_type' => 'EMERALD', 'amount' => 5, 'reason' => 'Ads promotion',
        ])->assertOk();
        $this->assertSame(105, $this->balance('EMERALD')->balance);
        $this->assertSame(100, $this->balance('PLATINUM')->balance);
        $this->actingAs($this->owner, 'sanctum');
        $purchaseId = $this->postJson('/api/v1/tokens/purchases', ['token_type' => 'EMERALD', 'quantity' => 4])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/tokens/purchases/'.$purchaseId.'/cancel')->assertOk();
        $this->assertSame('CANCELLED', TokenPurchase::findOrFail($purchaseId)->status);
        $this->assertSame(105, $this->balance('EMERALD')->balance);
    }

    public function test_ads_final_design_cannot_include_a_video(): void
    {
        $this->fundBoth();
        $id = $this->submit('ADS_IMAGE')->assertCreated()->json('data.submission.id');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/campaign-owner/submissions/'.$id.'/approve')->assertOk();
        $this->post('/api/v1/campaign-owner/submissions/'.$id.'/final', [
            'designer_id' => $this->admin->id, 'final_image' => UploadedFile::fake()->image('creative.png'),
            'final_video' => UploadedFile::fake()->create('video.mp4', 2, 'video/mp4'),
            'thumbnail_image' => UploadedFile::fake()->image('thumbnail.png'), 'final_video_duration_seconds' => 30,
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('final_video');
        $this->assertSame('PENDING_DESIGN', AdvertSubmission::findOrFail($id)->status);
        $this->assertNull(AdvertSubmission::findOrFail($id)->final_video_path);
        $this->assertSame(13, $this->balance('EMERALD')->locked_balance);
    }

    public function test_ads_design_publication_and_repost_keep_the_paid_format_and_pricing_snapshot(): void
    {
        $this->fundBoth();
        $id = $this->submit('ADS_IMAGE')->assertCreated()->json('data.submission.id');
        $this->actingAs($this->admin);
        $submission = AdvertSubmission::findOrFail($id);
        Livewire::test(ManageAdvertSubmissions::class)->callTableAction('approve', $submission)->assertHasNoTableActionErrors();
        $submission->refresh();
        $this->putJson('/api/v1/tokens/types/EMERALD', ['unit_price' => 25, 'people_per_token' => 2])->assertOk();
        Livewire::test(ManageAdvertSubmissions::class)->callTableAction('final_design', $submission, [
            'designer_id' => $this->admin->id, 'final_image' => UploadedFile::fake()->image('final.png', 300, 300),
        ])->assertHasNoTableActionErrors();
        $submission->refresh();
        Livewire::test(ManageAdvertSubmissions::class)->callTableAction('publish', $submission, [
            'category' => 'General', 'badge' => ['Sponsored'], 'description' => 'Published attached-image advert', 'valid_until' => now()->addWeek()->toDateTimeString(),
        ])->assertHasNoTableActionErrors();
        $submission->refresh();
        $this->assertSame(13, $submission->tokens_spent);
        $this->assertSame(10, $submission->people_per_token_snapshot);
        $this->assertSame(87, $this->balance('EMERALD')->balance);
        $this->assertSame(0, $this->balance('EMERALD')->locked_balance);
        $this->assertSame(100, $this->balance('PLATINUM')->balance);
        $advert = AdvertImages::where('submission_id', $id)->firstOrFail();
        $this->assertSame('ADS_IMAGE', $advert->type);
        $this->postJson('/api/v1/campaign/repost_product_advert', ['advert_id' => $advert->id, 'valid_until' => now()->addWeek()->toDateTimeString()])->assertOk();
        $this->assertSame(2, AdvertImages::where('submission_id', $id)->where('type', 'ADS_IMAGE')->count());
        $this->assertSame(1, TokenTransaction::where('type', TokenTransaction::SPEND)->count());
    }

    public function test_legacy_full_image_submission_still_uses_existing_platinum_tokens(): void
    {
        $this->fundBoth();
        $this->submit('IMAGE')->assertCreated()->assertJsonPath('data.token_usage.token_type', 'PLATINUM');
        $this->assertSame(87, $this->balance('PLATINUM')->balance);
        $this->assertSame(100, $this->balance('EMERALD')->balance);
    }

    public function test_paid_format_cannot_be_changed_after_publication(): void
    {
        $type = TokenType::where('code', 'EMERALD')->firstOrFail();
        $submission = AdvertSubmission::create([
            'campaign_id' => $this->campaign->id, 'submitted_by' => $this->owner->id, 'type' => 'ADS_IMAGE', 'token_type_id' => $type->id,
            'name' => 'Paid attached image', 'description' => 'Brief', 'capital_invested' => 0, 'status' => 'PUBLISHED',
        ]);
        $advert = $this->advert('ADS_IMAGE', ['submission_id' => $submission->id]);
        try {
            $advert->update(['type' => 'IMAGE']);
            $this->fail('A paid attached image cannot become a full-image advert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }
        $this->assertSame('ADS_IMAGE', $advert->fresh()->type);
    }

    public function test_listing_exposes_distinct_delivery_routes_for_full_and_legacy_attached_images(): void
    {
        $full = $this->advert('IMAGE');
        $ads = $this->advert('ads');
        $this->actingAs($this->member, 'sanctum');
        $data = $this->getJson('/api/v1/campaign/get_product_advert?status=available')->assertOk()->json('data.data');
        $fullData = collect($data)->firstWhere('id', $full->id);
        $adsData = collect($data)->firstWhere('id', $ads->id);
        $this->assertSame('PLATINUM', $fullData['token_type']);
        $this->assertNotNull($fullData['download_url']);
        $this->assertFalse($fullData['requires_personal_image']);
        $this->assertSame('EMERALD', $adsData['token_type']);
        $this->assertNull($adsData['download_url']);
        $this->assertTrue($adsData['requires_personal_image']);
        $this->assertStringEndsWith('/api/v1/image/stamp', $adsData['stamp_url']);
    }

    public function test_full_image_cannot_be_stamped_and_attached_image_cannot_be_downloaded_on_its_own(): void
    {
        $this->actingAs($this->member, 'sanctum');
        $full = $this->advert('IMAGE');
        $ads = $this->advert('ADS_IMAGE');
        $this->post('/api/v1/image/stamp', ['advert_id' => $full->id, 'image' => UploadedFile::fake()->image('personal.png')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('advert_id');
        $this->getJson(route('download.advert.personalized', ['advertId' => $ads->id]))->assertStatus(422)->assertJsonValidationErrors('advert_id');
    }

    public function test_stamp_uses_the_authenticated_member_and_a_verifiable_ownership_reference(): void
    {
        $advert = $this->advert('ADS_IMAGE');
        $this->actingAs($this->member, 'sanctum');
        $response = $this->post('/api/v1/image/stamp', ['advert_id' => $advert->id, 'image' => UploadedFile::fake()->image('personal.png', 300, 500)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('token_type', 'EMERALD');
        $path = public_path('storage/image_ads/encoded/'.$response->json('filename'));
        $proof = app(InvisibleImageWatermarkService::class)->extract($path);
        $verified = app(AdvertQrCodeService::class)->verifyOrFail($proof, $this->member, $advert);
        $this->assertSame($this->member->id, $verified->user_id);
        $this->assertSame($advert->id, $verified->advert_id);
        $this->assertNull(app(AdvertQrCodeService::class)->verify($proof, $this->owner, $advert));
        $this->post('/api/v1/image/stamp', [
            'advert_id' => $advert->id, 'identifier' => $this->owner->my_code, 'image' => UploadedFile::fake()->image('personal.png'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('identifier');
    }

    public function test_stamp_requires_authentication(): void
    {
        $this->withMiddleware();
        $this->postJson('/api/v1/image/stamp', [])->assertUnauthorized();
    }

    public function test_admin_pricing_estimator_wallet_and_purchase_actions_support_both_tokens(): void
    {
        $this->actingAs($this->admin);
        $ads = TokenType::where('code', 'EMERALD')->firstOrFail();
        Livewire::test(ManageTokenTypes::class)->assertSee('Emerald · Attached image');
        Livewire::test(ManageTokenTypes::class)->callTableAction('edit', $ads, [
            'unit_price' => 20, 'currency' => 'KES', 'people_per_token' => 10, 'is_active' => true,
        ])->assertHasNoTableActionErrors();
        $this->assertSame('20.00', $ads->fresh()->unit_price);
        Livewire::test(TokenEstimator::class)->fillForm(['mode' => 'usage', 'token_type' => 'EMERALD', 'target_reach' => 100])
            ->call('calculate')->assertHasNoFormErrors()->assertSet('quote.Token', 'EMERALD')->assertSet('quote.Total tokens required', 13);
        Livewire::test(ManageTokenWallets::class)->callTableAction('adjust', data: [
            'user_id' => $this->owner->id, 'token_type' => 'EMERALD', 'amount' => 20, 'reason' => 'Ads promotion fixture',
        ])->assertHasNoTableActionErrors();
        Livewire::test(ManageTokenPurchases::class)->callTableAction('new_purchase', data: [
            'user_id' => $this->owner->id, 'token_type' => 'EMERALD', 'quantity' => 4,
        ])->assertHasNoTableActionErrors();
        $this->assertSame(20, $this->balance('EMERALD')->balance);
        $this->assertSame('80.00', TokenPurchase::where('user_id', $this->owner->id)->firstOrFail()->total_amount);
    }

    public function test_admin_filters_include_legacy_ads_and_layout_studio_rejects_full_images(): void
    {
        $this->actingAs($this->admin);
        $full = $this->advert('IMAGE');
        $ads = $this->advert('ADS_IMAGE');
        $legacy = $this->advert('ads');
        Livewire::test(ListAdvertImages::class)->filterTable('type', 'ADS_IMAGE')->assertCanSeeTableRecords([$ads, $legacy])->assertCanNotSeeTableRecords([$full]);
        Livewire::test(ImageStudio::class)->fillForm([
            'user_id' => $this->member->id, 'advert_id' => $full->id, 'layout' => 'story', 'image' => UploadedFile::fake()->image('personal.png'),
        ])->call('generate')->assertHasErrors(['data.advert_id']);
    }

    private function enableAds(): void
    {
        TokenType::where('code', 'EMERALD')->update(['unit_price' => 20, 'is_active' => true]);
    }

    private function fundBoth(): void
    {
        $this->enableAds();
        foreach (['PLATINUM', 'EMERALD'] as $code) {
            app(TokenService::class)->adjustBalance($this->owner->id, $code, 100, 'Fixture opening balance');
        }
    }

    private function submit(string $type)
    {
        return $this->actingAs($this->owner, 'sanctum')->post('/api/v1/campaign-owner/campaign/submission', [
            'name' => 'Image format brief', 'description' => 'Design an advert for this posting format.',
            'type' => $type, 'target_reach' => 100, 'images' => [UploadedFile::fake()->image('original.png', 200, 200)],
        ], ['Accept' => 'application/json']);
    }

    private function balance(string $code): TokenWallet
    {
        return TokenWallet::where('user_id', $this->owner->id)->where('token_type_id', TokenType::where('code', $code)->value('id'))->firstOrFail();
    }

    private function advert(string $type, array $attributes = []): AdvertImages
    {
        $upload = UploadedFile::fake()->image('creative.png', 300, 300);
        $directory = public_path('storage/test-creatives');
        File::ensureDirectoryExists($directory);
        $filename = Str::uuid().'.png';
        File::copy($upload->getPathname(), $directory.'/'.$filename);

        return AdvertImages::create($attributes + [
            'name' => 'Fixture '.$type, 'type' => $type, 'campaign_id' => $this->campaign->id,
            'image_path' => 'test-creatives/'.$filename, 'description' => 'Test image advert', 'category' => 'General',
            'capacity' => 100, 'valid_until' => now()->addWeek(), 'capital_invested' => 0, 'selling_price' => 0,
        ]);
    }
}

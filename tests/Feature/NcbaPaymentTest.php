<?php

namespace Tests\Feature;

use App\Filament\Resources\NcbaPaymentResource;
use App\Filament\Resources\NcbaPaymentResource\Pages\ManageNcbaPayments;
use App\Http\Middleware\CheckActiveUser;
use App\Models\NcbaPayment;
use App\Models\Notification;
use App\Models\TokenPurchase;
use App\Models\TokenTransaction;
use App\Models\TokenType;
use App\Models\TokenWallet;
use App\Models\User;
use App\Services\NcbaNotificationVerifier;
use App\Services\NcbaPaymentService;
use App\Services\NcbaReceiptClaimService;
use App\Services\TokenService;
use App\Support\PaymentAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class NcbaPaymentTest extends TestCase
{
    private const CALLBACK = '/api/v1/payments/ncba/notifications';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 10:30:00', 'Africa/Nairobi'));
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'ncba.enabled' => true, 'ncba.auto_credit' => true, 'ncba.contract_verified' => true,
            'ncba.require_https' => false, 'ncba.alias' => '000001',
            'ncba.username' => 'fixture-bank-user', 'ncba.password' => 'fixture-bank-password',
            'ncba.secret' => 'fixture-secret-for-ncba', 'ncba.allowed_ips' => [],
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('admin_media');
        AdminPanelDatabase::build();
        $this->owner = AdminPanelDatabase::user('campaign_owner');
        $this->owner->update(['phone' => '+254711111111']);
        $this->owner->update(['phone_verified_at' => now()->subDay()]);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function test_signature_matches_an_independent_sha256_hex_then_base64_vector(): void
    {
        $payload = $this->payload($this->purchase(), ['TransID' => 'TEST123456', 'TransAmount' => '100.00', 'name' => 'Owner']);
        $this->assertSame('MzgxMjFjNThhMjQ5ZGI2MDlhOTgwZjJjYWQyYmE1ZjlmYjA4NWNmZDE0NzE4NWUzYzg2ZWQ4NTc5Y2JjMmQ2OA==', $payload['Hash']);
        $this->assertSame(123456789012345, PaymentAmount::minor('1234567890123.45'));
        $this->assertSame('1234567890123.45', PaymentAmount::decimal(123456789012345));
    }

    public function test_verified_callback_credits_only_the_saved_purchase_wallet_once(): void
    {
        $purchase = $this->purchase();
        $payload = $this->payload($purchase);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::CALLBACK, $payload)->assertOk()->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Notification received.']);
        }
        $this->assertSame('PAID', $purchase->fresh()->status);
        $this->assertSame('TEST123456', $purchase->fresh()->payment_reference);
        $this->assertSame(5, $this->balance($this->owner, 'PLATINUM'));
        $this->assertSame(1, TokenTransaction::where('type', TokenTransaction::PURCHASE)->count());
        $this->assertSame(1, Notification::where('user_id', $this->owner->id)->count());
        $payment = NcbaPayment::firstOrFail();
        $this->assertSame(NcbaPayment::APPLIED, $payment->status);
        $this->assertSame(3, $payment->delivery_count);
        $this->assertSame(15000, $payment->amount_minor);
        $this->assertSame('2026-11-18 10:30:45', $payment->transacted_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s'));
        $this->assertSame($purchase->id, $payment->token_purchase_id);
    }

    public function test_payment_matching_uses_the_claim_owner_and_saved_token_prices(): void
    {
        $purchase = $this->purchase();
        $other = AdminPanelDatabase::user('campaign_owner');
        $otherPurchase = app(TokenService::class)->createPurchase($other->id, 'GOLD', 3);
        TokenType::where('code', 'PLATINUM')->update(['unit_price' => 35]);
        $payload = $this->payload($purchase);
        $this->postJson(self::CALLBACK, $payload)->assertOk();
        $this->assertSame(5, $this->balance($this->owner, 'PLATINUM'));
        $this->assertSame(0, $this->balance($other, 'GOLD'));
        $this->assertSame('PENDING', $otherPurchase->fresh()->status);
        $this->assertSame('30.00', $purchase->fresh()->unit_price);
    }

    /** @dataProvider rejectedPayloads */
    public function test_invalid_or_unauthenticated_payloads_never_create_receipts_or_tokens(array $changes, int $status): void
    {
        $purchase = $this->purchase();
        $payload = $this->payload($purchase);
        $payload = array_replace($payload, $changes);
        $this->postJson(self::CALLBACK, $payload)->assertStatus($status)->assertJsonPath('ResultCode', '1');
        $this->assertSame(0, NcbaPayment::count());
        $this->assertSame(0, TokenTransaction::count());
        $this->assertSame('PENDING', $purchase->fresh()->status);
    }

    public static function rejectedPayloads(): array
    {
        return [
            'wrong username' => [['Username' => 'someone-else'], 401],
            'wrong password' => [['Password' => 'wrong-password'], 401],
            'wrong alias' => [['BillRefNumber' => 'OTHER'], 401],
            'wrong paybill' => [['BusinessShortCode' => '123456'], 422],
            'tampered amount' => [['TransAmount' => '151.00'], 401],
            'wrong hash' => [['Hash' => str_repeat('A', 88)], 401],
            'numeric amount loses signed formatting' => [['TransAmount' => 150], 422],
            'three decimal places' => [['TransAmount' => '150.001'], 422],
            'negative amount' => [['TransAmount' => '-150.00'], 422],
            'invalid timestamp' => [['TransTime' => '20260230100000'], 422],
            'explicit reversal or failure' => [['Status' => 'REVERSED'], 422],
            'unexpected bank currency' => [['Currency' => 'USD'], 422],
            'array narration' => [['Narrative' => ['bad']], 422],
        ];
    }

    public function test_bank_acknowledges_durably_recorded_exceptions_for_staff_review(): void
    {
        $purchase = $this->purchase();
        foreach (['' => 'MISSING', 'invalid-ref' => 'INVALID', 'VD0000000000' => 'UNKNOWN'] as $narrative => $receipt) {
            $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransID' => $receipt, 'FTRef' => 'FT'.$receipt, 'Narrative' => $narrative]))->assertOk();
        }
        $this->assertSame(3, NcbaPayment::where('status', NcbaPayment::REVIEW)->count());
        $this->assertSame('PENDING', $purchase->fresh()->status);
        $this->assertSame(0, TokenTransaction::count());
    }

    /** @dataProvider mismatchingAmounts */
    public function test_wrong_amounts_remain_in_review_and_cannot_be_force_confirmed(string $amount): void
    {
        $purchase = $this->purchase();
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransAmount' => $amount]))->assertOk();
        $this->assertSame(NcbaPayment::REVIEW, NcbaPayment::firstOrFail()->status);
        $this->assertSame(0, TokenTransaction::count());
        $this->expectException(ValidationException::class);
        app(TokenService::class)->confirmPurchase($purchase->id, 'TEST123456');
    }

    public static function mismatchingAmounts(): array
    {
        return ['underpayment' => ['149.99'], 'overpayment' => ['150.01']];
    }

    public function test_cancelled_and_wrong_currency_purchases_are_not_credited(): void
    {
        $cancelled = $this->purchase();
        app(TokenService::class)->cancelPurchase($cancelled->id, $this->owner->id);
        $usd = $this->purchase();
        $usd->update(['currency' => 'USD']);
        foreach ([$cancelled, $usd] as $index => $purchase) {
            $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransID' => 'REVIEW'.$index, 'FTRef' => 'FTREVIEW'.$index]))->assertOk();
        }
        $this->assertSame(2, NcbaPayment::where('status', NcbaPayment::REVIEW)->count());
        $this->assertSame(0, TokenTransaction::count());
    }

    public function test_changed_duplicate_narrative_cannot_move_a_receipt_to_another_owner(): void
    {
        $first = $this->purchase();
        $other = AdminPanelDatabase::user('campaign_owner');
        $second = app(TokenService::class)->createPurchase($other->id, 'PLATINUM', 5);
        $payload = $this->payload($first);
        $this->postJson(self::CALLBACK, $payload)->assertOk();
        // The guide's signature does not include Narrative; immutable receipt fingerprints still detect replays.
        $payload['Narrative'] = $second->payment_code;
        $this->postJson(self::CALLBACK, $payload)->assertStatus(409);
        $this->assertSame(1, NcbaPayment::count());
        $this->assertSame(1, NcbaPayment::first()->conflict_count);
        $this->assertSame($first->id, NcbaPayment::first()->token_purchase_id);
        $this->assertSame('PENDING', $second->fresh()->status);
        $this->assertSame(0, $this->balance($other, 'PLATINUM'));
        $this->assertSame(1, TokenTransaction::count());
    }

    public function test_second_receipt_for_an_already_paid_order_is_held_for_review(): void
    {
        $purchase = $this->purchase();
        $this->postJson(self::CALLBACK, $this->payload($purchase))->assertOk();
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransID' => 'SECOND', 'FTRef' => 'FTSECOND']))->assertOk();
        $this->assertSame(NcbaPayment::REVIEW, NcbaPayment::where('transaction_id', 'SECOND')->first()->status);
        $this->assertSame(5, $this->balance($this->owner, 'PLATINUM'));
    }

    public function test_core_banking_reference_is_unique_and_equivalent_amount_serializations_are_idempotent(): void
    {
        $purchase = $this->purchase();
        $this->postJson(self::CALLBACK, $this->payload($purchase))->assertOk();
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransAmount' => '150.0']))->assertOk();
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransID' => 'DIFFERENT']))->assertStatus(409);
        $this->assertSame(1, NcbaPayment::count());
        $this->assertSame(1, TokenTransaction::count());
    }

    public function test_auto_crediting_requires_both_explicit_enablement_and_bank_uat_confirmation(): void
    {
        $purchase = $this->purchase();
        config(['ncba.contract_verified' => false]);
        $this->postJson(self::CALLBACK, $this->payload($purchase))->assertOk();
        $this->assertSame(NcbaPayment::REVIEW, NcbaPayment::first()->status);
        $this->assertSame(0, TokenTransaction::count());
        config(['ncba.contract_verified' => true, 'ncba.auto_credit' => false]);
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransID' => 'AUTOOFF', 'FTRef' => 'FTAUTOOFF']))->assertOk();
        $this->assertSame(2, NcbaPayment::where('status', NcbaPayment::REVIEW)->count());
        $this->assertSame(0, TokenTransaction::count());
    }

    public function test_disabled_or_incomplete_configuration_rejects_callbacks(): void
    {
        $payload = $this->payload($this->purchase());
        config(['ncba.enabled' => false]);
        $this->postJson(self::CALLBACK, $payload)->assertStatus(503);
        config(['ncba.enabled' => true, 'ncba.secret' => '']);
        $this->postJson(self::CALLBACK, $payload)->assertStatus(503);
        $this->assertSame(0, NcbaPayment::count());
    }

    public function test_https_ip_allowlist_size_and_json_format_are_enforced(): void
    {
        $payload = $this->payload($this->purchase());
        config(['ncba.require_https' => true]);
        $this->postJson(self::CALLBACK, $payload)->assertForbidden();
        config(['ncba.require_https' => false, 'ncba.allowed_ips' => ['192.0.2.0/24']]);
        $this->postJson(self::CALLBACK, $payload)->assertForbidden();
        config(['ncba.allowed_ips' => []]);
        $this->call('POST', self::CALLBACK, [], [], [], ['CONTENT_TYPE' => 'text/xml'], '<xml/>')->assertStatus(415);
        $this->call('POST', self::CALLBACK, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{bad-json')->assertStatus(422);
        $this->postJson(self::CALLBACK, $payload + ['extra' => str_repeat('X', 17000)])->assertStatus(413);
        $this->assertSame(0, NcbaPayment::count());
    }

    public function test_https_and_allowed_source_are_accepted_without_a_member_bearer_token(): void
    {
        config(['ncba.require_https' => true, 'ncba.allowed_ips' => ['127.0.0.1/32']]);
        $this->postJson('https://localhost'.self::CALLBACK, $this->payload($this->purchase()))->assertOk();
        $this->assertSame(1, TokenTransaction::count());
    }

    public function test_zero_payment_is_rejected_even_with_a_valid_signature(): void
    {
        $this->postJson(self::CALLBACK, $this->payload($this->purchase(), ['TransAmount' => '0.00']))->assertStatus(422);
        $this->assertSame(0, NcbaPayment::count());
    }

    public function test_signed_strings_are_not_trimmed_and_secrets_are_not_stored(): void
    {
        $payload = $this->payload($this->purchase(), ['name' => '  Campaign Owner  ']);
        $this->postJson(self::CALLBACK, $payload)->assertOk();
        $payment = NcbaPayment::firstOrFail();
        $this->assertSame('  Campaign Owner  ', $payment->payer_name);
        $stored = DB::table('ncba_payments')->first();
        $this->assertNotSame($payload['Mobile'], $stored->payer_mobile);
        $this->assertNotSame($payload['name'], $stored->payer_name);
        $storedJson = json_encode($stored);
        foreach (['Username', 'Password', 'Hash'] as $key) {
            $this->assertStringNotContainsString($payload[$key], $storedJson);
        }
        $this->assertArrayNotHasKey('payer_mobile', $payment->toArray());
    }

    public function test_failed_database_work_rolls_back_credit_and_receipt_and_can_be_retried(): void
    {
        $purchase = $this->purchase();
        $payload = $this->payload($purchase);
        $fail = true;
        Notification::creating(function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('Deliberate fixture failure');
            }
        });
        Log::spy();
        $this->postJson(self::CALLBACK, $payload)->assertStatus(503)->assertJsonPath('ResultCode', '1');
        $this->assertSame(0, NcbaPayment::count());
        $this->assertSame(0, TokenTransaction::count());
        $this->assertSame('PENDING', $purchase->fresh()->status);
        $fail = false;
        $this->postJson(self::CALLBACK, $payload)->assertOk();
        $this->assertSame(5, $this->balance($this->owner, 'PLATINUM'));
        $this->assertSame(1, Notification::count());
        Log::shouldHaveReceived('error')->once()->with('NCBA notification could not be committed.', ['exception_type' => \RuntimeException::class]);
    }

    public function test_purchase_api_returns_instructions_and_owner_scoped_status(): void
    {
        $this->withoutMiddleware(CheckActiveUser::class)->actingAs($this->owner, 'sanctum');
        $response = $this->postJson('/api/v1/tokens/purchases', ['token_type' => 'PLATINUM', 'quantity' => 5])->assertCreated();
        $id = $response->json('data.id');
        $code = $response->json('data.payment_code');
        $this->assertMatchesRegularExpression('/^VD[A-F0-9]{10}$/D', $code);
        $response->assertJsonPath('data.payment_instructions.account_number', '000001')
            ->assertJsonPath('data.payment_instructions.amount', '150.00');
        $this->getJson('/api/v1/tokens/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->getJson('/api/v1/tokens/purchases')->assertOk()->assertJsonPath('data.0.payment_code', $code);
        $this->postJson('/api/v1/tokens/purchases/'.$id.'/receipt', ['receipt_number' => 'TEST123456'])->assertStatus(202);
        $this->postJson(self::CALLBACK, $this->payload(TokenPurchase::findOrFail($id)))->assertOk();
        $this->getJson('/api/v1/tokens/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'PAID')->assertJsonPath('data.payment_instructions', null);
        $this->actingAs(AdminPanelDatabase::user('campaign_owner'), 'sanctum');
        $this->getJson('/api/v1/tokens/purchases/'.$id)->assertNotFound();
    }

    public function test_admin_can_reconcile_a_missing_reference_once_with_audited_notes(): void
    {
        $purchase = $this->purchase(false);
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['Narrative' => '']))->assertOk();
        $payment = NcbaPayment::firstOrFail();
        $admin = AdminPanelDatabase::user();
        $this->actingAs($admin);
        $page = Livewire::test(ManageNcbaPayments::class)->assertSee('TEST123456')
            ->mountTableAction('view', $payment)->assertSuccessful()->assertSee('Owner')->unmountTableAction();
        $page->callTableAction('match', $payment, [
            'token_purchase_id' => $purchase->id, 'notes' => 'Verified with the owner and bank receipt.', 'verified_owner' => true,
        ])->assertHasNoTableActionErrors();
        app(NcbaPaymentService::class)->apply($payment->id, $purchase->id, $admin, 'Verified with the owner and bank receipt.');
        $this->assertSame($admin->id, $payment->fresh()->reviewed_by);
        $this->assertSame(5, $this->balance($this->owner, 'PLATINUM'));
        $this->assertSame(1, TokenTransaction::count());
        $this->assertSame(1, Notification::where('user_id', $this->owner->id)->count());
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('title', 'NCBA purchase approved')->count());
    }

    public function test_admin_can_record_external_reconciliation_without_sending_money_or_tokens(): void
    {
        $purchase = $this->purchase(false);
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['Narrative' => '']))->assertOk();
        $payment = NcbaPayment::firstOrFail();
        $this->actingAs(AdminPanelDatabase::user());
        Livewire::test(ManageNcbaPayments::class)->callTableAction('resolve', $payment, ['notes' => 'Refund already completed by bank: REF123.'])->assertHasNoTableActionErrors();
        $this->assertSame(NcbaPayment::RESOLVED, $payment->fresh()->status);
        $this->assertSame(0, TokenTransaction::count());
        $this->assertSame('PENDING', $purchase->fresh()->status);
    }

    public function test_payment_notifications_are_visible_and_changeable_only_by_their_owner(): void
    {
        $this->postJson(self::CALLBACK, $this->payload($this->purchase()))->assertOk();
        $notification = Notification::where('user_id', $this->owner->id)->firstOrFail();
        $this->withoutMiddleware(CheckActiveUser::class)->actingAs($this->owner, 'sanctum');
        $this->getJson('/api/v1/notifications/user?user_id='.$this->owner->id)->assertOk()->assertJsonPath('notifications.0.title', 'Payment received');
        $this->actingAs(AdminPanelDatabase::user('member'), 'sanctum');
        $data = ['user_id' => $this->owner->id, 'notification_id' => $notification->id];
        $this->getJson('/api/v1/notifications/user?user_id='.$this->owner->id)->assertForbidden();
        $this->getJson('/api/v1/notifications/stats?user_id='.$this->owner->id)->assertForbidden();
        $this->postJson('/api/v1/notifications/mark-read', $data)->assertForbidden();
        $this->postJson('/api/v1/notifications/mark-all-read', $data)->assertForbidden();
        $this->deleteJson('/api/v1/notifications/delete', $data)->assertForbidden();
        $this->assertFalse($notification->fresh()->is_read);
        $this->assertSame(1, Notification::count());
        $this->actingAs($this->owner, 'sanctum');
        $this->postJson('/api/v1/notifications/mark-read', $data)->assertOk();
        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_review_cannot_force_amount_mismatches_or_conflicting_receipts(): void
    {
        $purchase = $this->purchase();
        $this->postJson(self::CALLBACK, $this->payload($purchase, ['TransAmount' => '100.00']))->assertOk();
        $payment = NcbaPayment::firstOrFail();
        $this->actingAs(AdminPanelDatabase::user());
        Livewire::test(ManageNcbaPayments::class)->callTableAction('match', $payment, [
            'token_purchase_id' => $purchase->id, 'notes' => 'Confirmed owner, but amount is wrong.', 'verified_owner' => true,
        ])->assertHasErrors(['token_purchase_id']);
        $this->assertSame(0, TokenTransaction::count());
        $this->assertNull($payment->fresh()->token_purchase_id);
    }

    public function test_managers_and_members_cannot_access_payment_reconciliation(): void
    {
        foreach (['manager', 'member'] as $role) {
            $this->actingAs(AdminPanelDatabase::user($role));
            $this->get(NcbaPaymentResource::getUrl('index'))->assertForbidden();
        }
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NcbaPaymentService::class)->apply('anything', 'anything', $this->owner, 'Not authorized to allocate payments.');
    }

    public function test_migration_rerun_and_rollback_preserve_receipts_prices_and_references(): void
    {
        $purchase = $this->purchase();
        $this->postJson(self::CALLBACK, $this->payload($purchase))->assertOk();
        $before = DB::table('ncba_payments')->get()->toJson();
        $migration = require database_path('migrations/2026_11_18_100000_add_ncba_payment_confirmations.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, DB::table('ncba_payments')->get()->toJson());
        $this->assertSame('150.00', $purchase->fresh()->total_amount);
        $this->assertSame($purchase->payment_code, $purchase->fresh()->payment_code);
        $this->assertSame(5, $this->balance($this->owner, 'PLATINUM'));
    }

    private function purchase(bool $submitClaim = true): TokenPurchase
    {
        $purchase = app(TokenService::class)->createPurchase($this->owner->id, 'PLATINUM', 5);
        if ($submitClaim) {
            app(NcbaReceiptClaimService::class)->submit($this->owner, $purchase->id, 'TEST123456');
        }

        return $purchase;
    }

    private function payload(TokenPurchase $purchase, array $changes = []): array
    {
        $data = array_replace([
            'TransType' => 'Pay Bill', 'TransID' => 'TEST123456', 'FTRef' => 'FTTEST123456',
            'TransTime' => '20261118103045', 'TransAmount' => $purchase->total_amount,
            'BusinessShortCode' => '880100', 'BillRefNumber' => '000001', 'Narrative' => $purchase->payment_code,
            'Mobile' => '254711111111', 'name' => 'Owner',
            'Username' => 'fixture-bank-user', 'Password' => 'fixture-bank-password',
        ], $changes);
        $data['Hash'] = app(NcbaNotificationVerifier::class)->signature($data);

        return $data;
    }

    private function balance(User $owner, string $code): int
    {
        return (int) TokenWallet::where('user_id', $owner->id)->where('token_type_id', TokenType::where('code', $code)->value('id'))->value('balance');
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\NcbaReceiptClaimResource;
use App\Filament\Resources\NcbaReceiptClaimResource\Pages\ManageNcbaReceiptClaims;
use App\Filament\Widgets\NcbaPaymentNotifications;
use App\Http\Middleware\CheckActiveUser;
use App\Models\NcbaPayment;
use App\Models\NcbaPaymentEvent;
use App\Models\NcbaReceiptClaim;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
class NcbaReceiptClaimTest extends TestCase
{
    private const CALLBACK = '/api/v1/payments/ncba/notifications';

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'Africa/Nairobi'));
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'ncba.enabled' => true, 'ncba.auto_credit' => true, 'ncba.contract_verified' => true,
            'ncba.require_https' => false, 'ncba.alias' => '906854',
            'ncba.username' => 'fixture-user', 'ncba.password' => 'fixture-password',
            'ncba.secret' => 'fixture-secret', 'ncba.allowed_ips' => [],
            'cache.default' => 'array',
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
        $this->admin = AdminPanelDatabase::user('admin');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function test_fixed_account_instructions_and_bank_before_claim_approve_and_notify_once(): void
    {
        $purchase = $this->purchase();
        $instructions = app(NcbaPaymentService::class)->instructions($purchase);
        $this->assertSame('906854', $instructions['account_number']);
        $this->assertSame('880100', $instructions['paybill']);
        $this->assertSame('RECEIPT_CLAIM', $instructions['confirmation_method']);
        $payload = $this->bank($purchase);
        $this->assertSame(TokenPurchase::PENDING, $purchase->fresh()->status);
        $this->login();
        $url = $this->claimUrl($purchase);
        $this->postJson($url, ['receipt_number' => 'receipt001'])->assertOk()
            ->assertJsonPath('data.claim.status', 'APPROVED')->assertJsonPath('data.purchase.status', 'PAID');
        $this->postJson($url, ['receipt_number' => 'RECEIPT001'])->assertOk();
        $this->postJson(self::CALLBACK, $payload)->assertOk();
        $this->assertSame(5, $this->balance());
        $this->assertSame(1, NcbaReceiptClaim::count());
        $this->assertSame(2, NcbaReceiptClaim::first()->submission_count);
        $this->assertSame(1, TokenTransaction::count());
        $this->assertSame(1, NcbaPaymentEvent::where('event_type', 'TOKENS_CREDITED')->count());
        $this->assertSame(1, Notification::where('user_id', $this->owner->id)->where('title', 'Payment received')->count());
        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->where('title', 'NCBA purchase approved')->count());
    }

    public function test_claim_before_bank_callback_waits_then_credits_without_another_user_request(): void
    {
        $purchase = $this->purchase();
        $this->login();
        $this->postJson($this->claimUrl($purchase), ['receipt_number' => 'RECEIPT001'])->assertStatus(202)
            ->assertJsonPath('data.claim.status', 'WAITING_BANK');
        $this->assertSame(0, $this->balance());
        $this->bank($purchase);
        $this->getJson('/api/v1/tokens/purchases/'.$purchase->id)->assertOk()
            ->assertJsonPath('data.status', 'PAID')->assertJsonPath('data.receipt_claim.status', 'APPROVED');
        $this->assertSame(5, $this->balance());
    }

    public function test_narrative_never_allocates_without_an_explicit_receipt_claim(): void
    {
        $purchase = $this->purchase();
        $this->bank($purchase, ['Narrative' => $purchase->payment_code]);
        $this->assertSame(0, $this->balance());
        $this->assertSame('REVIEW', NcbaPayment::first()->status);
        $this->assertSame('APPROVED', $this->claim($purchase)->status);
    }

    public function test_authentication_and_purchase_ownership_are_enforced_and_history_does_not_leak_payer_data(): void
    {
        $purchase = $this->purchase();
        $url = $this->claimUrl($purchase);
        $this->postJson($url, ['receipt_number' => 'RECEIPT001'])->assertUnauthorized();
        $this->login(AdminPanelDatabase::user('campaign_owner'));
        $this->postJson($url, ['receipt_number' => 'RECEIPT001'])->assertNotFound();
        $this->assertSame(0, NcbaReceiptClaim::count());
        $this->bank($purchase);
        $this->login();
        $json = $this->postJson($url, ['receipt_number' => 'RECEIPT001'])->assertOk()->getContent();
        foreach (['payer_mobile', 'payer_name', 'fingerprint', 'phone_verified_at_snapshot', 'owner_phone_snapshot', 'fixture-secret', 'Bank Payer'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $stored = DB::table('ncba_receipt_claims')->first();
        $this->assertNotSame('254711111111', $stored->owner_phone_snapshot);
        $this->assertStringNotContainsString($this->owner->id, $stored->purchase_snapshot);
    }

    /** @dataProvider badReceipts */
    public function test_invalid_receipt_formats_are_rejected_without_creating_claims(mixed $receipt): void
    {
        $purchase = $this->purchase();
        $this->login();
        $this->postJson($this->claimUrl($purchase), ['receipt_number' => $receipt])->assertUnprocessable();
        $this->assertSame(0, NcbaReceiptClaim::count());
        $this->assertSame(0, TokenTransaction::count());
    }

    public static function badReceipts(): array
    {
        return [[''], [null], [['RECEIPT001']], ['SMS full receipt pasted here!'], [str_repeat('A', 33)], ['TEST-123'], [1234567890]];
    }

    /** @dataProvider wrongAmounts */
    public function test_under_and_overpayments_never_credit_or_change_saved_totals(string $amount): void
    {
        $purchase = $this->purchase();
        $this->bank($purchase, ['TransAmount' => $amount]);
        $claim = $this->claim($purchase);
        $this->assertSame('REVIEW', $claim->status);
        $this->assertSame('AMOUNT_OR_CURRENCY_MISMATCH', $claim->reason_code);
        $this->assertStringContainsString('AMOUNT_OR_CURRENCY_MISMATCH', NcbaPayment::first()->review_reason);
        $this->assertSame('150.00', $purchase->fresh()->total_amount);
        $this->assertSame(0, $this->balance());
    }

    public static function wrongAmounts(): array
    {
        return [['149.99'], ['150.01']];
    }

    /** @dataProvider ambiguousPayers */
    public function test_copied_or_hashed_payer_numbers_need_admin_review(string $phone): void
    {
        $purchase = $this->purchase();
        $this->bank($purchase, ['Mobile' => $phone]);
        $claim = $this->claim($purchase);
        $this->assertSame('REVIEW', $claim->status);
        $this->assertSame('OWNERSHIP_REQUIRES_REVIEW', $claim->reason_code);
        $this->assertSame(0, $this->balance());
        app(NcbaPaymentService::class)->apply(NcbaPayment::first()->id, $purchase->id, $this->admin, 'Verified third-party payer with owner and bank.');
        $this->assertSame(5, $this->balance());
        $this->assertSame('APPROVED', $claim->fresh()->status);
        $this->assertSame('STAFF_APPROVED', $claim->fresh()->reason_code);
    }

    public static function ambiguousPayers(): array
    {
        return [['254722222222'], [str_repeat('a', 64)]];
    }

    public function test_unverified_phone_is_not_automatic_proof_and_verifying_then_resubmitting_keeps_both_snapshots(): void
    {
        $this->owner->update(['phone_verified_at' => null]);
        $purchase = $this->purchase();
        $this->bank($purchase);
        $first = $this->claim($purchase);
        $this->assertSame('REVIEW', $first->status);
        $this->owner->update(['phone_verified_at' => now()]);
        $second = $this->claim($purchase);
        $this->assertSame('APPROVED', $second->status);
        $this->assertSame('SUPERSEDED', $first->fresh()->status);
        $this->assertNull($first->fresh()->phone_verified_at_snapshot);
        $this->assertSame(2, NcbaReceiptClaim::count());
    }

    public function test_other_users_cannot_steal_a_receipt_by_claiming_first(): void
    {
        $purchase = $this->purchase();
        $intruder = AdminPanelDatabase::user('campaign_owner');
        $intruder->update(['phone_verified_at' => now()->subDay()]);
        $wrong = app(TokenService::class)->createPurchase($intruder->id, 'PLATINUM', 5);
        $badClaim = app(NcbaReceiptClaimService::class)->submit($intruder, $wrong->id, 'RECEIPT001');
        $this->bank($purchase);
        $this->assertSame(0, $this->balance());
        $this->assertSame('REVIEW', $badClaim->fresh()->status);
        $this->assertSame('APPROVED', $this->claim($purchase)->status);
        $this->assertSame('REJECTED', $badClaim->fresh()->status);
        $this->assertSame('PENDING', $wrong->fresh()->status);
        $this->assertSame(0, TokenWallet::where('user_id', $intruder->id)->sum('balance'));
    }

    public function test_receipt_reuse_across_orders_and_second_receipt_on_paid_order_do_not_credit_again(): void
    {
        $purchase = $this->purchase();
        $this->bank($purchase);
        $approved = $this->claim($purchase);
        $second = $this->purchase();
        $this->assertSame('REJECTED', $this->claim($second)->status);
        $this->assertSame('REJECTED', $this->claim($purchase, 'OTHERRECEIPT')->status);
        $this->assertSame('APPROVED', $approved->fresh()->status);
        $this->assertSame('RECEIPT001', $purchase->fresh()->payment_reference);
        $this->assertSame(1, TokenTransaction::count());
    }

    public function test_receipt_corrections_preserve_history_and_late_payment_for_superseded_claim_is_not_credited(): void
    {
        $purchase = $this->purchase();
        $first = $this->claim($purchase, 'WRONGRECEIPT');
        $second = $this->claim($purchase);
        $this->bank($purchase, ['TransID' => 'WRONGRECEIPT', 'FTRef' => 'FTWRONG']);
        $this->assertSame(0, $this->balance());
        $this->bank($purchase);
        $this->assertSame('SUPERSEDED', $first->fresh()->status);
        $this->assertSame('APPROVED', $second->fresh()->status);
        $this->assertSame(5, $this->balance());
        $this->assertSame('REVIEW', NcbaPayment::where('transaction_id', 'WRONGRECEIPT')->first()->status);
    }

    public function test_cancelled_order_keeps_claim_and_late_bank_receipt_for_review(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        app(TokenService::class)->cancelPurchase($purchase->id, $this->owner->id);
        $this->bank($purchase);
        $this->assertSame('CANCELLED', $purchase->fresh()->status);
        $this->assertSame('REJECTED', $claim->fresh()->status);
        $this->assertSame('REVIEW', NcbaPayment::first()->status);
        $this->assertSame(0, $this->balance());
        $this->assertSame(1, NcbaPaymentEvent::where('event_type', 'PURCHASE_CANCELLED')->count());
    }

    public function test_two_eligible_orders_for_one_receipt_are_not_guessed_and_admin_can_resolve_once(): void
    {
        config(['ncba.auto_credit' => false]);
        $first = $this->purchase();
        $second = $this->purchase();
        $a = $this->claim($first);
        $b = $this->claim($second);
        $this->bank($first);
        config(['ncba.auto_credit' => true]);
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame('MULTIPLE_ELIGIBLE_PURCHASES', $a->fresh()->reason_code);
        $this->assertSame('MULTIPLE_ELIGIBLE_PURCHASES', $b->fresh()->reason_code);
        $this->assertSame(0, $this->balance());
        $payment = NcbaPayment::first();
        app(NcbaPaymentService::class)->apply($payment->id, $second->id, $this->admin, 'Owner confirmed the second order is intended.');
        app(NcbaPaymentService::class)->apply($payment->id, $second->id, $this->admin, 'Idempotent retry of the verified allocation.');
        $this->assertSame('REJECTED', $a->fresh()->status);
        $this->assertSame('APPROVED', $b->fresh()->status);
        $this->assertSame(1, TokenTransaction::count());
    }

    public function test_stale_claim_is_escalated_once_and_a_delayed_callback_can_still_confirm_it(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $this->travel(49)->hours();
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame('BANK_CONFIRMATION_DELAYED', $claim->fresh()->reason_code);
        $this->assertSame(1, NcbaPaymentEvent::where('event_type', 'CLAIM_REVIEW')->count());
        $this->bank($purchase);
        $this->assertSame('APPROVED', $claim->fresh()->status);
        $this->assertSame(5, $this->balance());
    }

    public function test_bank_confirmation_and_explicit_auto_enablement_are_required_even_for_correct_receipts(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        try {
            app(TokenService::class)->confirmPurchase($purchase->id, 'RECEIPT001');
            $this->fail('An unconfirmed bank receipt must not be force-credited.');
        } catch (ValidationException) {
            $this->assertSame(0, $this->balance());
        }
        config(['ncba.contract_verified' => false]);
        $this->bank($purchase);
        $this->assertSame('AUTOMATIC_APPROVAL_DISABLED', $claim->fresh()->reason_code);
        config(['ncba.contract_verified' => true, 'ncba.auto_credit' => false]);
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame(0, $this->balance());
        config(['ncba.auto_credit' => true]);
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame(5, $this->balance());
    }

    public function test_changed_phone_loses_verification_and_cannot_take_another_payers_receipt(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $this->owner->update(['phone' => '+254722222222', 'phone_verified_at' => now()]);
        $this->assertNull($this->owner->fresh()->phone_verified_at);
        $this->bank($purchase);
        $this->assertSame('OWNERSHIP_REQUIRES_REVIEW', $claim->fresh()->reason_code);
        $this->assertSame(0, $this->balance());
    }

    public function test_duplicate_verified_phone_formats_are_ambiguous_not_automatic_ownership(): void
    {
        $other = AdminPanelDatabase::user('campaign_owner');
        $other->update(['phone' => '0711111111']);
        $other->update(['phone_verified_at' => now()->subDay()]);
        $purchase = $this->purchase();
        $this->bank($purchase);
        $claim = $this->claim($purchase);
        $this->assertSame('AMBIGUOUS_PHONE_OWNERSHIP', $claim->reason_code);
        $this->assertSame(0, $this->balance());
    }

    /** @dataProvider reviewOnlyTimes */
    public function test_old_or_future_bank_times_need_review(string $time): void
    {
        $purchase = $this->purchase();
        $this->bank($purchase, ['TransTime' => $time]);
        $claim = $this->claim($purchase);
        $this->assertSame('PAYMENT_TIME_REQUIRES_REVIEW', $claim->reason_code);
        $this->assertSame(0, $this->balance());
    }

    public static function reviewOnlyTimes(): array
    {
        return [['20261118090000'], ['20261119110000']];
    }

    public function test_price_snapshot_is_honoured_but_editing_the_purchase_after_claim_needs_review(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        TokenType::where('code', 'PLATINUM')->update(['unit_price' => 35]);
        $this->bank($purchase);
        $this->assertSame('APPROVED', $claim->fresh()->status);
        $another = $this->purchase();
        $next = $this->claim($another, 'NEXTRECEIPT');
        $another->update(['quantity' => 9]);
        $this->bank($another, ['TransID' => 'NEXTRECEIPT', 'FTRef' => 'FTNEXT']);
        $this->assertSame('PURCHASE_CHANGED', $next->fresh()->reason_code);
        $this->assertSame(5, $this->balance());
    }

    public function test_failed_admin_notification_rolls_back_bank_credit_and_audit_then_retry_succeeds(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $before = NcbaPaymentEvent::count();
        $fail = true;
        $adminId = $this->admin->id;
        Notification::creating(function ($notification) use (&$fail, $adminId) {
            if ($fail && $notification->user_id === $adminId) {
                throw new \RuntimeException('Simulated notification persistence failure');
            }
        });
        $data = $this->payload($purchase);
        $this->postJson(self::CALLBACK, $data)->assertStatus(503);
        $this->assertSame(0, NcbaPayment::count());
        $this->assertSame(0, TokenTransaction::count());
        $this->assertSame($before, NcbaPaymentEvent::count());
        $this->assertSame('WAITING_BANK', $claim->fresh()->status);
        $fail = false;
        $this->postJson(self::CALLBACK, $data)->assertOk();
        $this->assertSame(5, $this->balance());
    }

    public function test_staff_rejection_is_audited_and_cannot_be_bypassed_by_resubmitting(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $this->actingAs($this->admin);
        Livewire::test(ManageNcbaReceiptClaims::class)->callTableAction('reject', $claim,
            ['notes' => 'Verified claim is disputed; hold for investigation.'])->assertHasNoTableActionErrors();
        $this->bank($purchase);
        $this->assertSame('REJECTED', $this->claim($purchase)->status);
        $this->claim($purchase, 'TYPORECEIPT');
        $new = $this->claim($purchase);
        $this->assertSame('PREVIOUS_STAFF_REJECTION', $new->reason_code);
        $this->assertSame(0, $this->balance());
        $event = NcbaPaymentEvent::where('event_type', 'CLAIM_REJECTED')->first();
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertArrayHasKey('notes', $event->details);
    }

    public function test_external_resolution_and_conflicts_never_auto_credit_pending_claims(): void
    {
        config(['ncba.auto_credit' => false]);
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $data = $this->bank($purchase);
        $data['Narrative'] = 'Changed unsigned narration';
        $this->postJson(self::CALLBACK, $data)->assertStatus(409);
        config(['ncba.auto_credit' => true]);
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame('RECEIPT_UNAVAILABLE', $claim->fresh()->reason_code);
        app(NcbaPaymentService::class)->resolve(NcbaPayment::first()->id, $this->admin, 'Bank investigated conflict and refunded outside application.');
        $this->assertSame('REJECTED', $claim->fresh()->status);
        $this->assertNull(NcbaPayment::first()->review_reason);
        $this->assertSame(0, $this->balance());
    }

    public function test_admin_payment_widget_and_claim_history_are_finance_only(): void
    {
        $purchase = $this->purchase();
        $this->bank($purchase);
        $claim = $this->claim($purchase);
        $this->actingAs($this->admin);
        Livewire::test(NcbaPaymentNotifications::class)->assertSee('NCBA purchase approved')->assertSee('RECEIPT001');
        Livewire::test(ManageNcbaReceiptClaims::class)->mountTableAction('history', $claim)
            ->assertSee('CLAIM SUBMITTED')->assertSee('CLAIM APPROVED')->assertSee('150.00');
        foreach (['manager', 'member'] as $role) {
            $this->actingAs(AdminPanelDatabase::user($role));
            $this->get(NcbaReceiptClaimResource::getUrl('index'))->assertForbidden();
            $this->assertFalse(NcbaPaymentNotifications::canView());
        }
    }

    public function test_notifications_only_reach_active_finance_users_and_not_other_owners(): void
    {
        $developer = AdminPanelDatabase::user('dev');
        $inactive = AdminPanelDatabase::user('admin');
        $inactive->update(['is_active' => false]);
        $manager = AdminPanelDatabase::user('manager');
        $purchase = $this->purchase();
        $this->claim($purchase);
        $this->bank($purchase);
        $this->assertSame(1, Notification::where('user_id', $developer->id)->where('title', 'NCBA purchase approved')->count());
        $this->assertSame(0, Notification::whereIn('user_id', [$inactive->id, $manager->id])->count());
    }

    public function test_receipt_endpoint_is_rate_limited(): void
    {
        $purchase = $this->purchase();
        $this->login();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->claimUrl($purchase), ['receipt_number' => 'RECEIPT001'])->assertStatus(202);
        }
        $this->postJson($this->claimUrl($purchase), ['receipt_number' => 'RECEIPT001'])->assertStatus(429);
        $this->assertSame(1, NcbaReceiptClaim::count());
    }

    public function test_financial_migration_reruns_and_rollback_preserve_claims_and_immutable_events(): void
    {
        $purchase = $this->purchase();
        $this->claim($purchase);
        $this->bank($purchase);
        $before = DB::table('ncba_payment_events')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_11_19_100000_add_ncba_receipt_claims.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, DB::table('ncba_payment_events')->orderBy('id')->get()->toJson());
        $this->assertSame(1, NcbaReceiptClaim::count());
        $this->expectException(\LogicException::class);
        NcbaPaymentEvent::first()->update(['event_type' => 'REWRITTEN']);
    }

    public function test_quote_uses_exact_minor_units_and_rejects_totals_that_exceed_database_precision(): void
    {
        TokenType::where('code', 'PLATINUM')->update(['unit_price' => '30.11']);
        $this->assertSame('3011.00', app(TokenService::class)->quote('PLATINUM', 100)['total_amount']);
        TokenType::where('code', 'PLATINUM')->update(['unit_price' => '9999999999.99']);
        $this->expectException(ValidationException::class);
        app(TokenService::class)->quote('PLATINUM', 100000);
    }

    public function test_original_validated_bank_fields_are_encrypted_hidden_and_never_overwritten_by_retries(): void
    {
        $purchase = $this->purchase();
        $data = $this->bank($purchase, ['TransAmount' => '150.0']);
        $payment = NcbaPayment::first();
        $this->assertSame('150.0', $payment->verified_payload['TransAmount']);
        foreach (['Username', 'Password', 'Hash'] as $secret) {
            $this->assertArrayNotHasKey($secret, $payment->verified_payload);
        }
        $this->assertArrayNotHasKey('verified_payload', $payment->toArray());
        $encrypted = DB::table('ncba_payments')->value('verified_payload');
        $this->assertStringNotContainsString('Bank Payer', $encrypted);
        $this->bank($purchase, ['TransAmount' => '150.00']);
        $this->assertSame($encrypted, DB::table('ncba_payments')->value('verified_payload'));
        $this->assertSame(2, $payment->fresh()->delivery_count);
    }

    public function test_inactive_or_deleted_purchase_owner_never_gets_automatic_credit(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $this->owner->update(['is_active' => false]);
        $this->bank($purchase);
        $this->assertSame('OWNERSHIP_REQUIRES_REVIEW', $claim->fresh()->reason_code);
        $this->owner->delete();
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame(0, $this->balance());
        $this->assertSame('PENDING', $purchase->fresh()->status);
    }

    public function test_cancellation_audit_records_the_actual_admin_not_the_purchase_owner(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        app(TokenService::class)->cancelPurchase($purchase->id, $this->owner->id, $this->admin);
        $this->assertSame($this->admin->id, NcbaPaymentEvent::where('event_type', 'PURCHASE_CANCELLED')->first()->actor_id);
        $this->bank($purchase);
        $this->assertSame(NcbaPayment::first()->id, $claim->fresh()->ncba_payment_id);
        $this->assertSame('REJECTED', $claim->fresh()->status);
    }

    public function test_reconciliation_keyset_batches_do_not_skip_rows_as_approved_claims_leave_the_query(): void
    {
        config(['ncba.auto_credit' => false]);
        for ($i = 0; $i < 105; $i++) {
            $purchase = $this->purchase();
            $receipt = 'BATCH'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $this->claim($purchase, $receipt);
            $data = $this->payload($purchase, ['TransID' => $receipt, 'FTRef' => 'FT'.$receipt]);
            app(NcbaPaymentService::class)->receive(app(NcbaNotificationVerifier::class)->verify($data));
        }
        config(['ncba.auto_credit' => true]);
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame(105, NcbaReceiptClaim::where('status', 'APPROVED')->count());
        $this->assertSame(105, TokenTransaction::count());
        $this->assertSame(525, $this->balance());
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame(105, TokenTransaction::count());
    }

    public function test_actual_console_migration_installs_and_preserves_existing_orders_through_rollback_and_reapply(): void
    {
        // Only this test's disposable in-memory schema is changed, never the configured live DB.
        \Illuminate\Support\Facades\Schema::drop('ncba_payment_events');
        \Illuminate\Support\Facades\Schema::drop('ncba_receipt_claims');
        \Illuminate\Support\Facades\Schema::drop('ncba_receipt_keys');
        \Illuminate\Support\Facades\Schema::table('ncba_payments', fn ($table) => $table->dropColumn('verified_payload'));
        $purchase = $this->purchase();
        $historical = $this->purchase();
        $historical->update(['status' => 'PAID', 'payment_reference' => 'HISTORICAL', 'paid_at' => now()->subDay()]);
        $path = 'database/migrations/2026_11_19_100000_add_ncba_receipt_claims.php';
        $this->artisan('migrate', ['--path' => $path, '--force' => true])->assertExitCode(0);
        $this->claim($purchase);
        $this->bank($purchase);
        $before = DB::table('ncba_payment_events')->orderBy('sequence')->get()->toJson();
        $this->artisan('migrate:rollback', ['--path' => $path, '--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--path' => $path, '--force' => true])->assertExitCode(0);
        $this->assertSame($before, DB::table('ncba_payment_events')->orderBy('sequence')->get()->toJson());
        $this->assertSame('HISTORICAL', $historical->fresh()->payment_reference);
        $this->assertSame('150.00', $historical->fresh()->total_amount);
        $this->assertSame(5, $this->balance());
    }

    public function test_claim_snapshots_and_verified_bank_receipt_fields_cannot_be_rewritten(): void
    {
        $purchase = $this->purchase();
        $claim = $this->claim($purchase);
        $this->bank($purchase);
        try {
            $claim->update(['receipt_number' => 'REPLACED']);
            $this->fail('A claim identity must not be rewritten.');
        } catch (\LogicException) {
            $this->assertSame('RECEIPT001', $claim->fresh()->receipt_number);
        }
        try {
            NcbaPayment::first()->update(['amount_minor' => 1]);
            $this->fail('An authenticated payment must not be rewritten.');
        } catch (\LogicException) {
            $this->assertSame(15000, NcbaPayment::first()->amount_minor);
        }
        $this->expectException(\LogicException::class);
        NcbaPaymentEvent::first()->delete();
    }

    public function test_scheduled_reconciliation_continues_after_one_failure_and_retries_without_partial_credit(): void
    {
        config(['ncba.auto_credit' => false]);
        $first = $this->purchase();
        $a = $this->claim($first);
        $this->bank($first);
        $second = $this->purchase();
        $b = $this->claim($second, 'SECONDRECEIPT');
        $this->bank($second, ['TransID' => 'SECONDRECEIPT', 'FTRef' => 'FTSECONDRECEIPT']);
        config(['ncba.auto_credit' => true]);
        $fail = true;
        Notification::creating(function ($notification) use (&$fail) {
            if ($fail && $notification->title === 'NCBA purchase approved' && ($notification->data['receipt_number'] ?? '') === 'RECEIPT001') {
                throw new \RuntimeException('Simulated per-receipt storage failure.');
            }
        });
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(1);
        $this->assertSame('REVIEW', $a->fresh()->status);
        $this->assertSame('APPROVED', $b->fresh()->status);
        $this->assertSame(5, $this->balance());
        $fail = false;
        $this->artisan('ncba:reconcile-receipts')->assertExitCode(0);
        $this->assertSame('APPROVED', $a->fresh()->status);
        $this->assertSame(10, $this->balance());
        $this->assertSame(2, TokenTransaction::count());
    }

    public function test_conflicting_authenticated_payload_is_retained_encrypted_without_secrets_or_overwriting_original(): void
    {
        $purchase = $this->purchase();
        $data = $this->bank($purchase);
        $data['Narrative'] = 'Unexpected changed bank narration';
        $this->postJson(self::CALLBACK, $data)->assertStatus(409);
        $event = NcbaPaymentEvent::where('event_type', 'CALLBACK_CONFLICT')->first();
        $this->assertSame($data['Narrative'], $event->evidence['Narrative']);
        $this->assertArrayNotHasKey('Hash', $event->evidence);
        $this->assertArrayNotHasKey('Username', $event->evidence);
        $this->assertArrayNotHasKey('Password', $event->evidence);
        $this->assertArrayNotHasKey('evidence', $event->toArray());
        $this->assertStringNotContainsString($data['Narrative'], DB::table('ncba_payment_events')->where('id', $event->id)->value('evidence'));
        $this->assertSame('', NcbaPayment::first()->verified_payload['Narrative']);
        $this->assertSame(0, $this->balance());
    }

    private function purchase(): TokenPurchase
    {
        return app(TokenService::class)->createPurchase($this->owner->id, 'PLATINUM', 5);
    }

    private function claim(TokenPurchase $purchase, string $receipt = 'RECEIPT001'): NcbaReceiptClaim
    {
        return app(NcbaReceiptClaimService::class)->submit($this->owner->fresh(), $purchase->id, $receipt);
    }

    private function claimUrl(TokenPurchase $purchase): string
    {
        return '/api/v1/tokens/purchases/'.$purchase->id.'/receipt';
    }

    private function login(?User $user = null): void
    {
        $this->withoutMiddleware(CheckActiveUser::class)->actingAs($user ?? $this->owner->fresh(), 'sanctum');
    }

    private function balance(): int
    {
        return (int) TokenWallet::where('user_id', $this->owner->id)->sum('balance');
    }

    private function bank(TokenPurchase $purchase, array $changes = []): array
    {
        $data = $this->payload($purchase, $changes);
        $this->postJson(self::CALLBACK, $data)->assertOk();

        return $data;
    }

    private function payload(TokenPurchase $purchase, array $changes = []): array
    {
        $data = array_replace([
            'TransType' => 'Pay Bill', 'TransID' => 'RECEIPT001', 'FTRef' => 'FTRECEIPT001',
            'TransTime' => '20261119100030', 'TransAmount' => $purchase->total_amount,
            'BusinessShortCode' => '880100', 'BillRefNumber' => '906854', 'Narrative' => '',
            'Mobile' => '254711111111', 'name' => 'Bank Payer', 'Username' => 'fixture-user', 'Password' => 'fixture-password',
        ], $changes);
        $data['Hash'] = app(NcbaNotificationVerifier::class)->signature($data);

        return $data;
    }
}

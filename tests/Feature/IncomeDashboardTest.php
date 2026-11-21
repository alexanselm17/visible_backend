<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\IncomeDashboard;
use App\Filament\Widgets\Income;
use App\Models\NcbaPayment;
use App\Models\NcbaReceiptClaim;
use App\Models\TokenPurchase;
use App\Models\TokenType;
use App\Models\User;
use App\Services\IncomeReportService;
use App\Support\IncomeReportFilters;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
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
class IncomeDashboardTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-21 12:00:00', 'Africa/Nairobi'));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::now());
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('admin_media');
        AdminPanelDatabase::build();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(AdminPanelDatabase::user('admin'));
        $this->owner = AdminPanelDatabase::user('campaign_owner');
    }

    private function filters(array $values = []): IncomeReportFilters
    {
        return IncomeReportFilters::make(array_replace(['preset' => 'custom', 'from_date' => '2026-11-01', 'to_date' => '2026-11-21'], $values));
    }

    private function purchase(string $amount = '100.00', string $status = TokenPurchase::PAID, string $date = '2026-11-10 12:00:00', array $extra = []): TokenPurchase
    {
        $purchase = TokenPurchase::create(array_replace([
            'user_id' => $this->owner->id, 'token_type_id' => TokenType::where('code', 'PLATINUM')->value('id'),
            'quantity' => 2, 'unit_price' => '50.00', 'total_amount' => $amount, 'currency' => 'KES',
            'status' => $status, 'paid_at' => $status === TokenPurchase::PAID ? $date : null,
            'payment_reference' => 'RECEIPT'.strtoupper(bin2hex(random_bytes(5))),
        ], $extra));
        $purchase->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        return $purchase;
    }

    private function bank(int $minor = 10000, string $status = NcbaPayment::REVIEW, array $extra = []): NcbaPayment
    {
        return NcbaPayment::create(array_replace([
            'transaction_id' => 'BANK'.strtoupper(bin2hex(random_bytes(5))), 'alias' => '906854', 'shortcode' => '880100',
            'amount_minor' => $minor, 'currency' => 'KES', 'status' => $status, 'transacted_at' => '2026-11-10 12:00:00',
            'payer_mobile' => 'DO-NOT-EXPOSE-PHONE', 'payer_name' => 'DO-NOT-EXPOSE-BANK-NAME',
            'verified_payload' => ['private' => 'DO-NOT-EXPOSE-RAW-PAYLOAD'], 'fingerprint' => str_repeat('a', 64),
            'last_received_at' => now(),
        ], $extra));
    }

    public function test_income_is_below_overview_and_all_finance_widgets_render(): void
    {
        $purchase = $this->purchase('125.37');
        $this->bank(12537, NcbaPayment::APPLIED, ['token_purchase_id' => $purchase->id]);
        $this->bank(3500);
        $this->get('/admin/income')->assertOk()->assertSee('Income &amp; payment analytics', false)
            ->assertSee('Monthly income comparison')->assertSee('Bank payments &amp; pending approvals', false)
            ->assertDontSee('DO-NOT-EXPOSE-PHONE')->assertDontSee('DO-NOT-EXPOSE-BANK-NAME')->assertDontSee('DO-NOT-EXPOSE-RAW-PAYLOAD');
        $this->assertLessThan(IncomeDashboard::getNavigationSort(), Dashboard::getNavigationSort());
        $this->assertNull(IncomeDashboard::getNavigationGroup());
        $this->assertSame('Income', IncomeDashboard::getNavigationLabel());
        foreach ((new IncomeDashboard)->getWidgets() as $widget) {
            $this->assertContains($widget, Filament::getPanel('admin')->getWidgets());
            $component = Livewire::test($widget, ['filters' => $this->filters()->values])->assertSuccessful();
            if ($widget === Income\IncomePurchasesTable::class) {
                $this->assertSame('incomePurchasesPage', $component->instance()->getTablePaginationPageName());
            } elseif ($widget === Income\IncomePaymentsTable::class) {
                $this->assertSame('incomePaymentsPage', $component->instance()->getTablePaginationPageName());
            }
        }
        session()->flush();
        $this->actingAs(AdminPanelDatabase::user('dev'));
        $this->get('/admin/income')->assertOk();
    }

    public function test_managers_members_and_inactive_staff_cannot_access_income_or_its_widgets(): void
    {
        foreach (['manager', 'member', 'campaign_owner'] as $role) {
            session()->flush();
            $this->actingAs(AdminPanelDatabase::user($role));
            $this->assertFalse(IncomeDashboard::canAccess());
            $this->get('/admin/income')->assertForbidden();
            Livewire::test(Income\IncomeSummary::class)->assertForbidden();
            Livewire::test(Income\IncomePaymentsTable::class)->assertForbidden();
            Livewire::test(IncomeDashboard::class)->assertForbidden();
        }
        $inactive = AdminPanelDatabase::user();
        $inactive->update(['is_active' => false]);
        session()->flush();
        $this->actingAs($inactive);
        $this->get('/admin/income')->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(IncomeReportService::class)->summary($this->filters());
    }

    public function test_approved_income_is_not_doubled_by_bank_receipts_and_unpaid_orders_are_separate(): void
    {
        $paid = $this->purchase('120.35');
        $this->purchase('29.65');
        $this->purchase('90.00', TokenPurchase::PENDING);
        $this->purchase('11.00', TokenPurchase::FAILED);
        $this->purchase('20.00', TokenPurchase::CANCELLED);
        $this->purchase('30.00', TokenPurchase::REFUNDED);
        $this->bank(12035, NcbaPayment::APPLIED, ['token_purchase_id' => $paid->id]);
        $this->bank(5000);
        $this->bank(7000, NcbaPayment::RESOLVED);
        $data = app(IncomeReportService::class)->summary($this->filters());
        $this->assertSame(['count' => 2, 'minor' => 15000, 'tokens' => 4], $data['approved']);
        $this->assertSame(7500, $data['average_minor']);
        $this->assertSame(1, $data['buyers']);
        $this->assertSame(1, $data['repeat_buyers']);
        $this->assertSame(['count' => 1, 'minor' => 9000], $data['orders'][TokenPurchase::PENDING]);
        $this->assertSame(['count' => 1, 'minor' => 5000], $data['bank'][NcbaPayment::REVIEW]);
        $this->assertSame(24035, $data['bank_total_minor']);
        $this->assertSame(3, $data['bank_count']);
        $this->assertNull($data['growth_percent']);
    }

    public function test_approved_dates_inclusive_boundaries_and_missing_paid_dates_are_handled(): void
    {
        $oldOrder = $this->purchase('12.34', date: '2026-09-01', extra: ['paid_at' => '2026-11-01 00:00:00']);
        $late = $this->purchase('0.66', date: '2026-11-21 23:59:59');
        DB::table('token_purchases')->where('id', $late->id)->update(['paid_at' => '2026-11-21 23:59:59.999999']);
        $this->purchase('500.00', date: '2026-11-22 00:00:00');
        $this->purchase('500.00', date: '2026-10-31 23:59:59');
        $missing = $this->purchase('500.00', extra: ['paid_at' => null]);
        $data = app(IncomeReportService::class)->summary($this->filters());
        $this->assertSame(1300, $data['approved']['minor']);
        $this->assertSame(1, $data['missing_paid_dates']);
        $this->assertContains($oldOrder->id, app(IncomeReportService::class)->purchaseTable($this->filters())->pluck('id')->all());
        $this->assertNotContains($missing->id, app(IncomeReportService::class)->approved($this->filters())->pluck('id')->all());
        $this->assertSame('KES 12.34', IncomeReportService::money(1234, 'KES'));
        $this->assertSame('KES 0.00', IncomeReportService::money(0, 'KES'));
    }

    public function test_currency_token_and_historical_inactive_type_filters_do_not_mix_balances(): void
    {
        $platinum = $this->purchase('50.00');
        $gold = $this->purchase('75.00', extra: ['token_type_id' => TokenType::where('code', 'GOLD')->value('id')]);
        $this->purchase('999.00', extra: ['currency' => 'USD']);
        $this->bank(5000, NcbaPayment::APPLIED, ['token_purchase_id' => $platinum->id]);
        $this->bank(7500, NcbaPayment::APPLIED, ['token_purchase_id' => $gold->id]);
        $this->bank(20000);
        $this->bank(99900, extra: ['currency' => 'USD']);
        TokenType::where('code', 'GOLD')->update(['is_active' => false, 'unit_price' => 1000]);
        $service = app(IncomeReportService::class);
        $goldData = $service->summary($this->filters(['token_type' => 'GOLD']));
        $this->assertSame(7500, $goldData['approved']['minor']);
        $this->assertSame(7500, $goldData['bank_total_minor']);
        $this->assertArrayNotHasKey(NcbaPayment::REVIEW, $goldData['bank']);
        $this->assertSame(12500, $service->summary($this->filters())['approved']['minor']);
        $this->assertSame(99900, $service->summary($this->filters(['currency' => 'USD']))['approved']['minor']);
        $this->assertSame(['KES' => 'KES', 'USD' => 'USD'], $service->currencies());
        $this->assertSame(['labels' => ['GOLD'], 'minor' => [7500]], $service->byToken($this->filters(['token_type' => 'GOLD'])));
        $this->owner->delete();
        $this->assertSame(12500, $service->summary($this->filters())['approved']['minor']);
    }

    public function test_open_receipt_claims_without_bank_confirmation_are_counted_but_not_income(): void
    {
        $purchase = $this->purchase('500.00', TokenPurchase::PENDING);
        foreach ([NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW, NcbaReceiptClaim::REJECTED] as $index => $status) {
            NcbaReceiptClaim::create(['token_purchase_id' => $purchase->id, 'user_id' => $purchase->user_id,
                'receipt_number' => 'CLAIM'.($index + 1), 'sequence' => $index + 1, 'status' => $status,
                'purchase_snapshot' => ['total_amount' => '500.00'], 'submitted_at' => '2026-11-10', 'last_submitted_at' => '2026-11-10']);
        }
        $data = app(IncomeReportService::class)->summary($this->filters());
        $this->assertSame(2, $data['waiting_claims']);
        $this->assertSame(0, $data['approved']['minor']);
        $this->assertSame(0, $data['bank_total_minor']);
    }

    public function test_previous_period_growth_uses_equal_length_period_and_zero_safe_math(): void
    {
        $this->purchase('20.00', date: '2026-11-21 12:00:00');
        $this->purchase('10.00', date: '2026-11-20 12:00:00');
        $this->purchase('999.00', date: '2026-11-19 12:00:00');
        $service = app(IncomeReportService::class);
        $filters = $this->filters(['from_date' => '2026-11-21']);
        $this->assertSame(100.0, $service->summary($filters)['growth_percent']);
        $this->assertSame(1000, $service->summary($filters)['previous']['minor']);
        $empty = $this->filters(['from_date' => '2026-11-01', 'to_date' => '2026-11-01']);
        $this->assertNull($service->summary($empty)['growth_percent']);
        $this->purchase('50.00', date: '2026-10-31');
        $this->assertSame(-100.0, $service->summary($empty)['growth_percent']);
    }

    public function test_trend_zero_fills_buckets_and_adapts_to_long_ranges(): void
    {
        $this->purchase('10.25', date: '2026-11-02');
        $service = app(IncomeReportService::class);
        $trend = $service->trend($this->filters(['to_date' => '2026-11-03']));
        $this->assertSame('day', $trend['bucket']);
        $this->assertSame(['01 Nov', '02 Nov', '03 Nov'], $trend['labels']);
        $this->assertSame([0, 1025, 0], $trend['minor']);
        $this->assertSame([0, 1, 0], $trend['counts']);
        $monthly = $service->trend($this->filters(['from_date' => '2026-01-01']));
        $this->assertSame('month', $monthly['bucket']);
        $this->assertCount(11, $monthly['labels']);
        $this->assertSame(1025, $monthly['minor'][10]);
        $yearly = $service->trend($this->filters(['from_date' => '2020-01-01']));
        $this->assertSame('year', $yearly['bucket']);
        $this->assertCount(7, $yearly['labels']);
        $this->assertSame(1025, $yearly['minor'][6]);
    }

    public function test_month_year_comparisons_use_fair_cutoffs_and_null_for_outside_window(): void
    {
        $this->purchase('10.00', date: '2026-11-21 23:59:59');
        $this->purchase('30.00', date: '2025-11-21');
        $this->purchase('80.00', date: '2025-11-22');
        $this->purchase('500.00', date: '2025-12-20');
        $service = app(IncomeReportService::class);
        $monthly = $service->monthly($this->filters());
        $this->assertCount(12, $monthly['labels']);
        $this->assertSame(1000, $monthly['years'][2026][10]);
        $this->assertSame(3000, $monthly['years'][2025][10]);
        $this->assertNull($monthly['years'][2025][11]);
        $this->assertNull($monthly['years'][2026][11]);
        $this->assertSame(0, $monthly['years'][2025][0]);
        $yearly = $service->yearly($this->filters());
        $this->assertSame(['2022', '2023', '2024', '2025', '2026'], $yearly['labels']);
        $this->assertSame([0, 0, 0, 3000, 1000], $yearly['minor']);
        $calendar = $service->monthly($this->filters(['comparison_scope' => 'calendar']));
        $this->assertSame(11000, $calendar['years'][2025][10]);
        $this->assertSame(50000, $calendar['years'][2025][11]);
        $this->assertNull($calendar['years'][2026][11]);
        $pastDecember = $service->monthly($this->filters(['from_date' => '2025-12-01', 'to_date' => '2025-12-31']));
        $this->assertSame(3000, $pastDecember['years'][2025][10]);
        $this->assertNull($pastDecember['years'][2025][11]);
        $this->assertSame(1000, $pastDecember['years'][2026][10]);
        $this->assertNull($pastDecember['years'][2026][11]);
    }

    public function test_leap_day_comparison_clamps_to_february_and_eat_dates_are_explicit(): void
    {
        $filters = $this->filters(['from_date' => '2024-02-01', 'to_date' => '2024-02-29', 'comparison_year' => 2024, 'compare_year' => 2023]);
        $this->assertSame('2024-03-01 00:00:00', $filters->comparisonBounds(2024)[1]->format('Y-m-d H:i:s'));
        $this->assertSame('2023-03-01 00:00:00', $filters->comparisonBounds(2023)[1]->format('Y-m-d H:i:s'));
        config(['app.timezone' => 'UTC']);
        $this->assertSame('2026-10-31 21:00:00', $this->filters()->bounds()[0]->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-21 21:00:00', $this->filters()->bounds()[1]->format('Y-m-d H:i:s'));
    }

    public function test_invalid_or_future_filters_are_rejected_before_they_reach_queries(): void
    {
        foreach ([['from_date' => '2026-11-22'], ['to_date' => '2026-11-22'], ['from_date' => '2010-01-01'],
            ['from_date' => '2026-02-30'], ['currency' => "KES' OR 1=1"], ['currency' => ['KES']],
            ['token_type' => 'FAKE'], ['compare_year' => 2026], ['comparison_year' => 2027], ['comparison_scope' => 'bad']] as $invalid) {
            try {
                $this->filters($invalid);
                $this->fail('Invalid filters were accepted: '.json_encode($invalid));
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(['from_date' => '2026-11-21', 'to_date' => '2026-11-21'], IncomeReportFilters::presetDates('today'));
        $this->assertSame(['from_date' => '2026-11-15', 'to_date' => '2026-11-21'], IncomeReportFilters::presetDates('last_7'));
        $this->assertSame(['from_date' => '2026-10-01', 'to_date' => '2026-10-31'], IncomeReportFilters::presetDates('last_month'));
    }

    public function test_draft_filters_are_applied_deliberately_and_invalid_drafts_leave_current_report_unchanged(): void
    {
        $component = Livewire::test(IncomeDashboard::class)->assertSuccessful();
        $defaults = IncomeReportFilters::defaults();
        $initialKeys = array_keys($component->instance()->getWidgets());
        $component->fillForm(array_replace($defaults, ['from_date' => '2026-11-10']))->assertSet('filters.from_date', '2026-11-01')
            ->call('applyFilters')->assertHasNoFormErrors()->assertSet('filters.from_date', '2026-11-10');
        $this->assertNotSame($initialKeys, array_keys($component->instance()->getWidgets()));
        $component->fillForm(array_replace($defaults, ['from_date' => '2010-01-01']))->call('applyFilters')
            ->assertHasFormErrors(['from_date'])->assertSet('filters.from_date', '2026-11-10');
        $component->call('resetFilters')->assertSet('filters', $defaults);
        $this->assertSame($defaults, session()->get('income_dashboard_filters.'.auth()->id()));
    }

    public function test_purchase_table_status_search_and_bank_table_amounts_use_the_applied_filters(): void
    {
        $paid = $this->purchase('123.45');
        $pending = $this->purchase('75.00', TokenPurchase::PENDING);
        $outside = $this->purchase('500.00', date: '2026-10-10');
        $usd = $this->purchase('800.00', extra: ['currency' => 'USD']);
        Livewire::test(Income\IncomePurchasesTable::class, ['filters' => $this->filters()->values])
            ->assertCanSeeTableRecords([$paid])->assertCanNotSeeTableRecords([$pending, $outside, $usd])
            ->filterTable('status', TokenPurchase::PENDING)->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$paid])
            ->searchTable($pending->payment_code)->assertCanSeeTableRecords([$pending]);
        $review = $this->bank(12345);
        $applied = $this->bank(5000, NcbaPayment::APPLIED, ['token_purchase_id' => $paid->id]);
        $outBank = $this->bank(555, extra: ['transacted_at' => '2026-10-01']);
        Livewire::test(Income\IncomePaymentsTable::class, ['filters' => $this->filters()->values])
            ->assertCanSeeTableRecords([$review])->assertCanNotSeeTableRecords([$applied, $outBank])
            ->assertSee('123.45')->assertDontSee('12,345.00')
            ->filterTable('status', NcbaPayment::APPLIED)->assertCanSeeTableRecords([$applied])->assertCanNotSeeTableRecords([$review]);
    }

    public function test_chart_datasets_use_the_service_totals_and_counts_not_unpaid_orders(): void
    {
        $this->purchase('123.45');
        $this->purchase('999.00', TokenPurchase::PENDING);
        $this->bank(2500);
        $expected = [Income\IncomeTrendChart::class => 123.45, Income\IncomeTokenChart::class => 123.45,
            Income\IncomeYearlyChart::class => 123.45, Income\IncomeBankStatusChart::class => 25.0];
        foreach ($expected as $widget => $total) {
            $component = Livewire::test($widget, ['filters' => $this->filters()->values]);
            $method = new \ReflectionMethod($widget, 'getData');
            $data = $method->invoke($component->instance());
            $this->assertEquals($total, array_sum($data['datasets'][0]['data']));
        }
        $component = Livewire::test(Income\IncomeOrderStatusChart::class, ['filters' => $this->filters()->values]);
        $method = new \ReflectionMethod(Income\IncomeOrderStatusChart::class, 'getData');
        $this->assertSame([1, 1, 0, 0, 0], $method->invoke($component->instance())['datasets'][0]['data']);
    }

    public function test_csv_export_is_approved_only_filtered_formula_safe_and_read_only(): void
    {
        $this->owner->update(['fullname' => '=UNSAFE()']);
        $paid = $this->purchase('123.45');
        $this->purchase('789.00', TokenPurchase::PENDING);
        $this->purchase('999.00', extra: ['currency' => 'USD']);
        $this->purchase('444.00', date: '2026-10-01');
        $this->bank(12345, NcbaPayment::APPLIED, ['token_purchase_id' => $paid->id]);
        $before = [TokenPurchase::count(), NcbaPayment::count(), DB::table('token_transactions')->count()];
        $page = new IncomeDashboard;
        $page->filters = $this->filters()->values;
        $response = $page->exportIncome();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('approved-income-KES-2026-11-01-to-2026-11-21.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("'=UNSAFE()", $csv);
        $this->assertStringContainsString('123.45', $csv);
        $this->assertStringNotContainsString('789.00', $csv);
        $this->assertStringNotContainsString('999.00', $csv);
        $this->assertStringNotContainsString('444.00', $csv);
        $this->assertCount(2, array_filter(explode("\n", trim($csv))));
        $this->assertSame($before, [TokenPurchase::count(), NcbaPayment::count(), DB::table('token_transactions')->count()]);
        Livewire::test(IncomeDashboard::class)->call('exportIncome')->assertFileDownloaded();
        $this->actingAs(AdminPanelDatabase::user('manager'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $page->exportIncome();
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Resources\NcbaPaymentResource;
use App\Filament\Widgets\Income;
use App\Models\TokenType;
use App\Services\IncomeReportService;
use App\Support\AdminAccess;
use App\Support\IncomeReportFilters;
use Filament\Actions\Action;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Dashboard;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeDashboard extends Dashboard
{
    protected static string $routePath = '/income';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'Income';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Income & payment analytics';

    protected static string $view = 'filament.pages.income-dashboard';

    public ?array $data = [];

    #[Locked]
    public array $filters = [];

    public static function canAccess(): bool
    {
        return AdminAccess::allows('finance');
    }

    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        try {
            $saved = session()->get($this->sessionKey());
            $this->filters = IncomeReportFilters::make(is_array($saved) ? $saved : null)->values;
        } catch (ValidationException) {
            $this->filters = IncomeReportFilters::defaults();
        }
        $this->form->fill($this->filters);
    }

    private function sessionKey(): string
    {
        return 'income_dashboard_filters.'.auth()->id();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Report period')->description('Choose a single date or date range. Click Apply filters to update every period-based total, graph and table.')
                ->schema([
                    F\Select::make('preset')->label('Quick period')->options(IncomeReportFilters::presets())->required()->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state !== 'custom') {
                                foreach (IncomeReportFilters::presetDates((string) $state) as $key => $value) {
                                    $set($key, $value);
                                }
                            }
                        }),
                    F\DatePicker::make('from_date')->label('From date')->required()->native(false)->displayFormat('d M Y')->minDate('2000-01-01')->maxDate(now())
                        ->live()->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
                    F\DatePicker::make('to_date')->label('Through date')->required()->native(false)->displayFormat('d M Y')->afterOrEqual('from_date')->maxDate(now())
                        ->live()->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
                    F\Select::make('currency')->options(fn () => app(IncomeReportService::class)->currencies())->required()->searchable(),
                    F\Select::make('token_type')->label('Token type')->options(fn () => ['all' => 'All tokens'] + TokenType::orderBy('sort_order')->pluck('name', 'code')->all())->required(),
                    F\Placeholder::make('timezone')->label('Reporting rules')->content('Dates use East Africa Time. Currencies are never combined. Unallocated receipts appear only under All tokens.'),
                ])->columns(3),
            F\Section::make('Month & year comparisons')->description('These comparison graphs use the years below, rather than the report date range. Currency and token filters still apply.')
                ->schema([
                    F\Select::make('comparison_year')->label('Main year')->options(array_combine(range(now()->year, 2000), range(now()->year, 2000)))->required(),
                    F\Select::make('compare_year')->label('Compare against')->options(array_combine(range(now()->year, 2000), range(now()->year, 2000)))->required()->different('comparison_year'),
                    F\Select::make('comparison_scope')->label('Comparison window')->options([
                        'ytd' => 'Same elapsed months/day (YTD)', 'calendar' => 'Full calendar years',
                    ])->helperText('YTD uses the Through date month/day. When comparing the current year, both years stop at the earlier of that cutoff or today.')->required(),
                ])->columns(3),
        ])->statePath('data');
    }

    public function applyFilters(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        try {
            $this->filters = IncomeReportFilters::make($state)->values;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }
        session()->put($this->sessionKey(), $this->filters);
    }

    public function resetFilters(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->filters = IncomeReportFilters::defaults();
        $this->form->fill($this->filters);
        session()->put($this->sessionKey(), $this->filters);
    }

    public function getWidgets(): array
    {
        $widgets = [Income\IncomeSummary::class, Income\IncomeTrendChart::class, Income\IncomeMonthlyChart::class,
            Income\IncomeYearlyChart::class, Income\IncomeTokenChart::class, Income\IncomeOrderStatusChart::class,
            Income\IncomeBankStatusChart::class, Income\IncomePurchasesTable::class, Income\IncomePaymentsTable::class];

        // Remount charts when applied filters change: Chart.js options live inside wire:ignore.
        // This also resets table pagination while leaving unapplied draft filters alone.
        $key = md5(json_encode($this->filters));

        return collect($widgets)->mapWithKeys(fn ($widget, $index) => [$key.'-'.$index => $widget])->all();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Export approved income')->icon('heroicon-o-arrow-down-tray')->action('exportIncome'),
            Action::make('review')->label('Payment approvals')->color('gray')->icon('heroicon-o-inbox-stack')->url(NcbaPaymentResource::getUrl())];
    }

    public function exportIncome(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $filters = IncomeReportFilters::make($this->filters);
        $query = app(IncomeReportService::class)->approved($filters)->with(['user', 'tokenType'])->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['purchase_id', 'owner_id', 'owner_name', 'token', 'quantity', 'currency', 'unit_price', 'approved_income', 'receipt', 'created_at_EAT', 'approved_at_EAT']);
            foreach ($query->lazyById(500) as $purchase) {
                $cells = [$purchase->id, $purchase->user_id, $purchase->user?->fullname ?? 'Deleted owner',
                    $purchase->tokenType?->code, $purchase->quantity, $purchase->currency, $purchase->unit_price,
                    $purchase->total_amount, $purchase->payment_reference, $purchase->created_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s'),
                    $purchase->paid_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s')];
                fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $cells));
            }
            fclose($stream);
        }, 'approved-income-'.$filters->currency().'-'.$filters->values['from_date'].'-to-'.$filters->values['to_date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

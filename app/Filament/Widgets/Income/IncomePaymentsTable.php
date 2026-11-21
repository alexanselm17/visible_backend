<?php

namespace App\Filament\Widgets\Income;

use App\Filament\Resources\NcbaPaymentResource;
use App\Models\NcbaPayment;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class IncomePaymentsTable extends TableWidget
{
    use UsesIncomeFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function updatedFilters(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table->queryStringIdentifier('incomePayments')->heading('Bank payments & pending approvals')->description('Real bank-confirmed receipts, filtered by bank transaction date. Starts with pending approvals. Open a receipt to investigate, approve or reconcile it securely.')
            ->query(fn () => $this->reports()->payments($this->incomeFilters())
                ->select(['id', 'transaction_id', 'amount_minor', 'currency', 'transacted_at', 'status', 'review_reason', 'conflict_count', 'token_purchase_id'])
                ->with('purchase.user'))
            ->columns([
                TextColumn::make('transaction_id')->label('M-Pesa receipt')->copyable()->searchable(),
                TextColumn::make('amount_minor')->label('Bank amount')->money(fn ($record) => $record->currency, divideBy: 100)->sortable(),
                TextColumn::make('transacted_at')->label('Bank transaction (EAT)')->dateTime('d M Y H:i', 'Africa/Nairobi')->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    NcbaPayment::APPLIED => 'success', NcbaPayment::REVIEW => 'warning', default => 'gray'
                }),
                TextColumn::make('review_reason')->label('Needs attention')->wrap()->limit(100)->placeholder('—'),
                TextColumn::make('conflict_count')->label('Conflicts')->numeric()->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('purchase.user.fullname')->label('Allocated owner')->placeholder('Not allocated'),
            ])->filters([
                SelectFilter::make('status')->options([NcbaPayment::REVIEW => 'Pending approval', NcbaPayment::APPLIED => 'Allocated', NcbaPayment::RESOLVED => 'Resolved outside token crediting'])->default(NcbaPayment::REVIEW),
                Filter::make('conflicts')->label('Conflicting notifications')->query(fn ($query) => $query->where('conflict_count', '>', 0)),
            ])->actions([Action::make('open')->label('Review receipt')->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn ($record) => NcbaPaymentResource::getUrl('index', ['tableSearch' => $record->transaction_id]))])
            ->defaultSort('transacted_at', 'desc')->paginated([5, 10, 25, 50])->defaultPaginationPageOption(5)->poll('60s');
    }
}

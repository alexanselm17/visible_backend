<?php

namespace App\Filament\Widgets\Income;

use App\Filament\Resources\TokenPurchaseResource;
use App\Models\TokenPurchase;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class IncomePurchasesTable extends TableWidget
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
        return $table->queryStringIdentifier('incomePurchases')->heading('Purchase history')->description('Approved purchases use approval date; other statuses use order date. Search by owner, purchase code or receipt. Approvals open the existing payment workspace.')
            ->query(fn () => $this->reports()->purchaseTable($this->incomeFilters())->with(['user', 'tokenType']))
            ->columns([
                TextColumn::make('payment_code')->label('Purchase')->searchable()->copyable(),
                TextColumn::make('user.fullname')->label('Owner')->searchable()->placeholder('Deleted owner'),
                TextColumn::make('tokenType.code')->label('Token')->badge(),
                TextColumn::make('quantity')->label('Tokens')->numeric()->sortable(),
                TextColumn::make('total_amount')->label('Amount')->money(fn ($record) => $record->currency)->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    TokenPurchase::PAID => 'success', TokenPurchase::PENDING => 'warning', TokenPurchase::FAILED => 'danger', default => 'gray',
                }),
                TextColumn::make('payment_reference')->label('Receipt')->searchable()->placeholder('Not supplied'),
                TextColumn::make('created_at')->label('Ordered (EAT)')->dateTime('d M Y H:i', 'Africa/Nairobi')->sortable()->toggleable(),
                TextColumn::make('paid_at')->label('Approved (EAT)')->dateTime('d M Y H:i', 'Africa/Nairobi')->sortable()->placeholder('Not approved'),
            ])->filters([
                SelectFilter::make('status')->options([TokenPurchase::PAID => 'Approved', TokenPurchase::PENDING => 'Pending payment',
                    TokenPurchase::FAILED => 'Failed', TokenPurchase::CANCELLED => 'Cancelled', TokenPurchase::REFUNDED => 'Marked refunded'])->default(TokenPurchase::PAID),
            ])->actions([Action::make('open')->label('Manage purchase')->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn ($record) => TokenPurchaseResource::getUrl('index', ['tableSearch' => $record->payment_code]))])
            ->defaultSort('created_at', 'desc')->paginated([5, 10, 25, 50])->defaultPaginationPageOption(5)->poll('60s');
    }
}

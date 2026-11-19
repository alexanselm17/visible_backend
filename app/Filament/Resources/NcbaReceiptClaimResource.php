<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\NcbaReceiptClaim;
use App\Services\NcbaReceiptClaimService;
use Filament\Forms\Components as F;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class NcbaReceiptClaimResource extends OperationsResource
{
    protected static ?string $model = NcbaReceiptClaim::class;

    protected static ?string $navigationLabel = 'Payment receipt claims';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Tokens';

    protected static string $area = 'finance';

    protected static array $exportColumns = ['id', 'token_purchase_id', 'user.fullname', 'receipt_number', 'sequence', 'status', 'reason_code', 'submission_count', 'submitted_at', 'decided_at'];

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny() || ! Schema::hasTable('ncba_receipt_claims')) {
            return null;
        }
        $count = NcbaReceiptClaim::where('status', NcbaReceiptClaim::REVIEW)->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['user', 'purchase.tokenType', 'payment']))
            ->poll('30s')->columns([
                C\TextColumn::make('receipt_number')->label('M-Pesa receipt')->searchable()->copyable(),
                C\TextColumn::make('user.fullname')->label('Claimant')->searchable(),
                C\TextColumn::make('purchase.payment_code')->label('Internal purchase reference')->searchable()->copyable(),
                C\TextColumn::make('purchase.tokenType.code')->label('Token')->badge(),
                C\TextColumn::make('purchase.quantity')->label('Tokens requested')->numeric(),
                C\TextColumn::make('purchase.total_amount')->label('Expected amount')->money(fn ($record) => $record->purchase->currency),
                C\TextColumn::make('sequence')->label('Attempt')->numeric(),
                C\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    NcbaReceiptClaim::APPROVED => 'success', NcbaReceiptClaim::REVIEW => 'warning',
                    NcbaReceiptClaim::REJECTED => 'danger', default => 'gray',
                }),
                C\TextColumn::make('reason_code')->label('Decision reason')->wrap(),
                C\IconColumn::make('bank_received')->label('Bank confirmed')->state(fn ($record) => (bool) $record->ncba_payment_id)->boolean(),
                C\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])->filters([
                SelectFilter::make('status')->options(array_combine(
                    ['WAITING_BANK', 'REVIEW', 'APPROVED', 'REJECTED', 'SUPERSEDED'],
                    ['Waiting for NCBA', 'Needs review', 'Approved', 'Rejected', 'Replaced by owner'])),
                SelectFilter::make('user_id')->relationship('user', 'fullname')->label('Owner')->searchable(),
            ])->actions([
                A\Action::make('history')->label('Details & history')->icon('heroicon-o-clock')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')->form([
                        F\Placeholder::make('snapshot')->label('Purchase snapshot at submission')
                            ->content(fn ($record) => view('filament.ncba-claim-snapshot', ['claim' => $record])),
                        F\Placeholder::make('history')->label('Immutable decision history')
                            ->content(fn ($record) => view('filament.ncba-payment-history', ['events' => \App\Models\NcbaPaymentEvent::where('receipt_claim_id', $record->id)
                                ->orWhere(fn ($query) => $query->where('receipt_number', $record->receipt_number)->whereNull('receipt_claim_id'))
                                ->orderBy('sequence')->get()])),
                    ]),
                A\Action::make('bank_receipt')->label('Open bank inbox')->icon('heroicon-o-banknotes')
                    ->visible(fn ($record) => (bool) $record->ncba_payment_id)
                    ->url(fn ($record) => NcbaPaymentResource::getUrl('index', ['tableSearch' => $record->receipt_number])),
                A\Action::make('reject')->label('Reject claim')->color('danger')->requiresConfirmation()
                    ->visible(fn ($record) => in_array($record->status, [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW], true))
                    ->modalDescription('Rejects this claim, not the bank payment. No refund or wallet debit is performed. The decision and reason remain in history.')
                    ->form([F\Textarea::make('notes')->label('Reason and ownership checks')->required()->minLength(10)->maxLength(4000)])
                    ->action(function ($record, array $data) {
                        app(NcbaReceiptClaimService::class)->reject($record->id, auth()->user(), $data['notes']);
                        Notification::make()->title('Claim rejection recorded')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => NcbaReceiptClaimResource\Pages\ManageNcbaReceiptClaims::route('/')];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\NcbaPayment;
use App\Models\TokenPurchase;
use App\Services\NcbaPaymentService;
use App\Support\PaymentAmount;
use Filament\Forms\Components as F;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters as T;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class NcbaPaymentResource extends OperationsResource
{
    protected static ?string $model = NcbaPayment::class;

    protected static ?string $navigationLabel = 'NCBA payments';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Tokens';

    protected static string $area = 'finance';

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny() || ! Schema::hasTable('ncba_payments')) {
            return null;
        }
        $count = NcbaPayment::where('status', NcbaPayment::REVIEW)->orWhere('conflict_count', '>', 0)->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['purchase.user', 'purchase.tokenType', 'reviewer']))
            ->poll('30s')->columns([
                C\TextColumn::make('transaction_id')->label('M-Pesa receipt')->searchable()->copyable(),
                C\TextColumn::make('bank_reference')->label('Bank reference')->searchable()->copyable()->placeholder('Not supplied'),
                C\TextColumn::make('amount')->state(fn ($record) => PaymentAmount::decimal($record->amount_minor))->money('KES'),
                C\TextColumn::make('receipt_claims_count')->label('Owner claims')->counts('receiptClaims')->numeric(),
                C\TextColumn::make('purchase.user.fullname')->label('Credited owner')->placeholder('Not allocated'),
                C\TextColumn::make('purchase.tokenType.code')->label('Token')->badge()->placeholder('Not allocated'),
                C\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    NcbaPayment::APPLIED => 'success', NcbaPayment::REVIEW => 'warning', default => 'gray',
                }),
                C\TextColumn::make('review_reason')->label('Needs attention')->wrap()->limit(100),
                C\TextColumn::make('conflict_count')->label('Conflicts')->numeric()->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                C\TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
            ])->filters([
                T\SelectFilter::make('status')->options([NcbaPayment::REVIEW => 'Needs review', NcbaPayment::APPLIED => 'Tokens credited', NcbaPayment::RESOLVED => 'Resolved outside token crediting']),
                T\Filter::make('conflicts')->label('Conflicting notifications')->query(fn ($query) => $query->where('conflict_count', '>', 0)),
            ])->headerActions([
                A\Action::make('setup')->label('Integration status')->icon('heroicon-o-information-circle')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')->form([
                        F\Placeholder::make('enabled')->label('Notifications')->content(fn () => config('ncba.enabled') ? 'Enabled' : 'Disabled'),
                        F\Placeholder::make('automatic')->label('Automatic crediting')->content(fn () => config('ncba.auto_credit') && config('ncba.contract_verified') ? 'Enabled after bank UAT' : 'Off: receipts stay in review'),
                        F\Placeholder::make('setup_help')->label('Setup')->content('Configure NCBA credentials securely on the server, register the HTTPS callback with NCBA, and complete bank UAT before enabling auto-credit. See docs/NCBA_PAYMENTS.md. Credentials are never displayed here.'),
                    ]),
            ])->actions([
                A\Action::make('view')->label('View receipt')->icon('heroicon-o-eye')->modalSubmitAction(false)->modalCancelActionLabel('Close')->form([
                    F\Placeholder::make('transaction_id')->content(fn ($record) => $record->transaction_id),
                    F\Placeholder::make('bank_reference')->content(fn ($record) => $record->bank_reference ?: 'Not supplied'),
                    F\Placeholder::make('transacted_at')->label('Bank transaction time (Nairobi)')->content(fn ($record) => $record->transacted_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s')),
                    F\Placeholder::make('alias')->content(fn ($record) => $record->alias),
                    F\Placeholder::make('narrative')->content(fn ($record) => $record->narrative ?: 'Not supplied'),
                    F\Placeholder::make('payer_mobile')->label('Payer phone / bank hash')->content(fn ($record) => $record->payer_mobile),
                    F\Placeholder::make('payer_name')->content(fn ($record) => $record->payer_name),
                    F\Placeholder::make('verified_fields')->label('Original verified bank fields (credentials excluded)')->content(fn ($record) => view('filament.ncba-verified-payload', ['payment' => $record])),
                    F\Placeholder::make('delivery_count')->content(fn ($record) => $record->delivery_count),
                    F\Placeholder::make('review_notes')->content(fn ($record) => $record->review_notes ?: 'No staff notes'),
                    F\Placeholder::make('reviewer')->content(fn ($record) => $record->reviewer?->fullname ?: 'Not manually reviewed'),
                    F\Placeholder::make('history')->label('Payment decision history')->content(fn ($record) => view('filament.ncba-payment-history', ['events' => \App\Models\NcbaPaymentEvent::where('receipt_number', $record->transaction_id)->orderBy('sequence')->get()])),
                ]),
                A\Action::make('claims')->label('Owner claims')->icon('heroicon-o-receipt-percent')
                    ->url(fn ($record) => NcbaReceiptClaimResource::getUrl('index', ['tableSearch' => $record->transaction_id])),
                A\Action::make('match')->label('Match & credit tokens')->color('success')->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === NcbaPayment::REVIEW && $record->conflict_count === 0)
                    ->modalDescription('Choose the verified owner’s purchase. Amount and currency must match exactly. This credits tokens once; it does not send money.')
                    ->form([
                        F\Select::make('token_purchase_id')->label('Pending purchase')->searchable()->required()
                            ->getSearchResultsUsing(fn (string $search, NcbaPayment $record) => TokenPurchase::where('status', TokenPurchase::PENDING)
                                ->where('currency', $record->currency)->where('total_amount', PaymentAmount::decimal($record->amount_minor))
                                ->where(fn ($query) => $query->where('payment_code', 'like', '%'.$search.'%')->orWhereHas('user', fn ($q) => $q->where('fullname', 'like', '%'.$search.'%')))
                                ->with('user', 'tokenType')->limit(30)->get()->mapWithKeys(fn ($purchase) => [$purchase->id => self::purchaseLabel($purchase)]))
                            ->getOptionLabelUsing(fn ($value) => ($purchase = TokenPurchase::with('user', 'tokenType')->find($value)) ? self::purchaseLabel($purchase) : null),
                        F\Textarea::make('notes')->label('Ownership checks and reconciliation notes')->required()->minLength(10)->maxLength(4000),
                        F\Checkbox::make('verified_owner')->label('I checked the bank receipt and confirmed this payment belongs to the selected purchase owner.')->accepted()->required(),
                    ])->action(function (NcbaPayment $record, array $data) {
                        app(NcbaPaymentService::class)->apply($record->id, $data['token_purchase_id'], auth()->user(), $data['notes']);
                        Notification::make()->title('Payment matched and tokens credited')->success()->send();
                    }),
                A\Action::make('resolve')->label('Resolve without tokens')->color('gray')->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === NcbaPayment::REVIEW)
                    ->modalDescription('For a refund or reconciliation already completed outside this app. This records the outcome only; it does NOT transfer or refund money.')
                    ->form([F\Textarea::make('notes')->label('Outcome and bank/refund reference')->required()->minLength(10)->maxLength(4000)])
                    ->action(function (NcbaPayment $record, array $data) {
                        app(NcbaPaymentService::class)->resolve($record->id, auth()->user(), $data['notes']);
                        Notification::make()->title('Reconciliation outcome recorded; no tokens credited')->success()->send();
                    }),
            ]);
    }

    private static function purchaseLabel(TokenPurchase $purchase): string
    {
        return $purchase->payment_code.' · '.($purchase->user?->fullname ?: 'Missing owner').' · '.$purchase->quantity.' '.($purchase->tokenType?->code ?: 'tokens').' · '.$purchase->currency.' '.$purchase->total_amount;
    }

    public static function getPages(): array
    {
        return ['index' => NcbaPaymentResource\Pages\ManageNcbaPayments::route('/')];
    }
}

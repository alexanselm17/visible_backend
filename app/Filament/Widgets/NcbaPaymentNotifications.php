<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\NcbaPaymentResource;
use App\Models\Notification;
use App\Support\AdminAccess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Schema;

class NcbaPaymentNotifications extends TableWidget
{
    protected static ?string $heading = 'Payment updates for you';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminAccess::allows('finance') && Schema::hasTable('ncba_receipt_claims');
    }

    public function table(Table $table): Table
    {
        abort_unless(AdminAccess::allows('finance'), 403);

        return $table->query(Notification::where('user_id', auth()->id())->where('data->category', 'ncba_payment')->where('is_read', false))
            ->poll('30s')->defaultSort('created_at', 'desc')->paginated([5, 10, 25])->columns([
                TextColumn::make('title')->weight('semibold'),
                TextColumn::make('message')->wrap(), TextColumn::make('created_at')->since(),
            ])->emptyStateHeading('No unread payment updates')->actions([
                Action::make('open')->label('Payments')->url(fn ($record) => NcbaPaymentResource::getUrl('index', ['tableSearch' => $record->data['receipt_number'] ?? ''])),
                Action::make('read')->label('Mark read')->action(function (Notification $record) {
                    abort_unless(AdminAccess::allows('finance') && $record->user_id === auth()->id(), 403);
                    $record->markAsRead();
                }),
            ]);
    }
}

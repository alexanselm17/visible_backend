<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankingResource\Pages;
use App\Models\Banking;
use App\Models\SysMeta;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Http\Requests\ResetBankingRequest;
use App\Http\Controllers\SalesController;
use App\Repositories\Sales\SalesRepositoryInterface;

class BankingResource extends Resource
{
  protected static ?string $model = Banking::class;

  protected static ?string $navigationIcon = 'heroicon-o-building-library';
  protected static ?string $navigationGroup = 'Money Management';
  protected static ?string $recordTitleAttribute = 'reference';
  protected static ?int $navigationSort = 1;

  public static function getNavigationBadge(): ?string
  {
    return static::getModel()::where('petrol_id', auth()->user()->petrol_id)
      ->where('approval_status', 0)
      ->count();
  }

  public static function getNavigationBadgeColor(): ?string
  {
    return 'warning';
  }



  public static function table(Table $table): Table
  {
    return $table
      ->recordUrl(null)

      ->columns([
        TextColumn::make('reference')
          ->label('Reference')
          ->searchable()
          ->copyable()
          ->sortable()
          ->visible(
            // fn($record) =>
            // $record->deposit_method->meta_value !== 'Cash'
          ),

        // TextColumn::make('name')
        //   ->label('Account Name')
        //   ->searchable()
        //   ->sortable(),

        TextColumn::make('amount')
          ->money('ksh')
          ->sortable(),

        TextColumn::make('phone')
          ->label('Phone')
          ->searchable(),

        TextColumn::make('depositMethod.meta_value')
          ->label('Deposit Method')
          ->sortable()
          ->searchable(),

        TextColumn::make('shift.description')
          ->label('Shift')
          ->sortable(),

        TextColumn::make('approval_status')
          ->badge()
          ->formatStateUsing(fn($state): string => match ($state) {
            1 => 'Approved',
            0 => 'Pending',
            default => 'Pending',
          })
          ->color(fn($state): string => match ($state) {
            1 => 'success',
            0 => 'warning',
            default => 'warning',
          }),

        TextColumn::make('processedBy.fullname')
          ->label('Processed By')
          ->toggleable(),

        TextColumn::make('approvedBy.fullname')
          ->label('Approved By')
          ->toggleable()
          ->visible(fn($record) => $record?->approval_status === "1"),


        TextColumn::make('petrolStation.name')
          ->label('Station')
          ->searchable()
          ->sortable(),

        TextColumn::make('created_at')
          ->label('Date')
          ->dateTime('M d, Y h:i A')
          ->sortable()
          ->toggleable(),
      ])
      ->defaultSort('created_at', 'desc')
      ->modifyQueryUsing(
        fn(Builder $query) =>
        $query->where('petrol_id', auth()->user()->petrol_id)
      )
      ->filters([
        Tables\Filters\SelectFilter::make('approval_status')
          ->options([
            0 => 'Pending',
            1 => 'Approved',
          ]),
        Tables\Filters\SelectFilter::make('shift_id')
          ->relationship('shift', 'description')
          ->label('Shift'),
        Tables\Filters\SelectFilter::make('deposit_method')
          ->relationship(
            'depositMethod',
            'meta_value',
            fn(Builder $query) =>
            $query->where('meta_key', 'deposit_method')
          )
          ->label('Deposit Method'),
      ])
      ->actions([

        Tables\Actions\Action::make('reset')
          ->label('Reset')
          ->icon('heroicon-o-arrow-path')
          ->color('warning')
          ->visible(fn(Banking $record): bool => $record->approval_status === 1) // Show only if approved
          ->requiresConfirmation()
          ->modalDescription('Are you sure you want to reset this banking record? This action cannot be undone.')
          ->action(function (Banking $record, array $data) {
            try {
              $resetBankingRequest = new ResetBankingRequest();
              $resetBankingRequest->merge([
                'shift_id' => $record->shift_id,
                'banking_id' => $record->id,
              ]);

              $setupRepository = app(SalesRepositoryInterface::class);
              $controller = new SalesController($setupRepository);
              $response = $controller->resetBankings($resetBankingRequest);
              $responseData = $response->getData();

              if ($responseData->ok === true) {
                Notification::make()
                  ->success()
                  ->title('Success')
                  ->body($responseData->message)
                  ->send();
              } else {
                Notification::make()
                  ->danger()
                  ->title('Error')
                  ->body($responseData->message)
                  ->send();
              }
            } catch (\Exception $e) {
              Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();
            }
          })

      ])
      ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
          Tables\Actions\DeleteBulkAction::make()
            ->successNotification(
              Notification::make()
                ->success()
                ->title('Bankings deleted')
                ->body('The selected banking records have been deleted successfully.')
            ),
        ]),
      ])
      ->emptyStateHeading('No banking records found')
      ->emptyStateDescription('Banking records will appear here automatically.')
      ->emptyStateIcon('heroicon-o-building-library');
  }

  public static function getRelations(): array
  {
    return [
      //
    ];
  }

  protected function getHeaderActions(): array
  {
    return [
      // Empty array - no create button
    ];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListBankings::route('/'),
      'edit' => Pages\EditBanking::route('/{record}/edit'),
    ];
  }
}

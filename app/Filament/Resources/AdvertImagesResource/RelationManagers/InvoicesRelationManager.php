<?php

namespace App\Filament\Resources\AdvertImagesResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('invoice_number')
                    ->label('Invoice Number')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('INV-001'),

                TextInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->step(0.01)
                    ->placeholder('0.00'),

                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                        'overdue' => 'Overdue',
                    ])
                    ->default('pending'),

                DatePicker::make('due_date')
                    ->label('Due Date')
                    ->required()
                    ->minDate(now()),

                DatePicker::make('paid_date')
                    ->label('Paid Date')
                    ->nullable(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->maxLength(500)
                    ->placeholder('Additional notes about this invoice...'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date < now() && $record->status !== 'paid' ? 'danger' : null),

                TextColumn::make('paid_date')
                    ->label('Paid Date')
                    ->date()
                    ->sortable()
                    ->placeholder('Not paid'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                        'overdue' => 'Overdue',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('overdue')
                    ->query(
                        fn (Builder $query): Builder => $query->where('due_date', '<', now())
                            ->where('status', '!=', 'paid')
                    )
                    ->label('Overdue'),

                Tables\Filters\Filter::make('high_amount')
                    ->query(fn (Builder $query): Builder => $query->where('amount', '>=', 1000))
                    ->label('High Amount (≥$1000)'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'paid')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_date' => now(),
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'status' => 'paid',
                                    'paid_date' => now(),
                                ]);
                            });
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No invoices yet')
            ->emptyStateDescription('Create invoices for this advertisement to track payments.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}

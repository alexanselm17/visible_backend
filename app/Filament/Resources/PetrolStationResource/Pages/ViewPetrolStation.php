<?php

namespace App\Filament\Resources\PetrolStationResource\Pages;

use App\Filament\Resources\PetrolStationResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPetrolStation extends ViewRecord
{
  protected static string $resource = PetrolStationResource::class;


  protected function getHeaderActions(): array
  {
    return [
      Action::make('station_timely_report')
        ->label('Generate Timely Station Report')
        ->icon('heroicon-o-document-chart-bar')
        ->color('gray')
        ->form([
          DatePicker::make('from')
            ->label('From Date')
            ->required(),

          DatePicker::make('to')
            ->label('To Date')
            ->after('from')
            ->beforeOrEqual(now())
            ->required(),
        ])
        ->action(function (array $data) {
          $petrolStationId = $this->record->id;
          $from = $data['from'];
          $to = $data['to'];

          try {
            return redirect()->route('reports.timely_report', [
              'petrol_id' => $petrolStationId,
              'from' => $from,
              'to' => $to,
            ]);
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error')
              ->body('Failed to generate station report: ' . $e->getMessage())
              ->danger()
              ->send();
          }
        }),

      Action::make('station_daily_report')
        ->label('Generate Daily Station Report')
        ->icon('heroicon-o-document-chart-bar')
        ->color('gray')
        ->form([
          DatePicker::make('date')
            ->label('Date to Generate Report')
            ->required(),


        ])
        ->action(function (array $data) {
          $petrolStationId = $this->record->id;
          $date = $data['date'];

          try {
            return redirect()->route('reports.daily_report', [
              'petrol_id' => $petrolStationId,
              'date' => $date,
            ]);
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error')
              ->body('Failed to generate station report: ' . $e->getMessage())
              ->danger()
              ->send();
          }
        }),
    ];
  }

  public function infolist(Infolist $infolist): Infolist
  {
    return $infolist
      ->schema([
        Grid::make(3)
          ->schema([
            Section::make('Station Details')
              ->schema([
                TextEntry::make('name')
                  ->label('Station Name')
                  ->weight('bold')
                  ->size('lg'),
                TextEntry::make('type')
                  ->badge(),
                TextEntry::make('company.name')
                  ->label('Company'),
              ])
              ->columnSpan(1),

            Section::make('Current Status')
              ->schema([
                TextEntry::make('products_count')
                  ->label('Total Products')
                  ->getStateUsing(fn($record) => $record->products()->count()),
                TextEntry::make('drums_count')
                  ->label('Total Drums')
                  ->getStateUsing(fn($record) => $record->drums()->count()),
                TextEntry::make('pumps_count')
                  ->label('Total Pumps')
                  ->getStateUsing(fn($record) => $record->pumps()->count()),
                TextEntry::make('active_shift')
                  ->label('Current Shift')
                  ->getStateUsing(function ($record) {
                    $activeShift = $record->shifts()->whereNull('ended_at')->first();

                    return $activeShift ? 'Active' : 'No Active Shift';
                  })
                  ->badge()
                  ->color(fn($state) => $state === 'Active' ? 'success' : 'danger'),
              ])
              ->columnSpan(2),
          ]),

        Section::make('Fuel Products')
          ->schema([
            RepeatableEntry::make('fuelProducts')
              ->schema([
                TextEntry::make('name')
                  ->label('Product Name'),
                TextEntry::make('buying_price')
                  ->label('Current Price')
                  ->money('KES'),
                TextEntry::make('drums_count')
                  ->label('Drums')
                  ->getStateUsing(fn($record) => $record->drums()->count()),
              ])
              ->columns(3),
          ])
          ->collapsible(),


        Section::make('Active Equipment')
          ->schema([
            Grid::make(2)
              ->schema([
                Section::make('Active Drums')
                  ->schema([
                    RepeatableEntry::make('drums')
                      ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('product.name')
                          ->label('Product Name'),
                        TextEntry::make('is_on_shift')
                          ->label('Status')
                          ->getStateUsing(fn($record) => $record->is_on_shift ? 'Active' : 'Inactive')
                          ->badge()
                          ->color(fn($state) => $state === 'Active' ? 'success' : 'warning'),
                      ])
                      ->columns(3),
                  ]),

                Section::make('Active Pumps')
                  ->schema([
                    RepeatableEntry::make('pumps')
                      ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('curr_volume')
                          ->label('Current Volume'),
                        TextEntry::make('curr_cash')
                          ->label('Current Cash')
                          ->money('KES'),
                        TextEntry::make('is_on_shift')
                          ->label('Status')
                          ->getStateUsing(fn($record) => $record->is_on_shift ? 'Active' : 'Inactive')
                          ->badge()
                          ->color(fn($state) => $state === 'Active' ? 'success' : 'warning'),
                      ])
                      ->columns(4),
                  ]),
              ]),
          ]),

        Section::make('Staff Members')
          ->schema([
            RepeatableEntry::make('staff')
              ->schema([
                TextEntry::make('fullname')
                  ->label('Full Name')
                  ->weight('bold'),

                TextEntry::make('username')
                  ->label('Username'),

                TextEntry::make('email')
                  ->label('Email')
                  ->copyable(),

                TextEntry::make('phone')
                  ->label('Phone')
                  ->copyable(),

                TextEntry::make('role.name')
                  ->label('Role')
                  ->badge(),

                TextEntry::make('is_active')
                  ->label('Status')
                  ->getStateUsing(fn($record) => $record->is_active ? 'Active' : 'Inactive')
                  ->badge()
                  ->color(fn($state) => $state === 'Active' ? 'success' : 'danger'),

                TextEntry::make('national_id')
                  ->label('National ID'),

                TextEntry::make('card_number')
                  ->label('Card Number')
                  ->visible(fn($record) => ! empty($record->card_number)),

                TextEntry::make('is_logged_in')
                  ->label('Login Status')
                  ->getStateUsing(fn($record) => $record->is_logged_in ? 'Online' : 'Offline')
                  ->badge()
                  ->color(fn($state) => $state === 'Online' ? 'success' : 'gray'),
              ])
              ->columns(4),
          ])
          ->collapsible(),

        Section::make('Customers')
          ->schema([
            RepeatableEntry::make('customers')
              ->schema([
                TextEntry::make('name'),
                TextEntry::make('phone'),
              ])
              ->columns(2),
          ])
          ->collapsible(),

      ]);
  }
}

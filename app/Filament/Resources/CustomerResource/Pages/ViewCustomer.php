<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action as ActionsAction;
use Filament\Tables\Actions\Action;

class ViewCustomer extends ViewRecord
{
  protected static string $resource = CustomerResource::class;

  public function infolist(Infolist $infolist): Infolist
  {
    return $infolist
      ->schema([
        Section::make('Customer Details')
          ->schema([
            Grid::make(3)
              ->schema([
                TextEntry::make('name')
                  ->label('Full Name')
                  ->weight(FontWeight::Bold),
                TextEntry::make('email')
                  ->icon('heroicon-o-envelope'),
                TextEntry::make('phone')
                  ->icon('heroicon-o-phone')
                  ->url(fn($record) => "tel:{$record->phone}"),

                TextEntry::make('petrolStation.name')
                  ->label('Assigned Station')
                  ->icon('heroicon-o-building-office-2'),
                TextEntry::make('created_at')
                  ->label('Customer Since')
                  ->icon('heroicon-o-calendar')
                  ->date('d M Y'),
              ]),
          ])->collapsible(),

        Section::make('Financial Overview')
          ->schema([
            Tabs::make('Financial Information')
              ->tabs([
                Tabs\Tab::make('Current Status')
                  ->schema([
                    Grid::make(3)
                      ->schema([
                        TextEntry::make('invoices_sum_amount')
                          ->label('Total Outstanding')
                          ->money('KES')
                          ->icon('heroicon-o-banknotes')
                          ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->latest()
                              ->first()
                              ?->customer_balance ?? 0;
                          }),

                        TextEntry::make('latest_invoice_number')
                          ->label('Latest Invoice')
                          ->icon('heroicon-o-document-text')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->latest()
                              ->first()
                              ?->invoice_number ?? 'No Invoice';
                          }),

                        TextEntry::make('latest_invoice_date')
                          ->label('Last Invoice Date')
                          ->icon('heroicon-o-clock')
                          ->date()
                          ->placeholder('No invoices yet')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->latest()
                              ->first()
                              ?->created_at;
                          }),
                      ]),
                  ]),

                Tabs\Tab::make('Payment History')
                  ->schema([
                    Grid::make(3)
                      ->schema([
                        TextEntry::make('invoices_count')
                          ->label('Total Invoices')
                          ->icon('heroicon-o-document-duplicate')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->where('type', 'invoice')
                              ->count();
                          }),

                        TextEntry::make('payments_sum')
                          ->label('Total Payments')
                          ->money('KES')
                          ->icon('heroicon-o-currency-dollar')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->where('type', 'repayment')
                              ->sum('amount');
                          }),

                        TextEntry::make('latest_payment.banking.name')
                          ->label('Last Payment Method')
                          ->icon('heroicon-o-credit-card')
                          ->placeholder('No payments yet'),
                      ]),
                  ]),

                Tabs\Tab::make('Transaction Summary')
                  ->schema([
                    Grid::make(2)
                      ->schema([
                        TextEntry::make('invoices_this_month_count')
                          ->label('Invoices This Month')
                          ->icon('heroicon-o-calendar')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->where('type', 'invoice')
                              ->whereMonth('created_at', now()->month)
                              ->count();
                          }),

                        TextEntry::make('invoices_this_month_sum')
                          ->label('Amount This Month')
                          ->money('KES')
                          ->icon('heroicon-o-banknotes')
                          ->state(function ($record) {
                            return $record->invoices()
                              ->where('type', 'invoice')
                              ->whereMonth('created_at', now()->month)
                              ->sum('amount');
                          }),
                      ]),
                  ]),
              ]),
          ])
          ->collapsible(),
      ]);
  }

  protected function getHeaderActions(): array
  {
    return [

      ActionsAction::make('customer_report')
        ->label('Customer Report')
        ->color('gray')
        ->icon('heroicon-o-document-chart-bar')
        ->form([


          DatePicker::make('from')
            ->label('From Date')
            ->required(),

          DatePicker::make('to')
            ->label('To Date')
            ->required()
            ->after('from')
            ->beforeOrEqual(now()),
        ])
        ->action(function (array $data) {
          try {
            return redirect()->route('reports.customer_report', [
              'customer_id' => $this->record->id,
              'petrol_id' => $this->record->petrol_id,
              'from' => $data['from'],
              'to' => $data['to']
            ]);
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error')
              ->body('Failed to generate customer report: ' . $e->getMessage())
              ->danger()
              ->send();
          }
        }),
      Actions\EditAction::make()
        ->icon('heroicon-o-pencil-square')
        ->label('Edit Customer Details')
        ->color('primary')
        ->button(),

      Actions\Action::make('reconcile')
        ->label('Reconcile Balance')
        ->url(fn() => CustomerResource::getUrl('reconcile', ['record' => $this->record->id]))
        ->icon('heroicon-o-currency-dollar')
        ->button()
        ->color('success'),

      Actions\Action::make('view_invoices')
        ->label('View Invoices')
        ->url(fn() => CustomerResource::getUrl('invoices', ['record' => $this->record->id]))
        ->icon('heroicon-o-document-text')
        ->button()
        ->color('info'),
    ];
  }
}

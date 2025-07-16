<?php

namespace App\Filament\Resources\DrumResource\Pages;

use App\Filament\Resources\DrumResource;
use App\Http\Controllers\SalesController;
use App\Http\Requests\StartDrumSessionRequest;
use App\Http\Requests\DrumReconcileRequest;
use App\Http\Requests\StockReconcileRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Auth;

class ViewDrum extends ViewRecord
{
  protected static string $resource = DrumResource::class;

  public $shift_id;

  public function mount($record): void
  {
    parent::mount($record);
    $this->shift_id = request()->query('shift_id');
  }

  public function getTitle(): string
  {
    return $this->record->name;
  }

  protected function getHeaderActions(): array
  {
    $previousUrl = url()->previous();
    $currentUrl = request()->url();

    $isFromShift = str_contains($previousUrl, 'shift') ||
      str_contains($previousUrl, 'shifts') ||
      request()->has('from_shift');

    $isFromDrums = str_contains($previousUrl, 'drum') ||
      str_contains($previousUrl, 'drums') ||
      request()->has('from_drums');

    $actions = []; // Initialize as an empty array

    if ($isFromShift) {
      $actions[] = Action::make('startSession')
        ->label('Start Tank Session')
        ->button()
        ->color('success')
        ->icon('heroicon-o-play')
        ->size(ActionSize::Large)
        ->visible(fn() => !$this->record->is_on_shift && $this->record->ended_at != null)
        ->action(function () {
          try {
            $request = new StartDrumSessionRequest;
            $request->merge([
              'drum_id' => $this->record->id,
              'processed_by' => Auth::id(),
            ]);


            $response = app(SalesController::class)->drumSession(
              $request,
              $this->record->petrol_id,
              $this->shift_id
            );
            dd($response);


            $this->handleResponse($response);
          } catch (\Exception $e) {
            $this->handleError($e);
          }
        });

      $actions[] = Action::make('endSession')
        ->label('End Tank Session')
        ->button()
        ->color('danger')
        ->icon('heroicon-o-stop')
        ->size(ActionSize::Large)
        ->visible(fn() => $this->record->is_on_shift)
        ->form([
          TextInput::make('ended_volume')
            ->label('End Volume')
            ->required()
            ->numeric()
            ->prefix('Litres')
            ->minValue(0)
            ->maxValue(fn() => $this->record->curr_volume)
            ->default(fn() => $this->record->curr_volume)
            ->helperText('End volume must be less than or equal to current volume'),
        ])
        ->action(function (array $data) {
          try {
            $request = new StartDrumSessionRequest;
            $request->merge([
              'drum_id' => $this->record->id,
              'processed_by' => Auth::id(),
              'ended_volume' => $data['ended_volume'],
            ]);

            $response = app(SalesController::class)->drumSession(
              $request,
              $this->record->petrol_id,
              $this->shift_id
            );

            $this->handleResponse($response);
          } catch (\Exception $e) {
            $this->handleError($e);
          }
        });
    }

    if ($isFromDrums) {
      $actions[] = Action::make('drum_report')
        ->label('Tank Stock Report')
        ->color('gray')
        ->icon('heroicon-o-beaker')
        ->action(function () {
          try {
            return redirect()->route('reports.stock_report', [
              'petrolStationId' => $this->record->petrol_id,
              'drum_id' => $this->record->id,
              'type' => 'tank'
            ]);
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error')
              ->body('Failed to generate tank stock report: ' . $e->getMessage())
              ->danger()
              ->send();
          }
        });

      $actions[] = Action::make('reconcile')
        ->label('Reconcile Tank')
        ->button()
        ->color('warning')
        ->icon('heroicon-o-arrow-path')
        ->size(ActionSize::Large)
        ->form([
          TextInput::make('current_volume')
            ->label('Current Volume')
            ->required()
            ->numeric()
            ->prefix('Litres')
            ->minValue(0),
        ])
        ->action(function (array $data) {
          try {
            $request = new StockReconcileRequest();
            $request->merge([
              'drum_id' => $this->record->id,
              'quantity' => $data['current_volume'],
              'petrol_id' => $this->record->petrol_id,
            ]);

            $response = app(SalesController::class)->reconcileStock(
              $request,
              $this->record->petrol_id
            );

            $responseData = $response->getData();

            if ($responseData->ok === true) {
              Notification::make()
                ->title($responseData->message)
                ->success()
                ->send();

              $this->redirect($this->getResource()::getUrl('index'));
            } else {
              Notification::make()
                ->title($responseData->message)
                ->danger()
                ->send();
            }
          } catch (\Exception $e) {
            Notification::make()
              ->title('Error occurred')
              ->body($e->getMessage())
              ->danger()
              ->send();
          }
        });
    }

    return $actions; // Ensure $actions is returned at the end
  }

  public function infolist(Infolist $infolist): Infolist
  {
    return $infolist
      ->schema([
        Section::make('Tank Information')
          ->description('Current status and details of the tank')
          ->collapsible()
          ->schema([
            TextEntry::make('name')
              ->label('Tank Name')
              ->size(TextEntry\TextEntrySize::Large)
              ->weight('bold'),
            TextEntry::make('capacity')
              ->label('Capacity')
              ->suffix(' Litres')
              ->numeric(
                decimalPlaces: 2,
                thousandsSeparator: ',',
              ),
            TextEntry::make('stock.stock')
              ->label('Current Volume')
              ->suffix(' Litres')
              ->numeric(
                decimalPlaces: 2,
                thousandsSeparator: ',',
              )
              ->color('success')
              ->placeholder('No stock data available'),
            TextEntry::make('petrolStation.name')
              ->label('Petrol Station')
              ->icon('heroicon-o-building-office-2'),
            TextEntry::make('product.name')
              ->label('Product Type')
              ->icon('heroicon-o-beaker'),
            TextEntry::make('is_on_shift')
              ->label('Shift Status')
              ->getStateUsing(fn($record) => $record->is_on_shift ? 'Active' : 'Inactive')
              ->badge()
              ->icon(fn($state) => $state === 'Active' ? 'heroicon-o-play' : 'heroicon-o-stop')
              ->color(fn($state) => $state === 'Active' ? 'success' : 'danger'),
          ])
          ->columns(3),

        Section::make('Session History')
          ->description('Detailed history of tank sessions and sales')
          ->collapsible()
          ->schema([
            RepeatableEntry::make('drumSessionDetails')
              ->schema([
                TextEntry::make('shift.description')
                  ->label('Shift Description')
                  ->weight('bold')
                  ->icon('heroicon-o-clock'),
                TextEntry::make('shift.created_at')
                  ->label('Started')
                  ->dateTime('d M Y, h:i A')
                  ->icon('heroicon-o-calendar'),
                TextEntry::make('start_volume')
                  ->label('Initial Volume')
                  ->suffix(' Litres')
                  ->numeric(
                    decimalPlaces: 2,
                    thousandsSeparator: ',',
                  ),
                TextEntry::make('ended_volume')
                  ->label('Final Volume')
                  ->suffix(' Litres')
                  ->numeric(
                    decimalPlaces: 2,
                    thousandsSeparator: ',',
                  )
                  ->placeholder('Session Active'),
                TextEntry::make('price')
                  ->label('Price per Litre')
                  ->prefix('KES ')
                  ->numeric(
                    decimalPlaces: 2,
                    thousandsSeparator: ',',
                  ),
                TextEntry::make('volume_sold')
                  ->label('Volume Sold')
                  ->suffix(' Litres')
                  ->getStateUsing(function ($record) {
                    return number_format($record->getVolumeSold(), 2);
                  })
                  ->color('success'),
                TextEntry::make('total_sales')
                  ->label('Total Revenue')
                  ->getStateUsing(function ($record) {
                    return 'KES ' . number_format($record->getTotalSales(), 2);
                  })
                  ->icon('heroicon-o-banknotes')
                  ->color('success')
                  ->weight('bold'),
                TextEntry::make('processedBy.fullname')
                  ->label('Processed By')
                  ->icon('heroicon-o-user'),
              ])
              ->columns(4),

          ]),
      ]);
  }
  private function handleResponse($response)
  {
    $responseData = json_decode($response->getContent(), true);

    if (isset($responseData['status']) && $responseData['status'] === 'success') {
      Notification::make()
        ->title('Success')
        ->body($responseData['message'] ?? 'Operation completed successfully')
        ->success()
        ->send();

      $this->redirect($this->getResource()::getUrl('view', [
        'record' => $this->record,
        'shift_id' => $this->shift_id
      ]));
      return;
    }
    Notification::make()
      ->title('Error')
      ->body($responseData['message'])
      ->danger()
      ->send();

    $this->redirect($this->getResource()::getUrl('view', [
      'record' => $this->record,
      'shift_id' => $this->shift_id
    ]));
    return;
  }

  private function handleError(\Exception $e)
  {
    Notification::make()
      ->title('Error')
      ->body($e->getMessage())
      ->danger()
      ->send();

    throw $e;
  }
}

<?php

namespace App\Filament\Resources\PumpsResource\Pages;

use App\Filament\Resources\PumpsResource;
use App\Http\Controllers\SalesController;
use App\Http\Requests\PumpReconcileRequest;
use App\Http\Requests\StartPumpSessionRequest;
use App\Models\PumpSessionDetail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextArea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ViewPump extends ViewRecord
{
  protected static string $resource = PumpsResource::class;

  public $shift_id;

  public function mount($record): void
  {
    parent::mount($record);
    $this->shift_id = request()->query('shift_id');
    Log::info('Mounted ViewPump with shift_id: ' . $this->shift_id);
  }

  protected function getHeaderActions(): array
  {
    $previousUrl = url()->previous();
    $currentUrl = request()->url();

    Log::info('Previous URL: ' . $previousUrl);
    Log::info('Current URL: ' . $currentUrl);

    $referrer = request()->header('referer');
    Log::info('Referrer: ' . $referrer);

    $isFromShift = str_contains($previousUrl, 'shift') ||
      str_contains($referrer, 'shift') ||
      request()->has('from_shift');

    $isFromPumps = str_contains($previousUrl, 'pumps') ||
      str_contains($referrer, 'pumps') ||
      request()->has('from_pumps');

    Log::info('Is from shift: ' . ($isFromShift ? 'true' : 'false'));
    Log::info('Is from pumps: ' . ($isFromPumps ? 'true' : 'false'));

    if ($isFromShift) {
      return [
        Action::make('assign')
          ->label('Start Pump Session')
          ->button()
          ->icon('heroicon-o-play')
          ->size(ActionSize::Large)
          ->color('success')
          ->size(ActionSize::Large)
          ->visible(function () {
            $latestSession = PumpSessionDetail::where('pump_id', $this->record->id)
              ->where('shift_id', $this->shift_id)
              ->latest()
              ->first();
            return !$latestSession || is_null($latestSession->assigned_to);
          })
          ->form([
            Select::make('user_id')
              ->label('Select User')
              ->options(function () {
                return User::where('petrol_id', $this->record->petrol_id)
                  ->pluck('fullname', 'id');
              })
              ->required()
              ->searchable(),
          ])
          ->action(function (array $data) {
            try {
              $request = new StartPumpSessionRequest();
              $request->merge([
                "pump_id" => $this->record->id,
                "processed_by" => Auth::id(),
                "assigned_to" => $data["user_id"],
              ]);

              $response = app(SalesController::class)->pumpSession(
                $request,
                $this->shift_id,
                $this->record->petrol_id,
              );

              $this->handleResponse($response);
            } catch (\Exception $e) {
              $this->handleError($e);
            }
          }),

        Action::make('end')
          ->label('End Session')
          ->button()
          ->color('danger')
          ->size(ActionSize::Large)
          ->visible(function () {
            $latestSession = PumpSessionDetail::where('pump_id', $this->record->id)
              ->where('shift_id', $this->shift_id)
              ->latest()
              ->first();

            // Show only if session exists, has assigned_to, and end details are null
            return $latestSession &&
              !is_null($latestSession->assigned_to) &&
              is_null($latestSession->ended_by) &&
              is_null($latestSession->ended_volume) &&
              is_null($latestSession->ended_cash);
          })
          ->form([
            TextInput::make('ended_volume')
              ->label('End Volume')
              ->required()
              ->numeric()
              ->prefix('Litres')
              // ->minValue(function () {
              //   $latestSession = $this->record->pumpSessionDetails()->latest()->first();
              //   return $latestSession ? $latestSession->start_volume : 0;
              // })
              // ->maxValue(function () {
              //   $latestSession = $this->record->pumpSessionDetails()->latest()->first();
              //   return $latestSession ? $latestSession->start_volume : 0;
              // })
              // ->rules(['lte:start_volume'])
              ->helperText('End volume must be less than or equal to start volume'),

            TextInput::make('ended_cash')
              ->label('End Cash')
              ->required()
              ->numeric()
              ->prefix('KES')
              // ->minValue(function () {
              //   $latestSession = $this->record->pumpSessionDetails()->latest()->first();
              //   return $latestSession ? $latestSession->start_cash : 0;
              // })
              // ->maxValue(function () {
              //   $latestSession = $this->record->pumpSessionDetails()->latest()->first();
              //   return $latestSession ? $latestSession->start_cash : 0;
              // })
              // ->rules(['lte:start_cash'])
              ->helperText('End cash must be less than or equal to start cash'),
          ])
          ->closeModalByClickingAway(false)
          ->modalSubmitActionLabel('End Session')
          ->action(function (array $data) {
            try {
              $request = new StartPumpSessionRequest();
              $request->merge([
                'ended_volume' => $data['ended_volume'],
                'ended_cash' => $data['ended_cash'],
              ]);

              $response = app(SalesController::class)->pumpSession(
                $request,
                $this->shift_id,
                $this->record->petrol_id
              );

              $responseData = json_decode($response->getContent(), true);

              if (isset($responseData['status']) && $responseData['status'] === 'success') {
                Notification::make()
                  ->title('Success')
                  ->body($responseData['message'] ?? 'Session ended successfully')
                  ->success()
                  ->send();

                $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                return;
              }

              Notification::make()
                ->title('Error')
                ->body($responseData['error'] ?? 'Failed to end session')
                ->danger()
                ->send();
            } catch (\Exception $e) {
              Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            }
          })
      ];
    }

    if ($isFromPumps) {
      return [
        Action::make('reconcile')
          ->label('Reconcile Pump')
          ->button()
          ->color('warning')
          ->size(ActionSize::Large)
          ->form([
            TextInput::make('current_volume')
              ->label('Current Volume')
              ->required()
              ->numeric()
              ->prefix('Litres'),
            TextInput::make('current_cash')
              ->label('Current Cash')
              ->required()
              ->numeric()
              ->prefix('KES'),
          ])
          ->action(function (array $data) {
            try {
              $request = new PumpReconcileRequest();
              $request->merge([
                'curr_volume' => $data['current_volume'],
                'curr_cash' => $data['current_cash'],
              ]);


              $response = app(SalesController::class)->reconcilePumps(
                $request,
                $this->record->id,
                $this->record->petrol_id
              );

              $responseData = json_decode($response->getContent(), true);

              if (isset($responseData['status']) && $responseData['status'] === 'success') {
                Notification::make()
                  ->title('Success')
                  ->body($responseData['message'] ?? 'Pump reconciled successfully')
                  ->success()
                  ->send();

                $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                return;
              }

              Notification::make()
                ->title('Error')
                ->body($responseData['message'])
                ->danger()
                ->send();
            } catch (\Exception $e) {
              Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            }
          })
      ];
    }

    return [];
  }

  public function infolist(Infolist $infolist): Infolist
  {
    return $infolist
      ->schema([
        Section::make('Pump Details')
          ->schema([
            TextEntry::make('name'),
            TextEntry::make('curr_volume')
              ->label('Current Volume'),
            TextEntry::make('curr_cash')
              ->label('Current Cash')
              ->prefix('KES '),
            TextEntry::make('is_on_shift')
              ->label('On Shift')
              ->getStateUsing(fn($record) => $record->is_on_shift ? 'True' : 'False')
              ->badge()
              ->color(fn($state) => $state === 'True' ? 'success' : 'danger'),
            TextEntry::make('petrolStation.name')
              ->label('Petrol Station'),
            TextEntry::make('drum.name')
              ->label('Drum'),
          ])
          ->columns(3),

        Section::make('Session History')
          ->schema([
            RepeatableEntry::make('pumpSessionDetails')
              ->schema([
                TextEntry::make('shift.description')
                  ->label('Shift')
                  ->weight('bold'),
                TextEntry::make('shift.started_at')
                  ->label('Shift Start')
                  ->dateTime(),
                TextEntry::make('assignedUser.fullname')
                  ->label('Assigned To')
                  ->default('Not Assigned'),
                TextEntry::make('endedByUser.fullname')
                  ->label('Ended By')
                  ->default('Not Ended'),
                TextEntry::make('start_volume')
                  ->label('Start Volume'),
                TextEntry::make('ended_volume')
                  ->label('End Volume'),
                TextEntry::make('start_cash')
                  ->label('Start Cash')
                  ->prefix('KES '),
                TextEntry::make('ended_cash')
                  ->label('End Cash')
                  ->prefix('KES '),
                TextEntry::make('price')
                  ->prefix('KES ')
                  ->numeric(),

                TextEntry::make('volume_sold')
                  ->label('Volume Sold')
                  ->getStateUsing(function ($record) {
                    if (is_null($record->ended_volume)) {
                      return 'N/A';
                    }
                    return number_format($record->ended_volume - $record->start_volume, 2);
                  }),
                TextEntry::make('total_sales')
                  ->label('Total Sales')
                  ->getStateUsing(function ($record) {
                    if (is_null($record->ended_volume)) {
                      return 'N/A';
                    }
                    $volumeSold = $record->ended_volume - $record->start_volume;
                    return 'KES ' . number_format($volumeSold * $record->price, 2);
                  })
                  ->badge(),
                TextEntry::make('status')
                  ->label('Status')
                  ->getStateUsing(function ($record) {
                    if (is_null($record->ended_volume)) {
                      return 'In Progress';
                    }
                    return 'Completed';
                  })
                  ->badge()
                  ->color(function ($state) {
                    return match ($state) {
                      'In Progress' => 'info',
                      'Completed' => 'success',
                      default => 'gray'
                    };
                  }),
              ])
              ->columns(3),
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

      $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
      return;
    }

    throw new \Exception($responseData['message'] ?? 'Operation failed');
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

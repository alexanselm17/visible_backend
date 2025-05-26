<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Resources\ShiftResource;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ShiftController;
use App\Http\Requests\StartShiftRequest;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateShift extends CreateRecord
{
  protected static string $resource = ShiftResource::class;

  protected function getCreatedNotificationTitle(): ?string
  {
    return null;
  }

  public function create(bool $another = false): void
  {
    try {
      $data = $this->form->getState();

      $request = new StartShiftRequest();
      $request->merge([
        'description' => $data['description'],
      ]);

      $response = app(SalesController::class)->startShift(
        request: $request,
        petrolStationId: $data['petrol_id'] ?? auth()->user()->petrol_id
      );

      $responseData = $response->getData();

      if ($responseData->ok === true) {
        Notification::make()
          ->title($responseData->message)
          ->success()
          ->send();

        if ($another) {
          $this->form->fill();
        } else {
          $this->redirect($this->getResource()::getUrl('index'));
        }
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
  }

  protected function getRedirectUrl(): string
  {
    return $this->getResource()::getUrl('index');
  }
}

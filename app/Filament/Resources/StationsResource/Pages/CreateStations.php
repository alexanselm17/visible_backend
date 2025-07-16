<?php

namespace App\Filament\Resources\StationResource\Pages;

use App\Filament\Resources\StationsResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Http\Controllers\ProductController;
use App\Http\Requests\CreatesStationRequest;
use App\Repositories\Products\ProductRepositoryInterface;

class CreateStations extends CreateRecord
{
  protected static string $resource = StationsResource::class;

  protected function getCreatedNotificationTitle(): ?string
  {
    return null;
  }

  public function create(bool $another = false): void
  {
    try {
      $data = $this->form->getState();

      $request = new CreatesStationRequest();
      $request->merge([
        'name' => $data['name'],
      ]);

      $response = app(ProductController::class)->createStation(
        request: $request,
        petrolStationId: $data['petrol_id']
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

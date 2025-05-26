<?php

namespace App\Filament\Resources\DrumResource\Pages;

use App\Filament\Resources\DrumResource;
use App\Http\Controllers\ProductController;
use App\Http\Requests\UpdateDrumRequest;
use App\Repositories\Products\ProductRepositoryInterface;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrum extends EditRecord
{
  protected static string $resource = DrumResource::class;

  protected function getHeaderActions(): array
  {
    return [
      Actions\DeleteAction::make(),
    ];
  }

  protected function getSavedNotificationTitle(): ?string
  {
    return null;
  }

  public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
  {
    try {
      $data = $this->form->getState();
      $drumRequest = new UpdateDrumRequest;
      $drumRequest->merge([
        'name' => $data['name'],
        'product_id' => $data['product_id'],
        'capacity' => $data['capacity'],
        'is_on_shift' => 0,
      ]);

      $productRepository = app(ProductRepositoryInterface::class);
      $controller = new ProductController($productRepository);

      // Call the updateDrum method
      $response = $controller->updateDrum($drumRequest, $this->record->id, $data['petrol_station_id']);

      // Get the response data
      $responseData = $response->getData();

      if ($responseData->ok === true) {
        Notification::make()
          ->title($responseData->message)
          ->success()
          ->send();
      } else {
        Notification::make()
          ->title($responseData->error)
          ->danger()
          ->send();
      }

      $this->redirect($this->getResource()::getUrl('index'));
    } catch (\Exception $e) {
      Notification::make()
        ->title('Error occurred')
        ->danger()
        ->send();

      $this->redirect($this->getResource()::getUrl('index'));
    }
  }

  protected function getRedirectUrl(): string
  {
    return $this->getResource()::getUrl('index');
  }
}

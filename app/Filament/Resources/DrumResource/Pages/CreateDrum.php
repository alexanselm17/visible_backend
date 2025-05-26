<?php

namespace App\Filament\Resources\DrumResource\Pages;

use App\Filament\Resources\DrumResource;
use App\Http\Controllers\ProductController;
use App\Http\Requests\CreateDrumRequest;
use App\Repositories\Products\ProductRepositoryInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDrum extends CreateRecord
{
  protected static string $resource = DrumResource::class;

  protected function getCreatedNotificationTitle(): ?string
  {
    return null;
  }

  public function create(bool $another = false): void
  {
    try {
      $data = $this->form->getState();
      $drumRequest = new CreateDrumRequest;
      $drumRequest->merge([
        'name' => $data['name'],
        'product_id' => $data['product_id'],
        'capacity' => $data['capacity'],
        'is_on_shift' => 0,
      ]);

      $productRepository = app(ProductRepositoryInterface::class);
      $controller = new ProductController($productRepository);

      // Call the createDrum method
      $response = $controller->createDrum($drumRequest, $data['petrol_station_id']);

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

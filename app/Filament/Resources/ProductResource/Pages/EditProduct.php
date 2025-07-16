<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Http\Controllers\ProductController;
use App\Http\Requests\UpdateProductRequest;
use App\Repositories\Products\ProductRepositoryInterface;

class EditProduct extends EditRecord
{
  protected static string $resource = ProductResource::class;
  public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void

  // Todo:: petrol station id not picking during edit
  {
    try {
      // Get the form data
      $data = $this->form->getState();
      $petrolStationId = $data['petrol_id'] ?? $this->record->petrol_id;

      // Create a new UpdateProductRequest instance and set its data

      $productRequest = new UpdateProductRequest();
      $productRequest->merge([
        'name' => strtoupper($data['name']),
        'category' => strtoupper($data['category']),
        'buying_price' => $data['buying_price'],
        'selling_price' => $data['selling_price'],
        'min_stock' => $data['min_stock'],
      ]);


      // Get the repository instance
      $productRepository = app(ProductRepositoryInterface::class);

      // Create controller instance with repository
      $controller = new ProductController($productRepository);



      // Call the updateProduct method
      $response = $controller->updateProduct($productRequest, $this->record->id);

      // Get the response data
      $responseData = $response->getData();

      if ($responseData->ok === true) {
        Notification::make()
          ->title($responseData->message)
          ->success()
          ->send();

        $this->redirect($this->getResource()::getUrl('index'));
      } else {
        Notification::make()
          ->title($responseData->message . ' ' . $responseData->error)
          ->danger()
          ->send();
      }
    } catch (\Exception $e) {
      Notification::make()
        ->title('Error occurred: ' . $e->getMessage())
        ->danger()
        ->send();

      // Redirect back to listing
      $this->redirect($this->getResource()::getUrl('index'));
    }
  }

  protected function getRedirectUrl(): string
  {
    return $this->getResource()::getUrl('index');
  }

  // Override these methods to prevent default behavior
  protected function handleRecordUpdate(Model $record, array $data): Model
  {
    return $record;
  }

  protected function mutateFormDataBeforeSave(array $data): array
  {
    return $data;
  }
}

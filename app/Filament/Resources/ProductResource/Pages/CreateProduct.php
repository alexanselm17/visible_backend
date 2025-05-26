<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductsModel;
use Filament\Notifications\Notification;
use App\Http\Controllers\ProductController;
use App\Http\Requests\ProductRequest;
use App\Repositories\Products\ProductRepositoryInterface;

class CreateProduct extends CreateRecord
{
  protected static string $resource = ProductResource::class;

  protected function getCreatedNotificationTitle(): ?string
  {
    return null;
  }

  public function create(bool $another = false): void
  {
    try {
      $data = $this->form->getState();
      $productRequest = new ProductRequest();
      $productRequest->merge([
        'name' => strtoupper($data['name']),
        'category' => strtoupper($data['category']),
        'buying_price' => $data['buying_price'],
        'selling_price' => $data['selling_price'],
        'min_stock' => $data['min_stock'],
      ]);

      $productRepository = app(ProductRepositoryInterface::class);
      $controller = new ProductController($productRepository);
      $response = $controller->createProduct($productRequest, $data['petrol_id']);

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
          ->title($responseData->error)
          ->danger()
          ->send();
      }
    } catch (\Exception $e) {
      Notification::make()
        ->title('Error occurred')
        ->danger()
        ->send();
    }
  }

  protected function getRedirectUrl(): string
  {
    return $this->getResource()::getUrl('index');
  }
}

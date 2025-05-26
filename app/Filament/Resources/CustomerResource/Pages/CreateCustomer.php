<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Http\Controllers\CustomersController;
use App\Http\Requests\CustomersRequest;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;


class CreateCustomer extends CreateRecord
{
  protected static string $resource = CustomerResource::class;

  protected function getCreatedNotificationTitle(): ?string
  {
    return null;
  }

  public function create(bool $another = false): void
  {
    try {
      $data = $this->form->getState();

      $request = new CustomersRequest();
      $request->merge([
        'name' => $data['name'],
        'phone' => Str::start($data['phone'], '+254'),
      ]);

      $response = app(CustomersController::class)->createCustomer(
        request: $request,
        petrolStationId: $data['petrol_id']
      );

      $responseData = $response->getData();

      if ($responseData->status === 'ok') {
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
          ->success()
          ->send();
        $this->redirect($this->getResource()::getUrl('index'));
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

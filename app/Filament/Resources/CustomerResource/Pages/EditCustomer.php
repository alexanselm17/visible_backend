<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Http\Requests\CustomerUpdateRequest;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customers;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;


class EditCustomer extends EditRecord
{
  protected static string $resource = CustomerResource::class;

  protected function getRedirectUrl(): string
  {
    return $this->getResource()::getUrl('index');
  }

  protected function handleRecordUpdate(Model $record, array $data): Model
  {
    try {
      // First try API validation
      $customerRequest = new CustomerUpdateRequest();
      $customerRequest->merge([
        'name' => $data['name'],
        'phone' => Str::start($data['phone'], '+254'),
      ]);

      $controller = app()->make('App\Http\Controllers\CustomersController');
      $response = $controller->updateCustomer($customerRequest, $record->id);
      $responseData = json_decode($response->getContent(), true);

      // Check for API validation errors
      if (isset($responseData['errors'])) {
        $this->handleValidationErrors($responseData['errors']);
        return $record; // Return original record to prevent form reset
      }

      // Try to update the database record
      try {
        $record->update([
          'name' => $data['name'],
          'phone' => $data['phone'],
          'petrol_station_id' => $data['petrol_station_id'] ?? $record->petrol_station_id,
        ]);

        // Show success notification
        Notification::make()
          ->title($responseData['message'] ?? 'Customer updated successfully')
          ->success()
          ->send();

        return $record;
      } catch (QueryException $e) {
        // Handle database unique constraint violations
        if ($e->getCode() == 23000) { // Integrity constraint violation
          $field = null;
          $message = '';

          // Extract field name from error message
          if (str_contains($e->getMessage(), 'customers_name_unique')) {
            $field = 'name';
            $message = 'This name is already taken.';
          } elseif (str_contains($e->getMessage(), 'customers_phone_unique')) {
            $field = 'phone';
            $message = 'This phone number is already taken.';
          } else {
            $message = 'This record already exists.';
          }

          $errors = $field ? [$field => [$message]] : ['general' => [$message]];

          $this->handleValidationErrors($errors);
          return $record;
        }

        throw $e;
      }
    } catch (\Exception $e) {
      Notification::make()
        ->title('Error')
        ->body('An error occurred while updating the customer.')
        ->danger()
        ->send();

      return $record; // Return original record to prevent form reset
    }
  }

  protected function handleValidationErrors(array $errors): void
  {
    $errorMessages = [];
    foreach ($errors as $field => $fieldErrors) {
      foreach ($fieldErrors as $error) {
        $errorMessages[] = $error;
      }
    }

    Notification::make()
      ->title('Validation Error')
      ->body(implode("\n", $errorMessages))
      ->danger()
      ->send();

    throw ValidationException::withMessages($errors);
  }

  protected function getSavedNotificationTitle(): ?string
  {
    return null;
  }

  protected function afterSave(): void
  {
    // Empty to prevent default notification
  }

  protected function mutateFormDataBeforeFill(array $data): array
  {
    if (isset($data['petrol_id'])) {
      $data['petrol_station_id'] = $data['petrol_id'];
    }
    return $data;
  }

  protected function mutateFormDataBeforeSave(array $data): array
  {
    if (isset($data['petrol_station_id'])) {
      $data['petrol_id'] = $data['petrol_station_id'];
    }
    return $data;
  }
}

<?php

namespace App\Filament\Resources\PumpsResource\Pages;

use App\Filament\Resources\PumpsResource;
use App\Http\Controllers\ProductController;
use App\Http\Requests\CreatePumpRequest;
use App\Repositories\Products\ProductRepositoryInterface;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePumps extends CreateRecord
{
    protected static string $resource = PumpsResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }

    public function create(bool $another = false): void
    {
        try {
            // Get the form data
            $data = $this->form->getState();

            // Create a new pump request instance and set its data
            $pumpRequest = new CreatePumpRequest();
            $pumpRequest->merge([
                'name' => $data['name'],
                'curr_value' => 0.00,
                'curr_volume' => 0.00,
                'curr_cash' => 0.00,
                'drum_id' => $data['drum_id'],
            ]);

            // Get the repository instance
            $productRepository = app(ProductRepositoryInterface::class);
            
            // Create controller instance with repository
            $controller = new ProductController($productRepository);
            
            // Call the createPump method
            $response = $controller->createPump($pumpRequest, $data['petrol_station_id']);

            // Get the response data
            $responseData = $response->getData();

            if ($responseData->ok === true) {
                Notification::make()
                    ->title($responseData->message)
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title($responseData->message)
                    ->danger()
                    ->send();
            }

            // Redirect back to listing
            $this->redirect($this->getResource()::getUrl('index'));

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error occurred')
                ->body($e->getMessage())
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
}
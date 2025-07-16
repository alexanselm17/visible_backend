<?php

namespace App\Filament\Resources\PetrolStationResource\Pages;

use App\Filament\Resources\PetrolStationResource;
use App\Http\Controllers\SetupController;
use App\Http\Requests\CreatePetrolStationRequest;
use App\Http\Requests\PetrolStation;
use App\Repositories\Setup\SetupRepositoryInterface;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePetrolStation extends CreateRecord
{
    protected static string $resource = PetrolStationResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }

    public function create(bool $another = false): void
    {
        try {
            // Get the form data
            $data = $this->form->getState();

            // Create a new petrol station request instance and set its data
            $petrolStationRequest = new PetrolStation();
            $petrolStationRequest->merge([
                'name' => $data['name'],
                'type' => $data['type'],
            ]);

            // Get the repository instance
            $setupRepository = app(SetupRepositoryInterface::class);
            
            // Create controller instance with repository
            $controller = new SetupController($setupRepository);
            
            // Call the createPetrolStation method
            $response = $controller->registerPetrolStation($petrolStationRequest, auth()->user()->company_id);

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
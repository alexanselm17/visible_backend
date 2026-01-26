<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Http\Controllers\ProductController;
use App\Http\Requests\StartCampaignRequest;
use App\Repositories\Products\ProductRepositoryInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    public function create(bool $another = false): void
    {
        try {
            $data = $this->form->getState();

            $campaignRequest = new StartCampaignRequest;
            $campaignRequest->merge([
                'name' => $data['name'],
            ]);

            $productRepository = app(ProductRepositoryInterface::class);
            $controller = new ProductController($productRepository);
            $response = $controller->startCampaigns($campaignRequest);

            $responseData = $response->getData();

            if ($responseData->ok === true) {
                Notification::make()
                    ->title($responseData->message)
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            } else {
                Notification::make()
                    ->title($responseData->error ?? 'Failed to create campaign')
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('An unexpected error occurred')
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

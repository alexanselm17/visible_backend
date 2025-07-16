<?php


namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\ProductController;
use App\Http\Requests\StartCampaignRequest;
use App\Repositories\Products\ProductRepositoryInterface;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    public function create(bool $another = false): void
    {
        try {
            $data = $this->form->getState();

            $validUntil = isset($data['valid_until_date'], $data['valid_until_time'])
                ? Carbon::parse("{$data['valid_until_date']} {$data['valid_until_time']}")->toDateTimeString()
                : null;

            $campaignRequest = new StartCampaignRequest();
            $campaignRequest->merge([
                'name' => $data['name'],
                'capital_invested' => $data['capital_invested'],
                'reward' => $data['reward'],
                'capacity' => $data['capacity'],
                'valid_until' => $validUntil,
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

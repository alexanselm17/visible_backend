<?php

namespace App\Filament\Resources\AdvertImagesResource\Pages;

use App\Filament\Resources\AdvertImagesResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use App\Http\Requests\ProductAdvertRequest;
use App\Http\Controllers\ProductController;
use App\Repositories\Products\ProductRepositoryInterface;

class CreateAdvertImages extends CreateRecord
{
    protected static string $resource = AdvertImagesResource::class;

    public function create(bool $another = false): void
    {
        try {
            $data = $this->form->getState();
            $request = new ProductAdvertRequest();

            // Manually add file (image/video) and other fields if needed here
            // Ensure files are in request()->files and available through form

            $request->merge([
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'category' => $data['category'] ?? '',
                'badge' => $data['badge'] ?? '',
                'image' => $data['image'] ?? null,
                'video' => $data['video'] ?? null,
            ]);

            // Assume you are passing campaign_id via a hidden field
            $campaignId = $data['campaign_id'];

            // Create controller + call method
            $productRepository = app(ProductRepositoryInterface::class);
            $controller = new ProductController($productRepository);
            $response = $controller->uploadAdvertProducts($request, $campaignId);

            $responseData = $response->getData();

            if ($responseData->ok === true) {
                Notification::make()
                    ->title($responseData->message)
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            } else {
                Notification::make()
                    ->title($responseData->error ?? 'Failed to upload advert')
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
